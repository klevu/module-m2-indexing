<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\ViewModel\Config\Information;

use Klevu\Configuration\Model\CurrentScopeFactory;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
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
     * @return array<string, array<string, array<string, string>>>
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
            foreach ($this->indexingEntityProvider->getTypes(apiKey: $storeApiKey) as $entityType) {
                $return[$storeApiKey][$entityType]['total'] = (string)$this->indexingEntityProvider->count(
                    entityType: $entityType,
                    apiKey: $storeApiKey,
                );
                $return[$storeApiKey][$entityType]['indexable'] = (string)$this->indexingEntityProvider->count(
                    entityType: $entityType,
                    apiKey: $storeApiKey,
                    isIndexable: true,
                );
                foreach (Actions::cases() as $nextAction) {
                    $count = (string)$this->indexingEntityProvider->count(
                        entityType: $entityType,
                        apiKey: $storeApiKey,
                        nextAction: $nextAction,
                        isIndexable: true,
                    );
                    $return[$storeApiKey][$entityType][$nextAction->value] = $count;
                }
            }
        }
        $this->indexingEntities = $return;

        return $return;
    }
}
