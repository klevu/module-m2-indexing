<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\ViewModel\Config\Information;

use Klevu\Configuration\Model\CurrentScopeFactory;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
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
     * @return array<string, array<string, array<string, string>>>
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
            foreach ($this->indexingAttributeProvider->getTypes(apiKey: $storeApiKey) as $attributeType) {
                $return[$storeApiKey][$attributeType]['total'] = (string)$this->indexingAttributeProvider->count(
                    attributeType: $attributeType,
                    apiKey: $storeApiKey,
                );
                $return[$storeApiKey][$attributeType]['indexable'] = (string)$this->indexingAttributeProvider->count(
                    attributeType: $attributeType,
                    apiKey: $storeApiKey,
                    isIndexable: true,
                );
                foreach (Actions::cases() as $nextAction) {
                    $count = (string)$this->indexingAttributeProvider->count(
                        attributeType: $attributeType,
                        apiKey: $storeApiKey,
                        nextAction: $nextAction,
                        isIndexable: true,
                    );
                    $return[$storeApiKey][$attributeType][$nextAction->value] = $count;
                }
            }
        }
        $this->indexingAttributes = $return;

        return $return;
    }
}
