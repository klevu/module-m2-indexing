<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Configuration\Model\CurrentScopeFactory;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\Indexing\Exception\StoreApiKeyException;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterfaceFactory;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class EntityDiscoveryProvider implements EntityDiscoveryProviderInterface
{
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
     * @var MagentoEntityInterfaceFactory
     */
    private readonly MagentoEntityInterfaceFactory $magentoEntityInterfaceFactory;
    /**
     * @var IsIndexableDeterminerInterface
     */
    private readonly IsIndexableDeterminerInterface $isIndexableDeterminer;
    /**
     * @var EntityProviderInterface[]
     */
    private array $entityProviders;
    /**
     * @var string
     */
    private readonly string $entityType;
    /**
     * @var bool
     */
    private readonly bool $isParentRelationSynced;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ApiKeyProviderInterface $apiKeyProvider
     * @param CurrentScopeFactory $currentScopeFactory
     * @param MagentoEntityInterfaceFactory $magentoEntityInterfaceFactory
     * @param IsIndexableDeterminerInterface $isIndexableDeterminer ,
     * @param EntityProviderInterface[] $entityProviders
     * @param string $entityType
     * @param bool $isParentRelationSynced
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ApiKeyProviderInterface $apiKeyProvider,
        CurrentScopeFactory $currentScopeFactory,
        MagentoEntityInterfaceFactory $magentoEntityInterfaceFactory,
        IsIndexableDeterminerInterface $isIndexableDeterminer,
        array $entityProviders,
        string $entityType,
        bool $isParentRelationSynced = false,
    ) {
        $this->storeManager = $storeManager;
        $this->apiKeyProvider = $apiKeyProvider;
        $this->currentScopeFactory = $currentScopeFactory;
        $this->magentoEntityInterfaceFactory = $magentoEntityInterfaceFactory;
        $this->isIndexableDeterminer = $isIndexableDeterminer;
        $this->entityType = $entityType;
        array_walk($entityProviders, [$this, 'addEntityProvider']);
        $this->isParentRelationSynced = $isParentRelationSynced;
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * @param string[]|null $apiKeys
     * @param int[]|null $entityIds
     * @param string[]|null $entitySubtypes
     *
     * @return MagentoEntityInterface[][]
     * @throws NoSuchEntityException
     * @throws StoreApiKeyException
     */
    public function getData(
        ?array $apiKeys = [],
        ?array $entityIds = [],
        ?array $entitySubtypes = [],
    ): array {
        $return = [];
        $storeFound = false;

        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $storeApiKey = $this->apiKeyProvider->get(
                scope: $this->currentScopeFactory->create(data: ['scopeObject' => $store]),
            );
            if (!$storeApiKey || ($apiKeys && !in_array($storeApiKey, $apiKeys, true))) {
                continue;
            }
            $storeFound = true;
            $return[$storeApiKey] = $this->createIndexingEntities(
                store: $store,
                apiKey: $storeApiKey,
                magentoEntities: $return[$storeApiKey] ?? [],
                entityIds: $entityIds,
                entitySubtypes: $entitySubtypes,
            );
        }
        if (!$storeFound) {
            throw new StoreApiKeyException(
                __('No store found with the provided API Key.'),
            );
        }

        return $return;
    }

    /**
     * @param EntityProviderInterface $entityProvider
     *
     * @return void
     */
    private function addEntityProvider(EntityProviderInterface $entityProvider): void
    {
        $this->entityProviders[] = $entityProvider;
    }

    /**
     * @param StoreInterface $store
     * @param string $apiKey
     * @param MagentoEntityInterface[] $magentoEntities
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return MagentoEntityInterface[]
     */
    private function createIndexingEntities(
        StoreInterface $store,
        string $apiKey,
        array $magentoEntities,
        array $entityIds,
        array $entitySubtypes,
    ): array {
        foreach ($this->entityProviders as $entityProvider) {
            if ($entitySubtypes && !in_array($entityProvider->getEntitySubtype(), $entitySubtypes, true)) {
                continue;
            }
            foreach ($entityProvider->get(store: $store, entityIds: $entityIds) as $entity) {
                $isIndexable = $this->isIndexableDeterminer->execute(
                    entity: $entity,
                    store: $store,
                    entitySubtype: $entityProvider->getEntitySubtype(),
                );
                $magentoEntities = $this->setMagentoEntity(
                    magentoEntities: $magentoEntities,
                    apiKey: $apiKey,
                    entity: $entity,
                    isIndexable: $isIndexable,
                    entitySubtype: $entityProvider->getEntitySubtype(),
                );
            }
        }

        return $magentoEntities;
    }

    /**
     * @param MagentoEntityInterface[] $magentoEntities
     * @param string $apiKey
     * @param ExtensibleDataInterface|PageInterface $entity
     * @param bool $isIndexable
     * @param string|null $entitySubtype
     *
     * @return MagentoEntityInterface[]
     */
    private function setMagentoEntity(
        array $magentoEntities,
        string $apiKey,
        ExtensibleDataInterface|PageInterface $entity,
        bool $isIndexable,
        ?string $entitySubtype = null,
    ): array {
        $entityId = (int)$entity->getId(); // @phpstan-ignore-line
        $entityParentId = $this->getParentId($entity);

        $key = $entityId . '-' . $entityParentId;
        $magentoEntities[$key] = ($magentoEntities[$key] ?? null)
            ? $this->updateIsIndexableForMagentoEntity(
                magentoEntity: $magentoEntities[$key],
                isIndexable: $isIndexable,
            )
            : $this->magentoEntityInterfaceFactory->create([
                'entityId' => $entityId,
                'entityParentId' => $entityParentId ? (int)$entityParentId : null,
                'apiKey' => $apiKey,
                'isIndexable' => $isIndexable,
                'entitySubtype' => $entitySubtype,
            ]);

        return $magentoEntities;
    }

    /**
     * @param MagentoEntityInterface $magentoEntity
     * @param bool $isIndexable
     *
     * @return MagentoEntityInterface
     */
    private function updateIsIndexableForMagentoEntity(
        MagentoEntityInterface $magentoEntity,
        bool $isIndexable,
    ): MagentoEntityInterface {
        // if entity is indexable in ANY store the apiKey is assigned to, set as indexable
        $magentoEntity->setIsIndexable(
            isIndexable: $magentoEntity->isIndexable() || $isIndexable,
        );

        return $magentoEntity;
    }

    /**
     * @param ExtensibleDataInterface|PageInterface $entity
     *
     * @return int
     */
    private function getParentId(ExtensibleDataInterface|PageInterface $entity): int
    {
        if (!$this->isParentRelationSynced) {
            return 0;
        }

        return match (true) {
            method_exists($entity, 'getParentId') => (int)$entity->getParentId(),
            method_exists($entity, 'getData') => (int)$entity->getData('parent_id'),
            default => 0,
        };
    }
}
