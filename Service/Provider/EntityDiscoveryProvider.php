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
use Klevu\IndexingApi\Service\Provider\EntityProviderProviderInterface;
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
     * @var EntityProviderProviderInterface
     */
    private readonly EntityProviderProviderInterface $entityProviderProvider;
    /**
     * @var string
     */
    private readonly string $entityType;
    /**
     * @var bool
     */
    private readonly bool $isParentRelationSynced;
    /**
     * Setting this flag to true will significantly increase discovery time as data is retrieved for each store
     * during isIndexable calculation
     *
     * @var bool
     */
    private readonly bool $isCheckIsIndexableAtStoreScope;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ApiKeyProviderInterface $apiKeyProvider
     * @param CurrentScopeFactory $currentScopeFactory
     * @param MagentoEntityInterfaceFactory $magentoEntityInterfaceFactory
     * @param IsIndexableDeterminerInterface $isIndexableDeterminer ,
     * @param EntityProviderProviderInterface $entityProviderProvider
     * @param string $entityType
     * @param bool $isParentRelationSynced
     * @param bool $isCheckIsIndexableAtStoreScope
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ApiKeyProviderInterface $apiKeyProvider,
        CurrentScopeFactory $currentScopeFactory,
        MagentoEntityInterfaceFactory $magentoEntityInterfaceFactory,
        IsIndexableDeterminerInterface $isIndexableDeterminer,
        EntityProviderProviderInterface $entityProviderProvider,
        string $entityType,
        bool $isParentRelationSynced = false,
        bool $isCheckIsIndexableAtStoreScope = false,
    ) {
        $this->storeManager = $storeManager;
        $this->apiKeyProvider = $apiKeyProvider;
        $this->currentScopeFactory = $currentScopeFactory;
        $this->magentoEntityInterfaceFactory = $magentoEntityInterfaceFactory;
        $this->isIndexableDeterminer = $isIndexableDeterminer;
        $this->entityType = $entityType;
        $this->entityProviderProvider = $entityProviderProvider;
        $this->isParentRelationSynced = $isParentRelationSynced;
        $this->isCheckIsIndexableAtStoreScope = $isCheckIsIndexableAtStoreScope;
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
     * @return \Generator<string, \Generator<MagentoEntityInterface[]>>
     * @throws NoSuchEntityException
     * @throws StoreApiKeyException
     */
    public function getData(
        ?array $apiKeys = [],
        ?array $entityIds = [],
        ?array $entitySubtypes = [],
    ): \Generator {
        $storesByApiKey = $this->getStoresByApiKeys($apiKeys);
        foreach ($storesByApiKey as $storeApiKey => $stores) {
            $magentoEntities = $this->createIndexingEntities(
                stores: $stores,
                apiKey: $storeApiKey,
                entityIds: $entityIds,
                entitySubtypes: $entitySubtypes,
            );
            yield $storeApiKey => $magentoEntities;
            unset ($magentoEntities);
        }
        unset($storesByApiKey);
    }

    /**
     * @return string[]
     */
    public function getEntityProviderTypes(): array
    {
        $return = [];
        foreach ($this->entityProviderProvider->get() as $entityProvider) {
            $return[] = $entityProvider->getEntitySubtype();
        }

        return $return;
    }

    /**
     * @param StoreInterface[] $stores
     * @param string $apiKey
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return \Generator<MagentoEntityInterface[]>
     */
    private function createIndexingEntities(
        array $stores,
        string $apiKey,
        array $entityIds,
        array $entitySubtypes,
    ): \Generator {
        foreach ($this->entityProviderProvider->get() as $entityProvider) {
            if (
                $entitySubtypes
                && !in_array($entityProvider->getEntitySubtype(), $entitySubtypes, true)
            ) {
                continue;
            }
            $entityData = $this->getEntityData(entityProvider: $entityProvider, stores: $stores, entityIds: $entityIds);
            foreach ($entityData as $entities) {
                $isIndexable = $this->generateIsIndexableData(
                    entityProvider: $entityProvider,
                    stores: $stores,
                    entities: $entities,
                );
                $magentoEntities = [];
                /** @var ExtensibleDataInterface|PageInterface $entity */
                foreach ($entities as $entity) {
                    $key = $this->getMagentoEntityId(entity: $entity);
                    $magentoEntities[$key] = $this->createMagentoEntity(
                        apiKey: $apiKey,
                        entity: $entity,
                        isIndexable: $isIndexable[$key] ?? false,
                        entitySubtype: $entityProvider->getEntitySubtype(),
                    );
                }
                yield $magentoEntities;
                unset($magentoEntities, $isIndexable, $entities);
            }
            unset($entityData);
        }
    }

    /**
     * @param EntityProviderInterface $entityProvider
     * @param StoreInterface[] $stores
     * @param int[] $entityIds
     *
     * @return \Generator|null
     */
    private function getEntityData(
        EntityProviderInterface $entityProvider,
        array $stores,
        array $entityIds,
    ): ?\Generator {
        if (!$this->isCheckIsIndexableAtStoreScope || count($stores) === 1) {
            $store = array_shift($stores);

            return $entityProvider->get(store: $store, entityIds: $entityIds);
        }

        return $entityProvider->get(entityIds: $entityIds);
    }

    /**
     * @param EntityProviderInterface $entityProvider
     * @param storeInterface[] $stores
     * @param array<ExtensibleDataInterface|PageInterface> $entities
     *
     * @return bool[]
     */
    private function generateIsIndexableData(
        EntityProviderInterface $entityProvider,
        array $stores,
        array $entities,
    ): array {
        $isIndexable = [];
        if ($this->isCheckIsIndexableAtStoreScope && count($stores) > 1) {
            $batchEntityIds = array_map(
                callback: static fn (ExtensibleDataInterface|PageInterface $magentoEntity): int => (
                    (int)$magentoEntity->getId()
                ),
                array: $entities,
            );
            foreach ($stores as $store) {
                $storeEntityGenerator = $entityProvider->get(store: $store, entityIds: $batchEntityIds);
                foreach ($storeEntityGenerator as $storeEntities) {
                    /** @var ExtensibleDataInterface|PageInterface $storeEntity */
                    foreach ($storeEntities as $storeEntity) {
                        $key = $this->getMagentoEntityId(entity: $storeEntity);
                        $isIndexable[$key] = ($isIndexable[$key] ?? false)
                            || $this->isIndexableDeterminer->execute(
                                entity: $storeEntity,
                                store: $store,
                                entitySubtype: $entityProvider->getEntitySubtype() ?? '',
                            );
                    }
                }
            }
        } else {
            $store = array_shift($stores);
            foreach ($entities as $entity) {
                $key = $this->getMagentoEntityId(entity: $entity);
                $isIndexable[$key] = $this->isIndexableDeterminer->execute(
                    entity: $entity,
                    store: $store,
                    entitySubtype: $entityProvider->getEntitySubtype() ?? '',
                );
            }
        }

        return $isIndexable;
    }

    /**
     * @param string $apiKey
     * @param ExtensibleDataInterface|PageInterface $entity
     * @param bool $isIndexable
     * @param string|null $entitySubtype
     *
     * @return MagentoEntityInterface
     */
    private function createMagentoEntity(
        string $apiKey,
        ExtensibleDataInterface|PageInterface $entity,
        bool $isIndexable,
        ?string $entitySubtype = null,
    ): MagentoEntityInterface {
        $entityParentId = $this->getParentId($entity);

        return $this->magentoEntityInterfaceFactory->create([
            'entityId' => (int)$entity->getId(), // @phpstan-ignore-line
            'entityParentId' => $entityParentId ? (int)$entityParentId : null,
            'apiKey' => $apiKey,
            'isIndexable' => $isIndexable,
            'entitySubtype' => $entitySubtype,
        ]);
    }

    /**
     * @param ExtensibleDataInterface|PageInterface $entity
     *
     * @return string
     */
    private function getMagentoEntityId(ExtensibleDataInterface|PageInterface $entity): string
    {
        $entityId = (int)$entity->getId(); // @phpstan-ignore-line
        $entityParentId = $this->getParentId($entity);

        return $entityId . '-' . $entityParentId;
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

    /**
     * @param string[]|null $apiKeys
     *
     * @return array<string, StoreInterface[]>
     * @throws NoSuchEntityException
     * @throws StoreApiKeyException
     */
    private function getStoresByApiKeys(?array $apiKeys): array
    {
        $storesByApiKey = [];
        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $storeApiKey = $this->apiKeyProvider->get(
                scope: $this->currentScopeFactory->create(data: ['scopeObject' => $store]),
            );
            if (!$storeApiKey || ($apiKeys && !in_array($storeApiKey, $apiKeys, true))) {
                continue;
            }
            $storesByApiKey[$storeApiKey][] = $store;
        }
        if (!$storesByApiKey && $apiKeys) {
            throw new StoreApiKeyException(
                __('No store found with the provided API Keys.'),
            );
        }

        return $storesByApiKey;
    }
}
