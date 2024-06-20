<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\ViewModel\Config\Information;

use Klevu\Configuration\Model\CurrentScopeFactory;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Klevu\IndexingApi\ViewModel\Config\Information\IndexingAttributesInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;

class IndexingAttributes implements IndexingAttributesInterface
{
    /**
     * @var IndexingAttributeProviderInterface
     */
    private readonly IndexingAttributeProviderInterface $indexingAttributeProvider;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var ApiKeyProviderInterface
     */
    private readonly ApiKeyProviderInterface $apiKeyProvider;
    /**
     * @var CurrentScopeFactory
     */
    private readonly CurrentScopeFactory $currentScopeFactory;
    /**
     * @var mixed[][][]|null
     */
    private ?array $indexingAttributes = null;

    /**
     * @param IndexingAttributeProviderInterface $indexingAttributeProvider
     * @param StoreManagerInterface $storeManager
     * @param ApiKeyProviderInterface $apiKeyProvider
     * @param CurrentScopeFactory $currentScopeFactory
     */
    public function __construct(
        IndexingAttributeProviderInterface $indexingAttributeProvider,
        StoreManagerInterface $storeManager,
        ApiKeyProviderInterface $apiKeyProvider,
        CurrentScopeFactory $currentScopeFactory,
    ) {
        $this->indexingAttributeProvider = $indexingAttributeProvider;
        $this->storeManager = $storeManager;
        $this->apiKeyProvider = $apiKeyProvider;
        $this->currentScopeFactory = $currentScopeFactory;
    }

    /**
     * @return string[]
     */
    public function getChildBlocks(): array
    {
        return [];
    }

    /**
     * @return Phrase[][]
     */
    public function getMessages(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function getStyles(): string
    {
        return '';
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     */
    public function hasAttributes(): bool
    {
        return (bool)count($this->getAttributes());
    }

    /**
     * @return mixed[][][]
     * @throws NoSuchEntityException
     */
    public function getAttributes(): array
    {
        if (null !== $this->indexingAttributes) {
            return $this->indexingAttributes;
        }
        $return = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeApiKey = $this->apiKeyProvider->get(
                scope: $this->currentScopeFactory->create(data: ['scopeObject' => $store]),
            );
            if (!$storeApiKey || isset($return[$storeApiKey])) {
                continue;
            }
            $return[$storeApiKey] = [];
            $indexingAttributes = $this->indexingAttributeProvider->get(apiKey: $storeApiKey);
            foreach ($this->getAttributeTypes($indexingAttributes) as $entityType) {
                $indexingAttributesByType = $this->filterIndexingAttributeByType(
                    indexingAttributes: $indexingAttributes,
                    attributeType: $entityType,
                );
                $return[$storeApiKey][$entityType]['total'] = count($indexingAttributesByType);
                foreach (Actions::cases() as $nextAction) {
                    $indexingAttributesByAction = $this->filterIndexingAttributeByAction(
                        indexingAttributes: $indexingAttributesByType,
                        nextAction: $nextAction,
                    );
                    $indexingAttributesByAction = $this->filterIndexingAttributeByIsIndexable(
                        indexingAttributes: $indexingAttributesByAction,
                    );
                    $return[$storeApiKey][$entityType][$nextAction->value] = count($indexingAttributesByAction);
                }
            }
        }
        $this->indexingAttributes = $return;

        return $return;
    }

    /**
     * @param IndexingAttributeInterface[] $indexingAttributes
     *
     * @return string[]
     */
    private function getAttributeTypes(array $indexingAttributes): array
    {
        return array_unique(
            array_map(
                callback: static fn (IndexingAttributeInterface $indexingAttribute): string => (
                    $indexingAttribute->getTargetAttributeType()
                ),
                array: $indexingAttributes,
            ),
        );
    }

    /**
     * @param IndexingAttributeInterface[] $indexingAttributes
     * @param string $attributeType
     *
     * @return IndexingAttributeInterface[]
     */
    private function filterIndexingAttributeByType(array $indexingAttributes, string $attributeType): array
    {
        return array_filter(
            array: $indexingAttributes,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $indexingAttribute->getTargetAttributeType() === $attributeType
            ),
        );
    }

    /**
     * @param IndexingAttributeInterface[] $indexingAttributes
     * @param Actions $nextAction
     *
     * @return IndexingAttributeInterface[]
     */
    private function filterIndexingAttributeByAction(array $indexingAttributes, Actions $nextAction): array
    {
        return array_filter(
            array: $indexingAttributes,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $indexingAttribute->getNextAction() === $nextAction
            ),
        );
    }

    /**
     * @param IndexingAttributeInterface[] $indexingAttributes
     *
     * @return IndexingAttributeInterface[]
     */
    private function filterIndexingAttributeByIsIndexable(array $indexingAttributes): array
    {
        return array_filter(
            array: $indexingAttributes,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $indexingAttribute->getIsIndexable()
            ),
        );
    }
}
