<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\ViewModel\Config\Information;

use Klevu\Configuration\Model\CurrentScopeFactory;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\IndexingApi\ViewModel\Config\Information\IndexingEntitiesInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;

class IndexingEntities implements IndexingEntitiesInterface
{
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var ApiKeyProviderInterface
     */
    private ApiKeyProviderInterface $apiKeyProvider;
    /**
     * @var CurrentScopeFactory
     */
    private CurrentScopeFactory $currentScopeFactory;
    /**
     * @var mixed[][][]|null
     */
    private ?array $indexingEntities = null;

    /**
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param StoreManagerInterface $storeManager
     * @param ApiKeyProviderInterface $apiKeyProvider
     * @param CurrentScopeFactory $currentScopeFactory
     */
    public function __construct(
        IndexingEntityProviderInterface $indexingEntityProvider,
        StoreManagerInterface $storeManager,
        ApiKeyProviderInterface $apiKeyProvider,
        CurrentScopeFactory $currentScopeFactory,
    ) {
        $this->indexingEntityProvider = $indexingEntityProvider;
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
    public function hasEntities(): bool
    {
        return (bool)count($this->getEntities());
    }

    /**
     * @return mixed[][][]
     * @throws NoSuchEntityException
     */
    public function getEntities(): array
    {
        if (null !== $this->indexingEntities) {
            return $this->indexingEntities;
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
            $indexingEntities = $this->indexingEntityProvider->get(apiKey: $storeApiKey);
            foreach ($this->getEntityTypes($indexingEntities) as $entityType) {
                $indexingEntitiesByType = $this->filterIndexingEntityByType(
                    indexingEntities: $indexingEntities,
                    entityType: $entityType,
                );
                $return[$storeApiKey][$entityType]['total'] = count($indexingEntitiesByType);
                foreach (Actions::cases() as $nextAction) {
                    $indexingEntitiesByAction = $this->filterIndexingEntityByAction(
                        indexingEntities: $indexingEntitiesByType,
                        nextAction: $nextAction,
                    );
                    $indexingEntitiesByAction = $this->filterIndexingEntityByIsIndexable(
                        indexingEntities: $indexingEntitiesByAction,
                    );
                    $return[$storeApiKey][$entityType][$nextAction->value] = count($indexingEntitiesByAction);
                }
            }
        }
        $this->indexingEntities = $return;

        return $return;
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     *
     * @return string[]
     */
    private function getEntityTypes(array $indexingEntities): array
    {
        return array_unique(
            array_map(
                callback: static fn (IndexingEntityInterface $indexingEntity): string => (
                    $indexingEntity->getTargetEntityType()
                ),
                array: $indexingEntities,
            ),
        );
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param string $entityType
     *
     * @return IndexingEntityInterface[]
     */
    private function filterIndexingEntityByType(array $indexingEntities, string $entityType): array
    {
        return array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity): bool => (
                $indexingEntity->getTargetEntityType() === $entityType
            ),
        );
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param Actions $nextAction
     *
     * @return IndexingEntityInterface[]
     */
    private function filterIndexingEntityByAction(array $indexingEntities, Actions $nextAction): array
    {
        return array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity): bool => (
                $indexingEntity->getNextAction() === $nextAction
            ),
        );
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     *
     * @return IndexingEntityInterface[]
     */
    private function filterIndexingEntityByIsIndexable(array $indexingEntities): array
    {
        return array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity): bool => $indexingEntity->getIsIndexable(),
        );
    }
}
