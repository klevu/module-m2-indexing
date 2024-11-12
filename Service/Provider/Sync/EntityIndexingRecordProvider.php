<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider\Sync;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Configuration\Service\Provider\StoresProviderInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\EntityIndexingDeleteRecordInterface;
use Klevu\IndexingApi\Model\EntityIndexingRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityIndexingDeleteRecordCreatorServiceInterface;
use Klevu\IndexingApi\Service\EntityIndexingRecordCreatorServiceInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sync\EntityIndexingRecordProviderInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class EntityIndexingRecordProvider implements EntityIndexingRecordProviderInterface
{
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;
    /**
     * @var EntityIndexingRecordCreatorServiceInterface
     */
    private readonly EntityIndexingRecordCreatorServiceInterface $indexingRecordCreatorService;
    /**
     * @var EntityIndexingDeleteRecordCreatorServiceInterface
     */
    private readonly EntityIndexingDeleteRecordCreatorServiceInterface $indexingRecordDeleteCreatorService;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var StoresProviderInterface
     */
    private readonly StoresProviderInterface $storesProvider;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;
    /**
     * @var EntityProviderProviderInterface
     */
    private readonly EntityProviderProviderInterface $entityProviderProvider;
    /**
     * @var Actions
     */
    private Actions $action;
    /**
     * @var string
     */
    private readonly string $entityType;
    /**
     * @var int|null
     */
    private readonly ?int $batchSize;

    /**
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param EntityIndexingRecordCreatorServiceInterface $indexingRecordCreatorService
     * @param EntityIndexingDeleteRecordCreatorServiceInterface $indexingRecordDeleteCreatorService
     * @param LoggerInterface $logger
     * @param StoresProviderInterface $storesProvider
     * @param ScopeProviderInterface $scopeProvider
     * @param EntityProviderProviderInterface $entityProviderProvider
     * @param string $entityType
     * @param string $action
     * @param int|null $batchSize
     */
    public function __construct(
        IndexingEntityProviderInterface $indexingEntityProvider,
        EntityIndexingRecordCreatorServiceInterface $indexingRecordCreatorService,
        EntityIndexingDeleteRecordCreatorServiceInterface $indexingRecordDeleteCreatorService,
        LoggerInterface $logger,
        StoresProviderInterface $storesProvider,
        ScopeProviderInterface $scopeProvider,
        EntityProviderProviderInterface $entityProviderProvider,
        string $entityType,
        string $action,
        ?int $batchSize = null,
    ) {
        $this->indexingEntityProvider = $indexingEntityProvider;
        $this->indexingRecordCreatorService = $indexingRecordCreatorService;
        $this->indexingRecordDeleteCreatorService = $indexingRecordDeleteCreatorService;
        $this->logger = $logger;
        $this->storesProvider = $storesProvider;
        $this->scopeProvider = $scopeProvider;
        $this->entityProviderProvider = $entityProviderProvider;
        $this->setAction($action);
        $this->entityType = $entityType;
        $this->batchSize = $batchSize;
    }

    /**
     * @param string $apiKey
     *
     * @return \Generator
     */
    public function get(string $apiKey): \Generator
    {
        $stores = $this->storesProvider->get($apiKey);
        if (!$stores) {
            return;
        }
        $store = array_shift($stores);
        $this->scopeProvider->setCurrentScope($store);

        $lastRecordId = 0;
        while (true) {
            $entityIds = $this->getEntityIdsToSync(apiKey: $apiKey, lastRecordId: $lastRecordId + 1);
            if (!$entityIds) {
                break;
            }
            $entitiesCache = $this->getEntities(
                store: $store,
                entityIds: $entityIds,
            );
            foreach ($entityIds as $entity) {
                try {
                    yield $this->generateIndexingRecord($entity, $entitiesCache);
                } catch (\Exception $exception) {
                    $this->logger->error(
                        message: 'Method: {method}, Error: {message}',
                        context: [
                            'method' => __METHOD__,
                            'message' => $exception->getMessage(),
                        ],
                    );
                }
            }
            $lastRecord = array_pop($entityIds);
            $lastRecordId = $lastRecord['record_id'] ?? null;
            if (!$lastRecordId) {
                break;
            }
        }
    }

    /**
     * @param string $action
     *
     * @return void
     */
    private function setAction(string $action): void
    {
        $this->action = Actions::from($action);
    }

    /**
     * @param string $apiKey
     * @param int|null $lastRecordId
     *
     * @return array<array<string, int|null>>
     */
    private function getEntityIdsToSync(string $apiKey, ?int $lastRecordId = null): array
    {
        $indexingEntities = $this->indexingEntityProvider->get(
            entityType: $this->entityType,
            apiKeys: [$apiKey],
            nextAction: $this->action,
            isIndexable: true,
            pageSize: $this->batchSize,
            startFrom: $lastRecordId,
        );

        return array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): array => ([
                'record_id' => $indexingEntity->getId(),
                'entity_id' => $indexingEntity->getTargetId(),
                'parent_id' => $indexingEntity->getTargetParentId(),
            ]),
            array: $indexingEntities,
        );
    }

    /**
     * @param StoreInterface $store
     * @param int[][] $entityIds
     *
     * @return array<string, ExtensibleDataInterface|PageInterface>
     */
    private function getEntities(StoreInterface $store, array $entityIds): array
    {
        if ($this->action === Actions::DELETE) {
            return [];
        }
        $return = [];
        foreach ($this->entityProviderProvider->get() as $entityProvider) {
            /** @var \Generator<PageInterface[]>|null $entitiesGenerator */
            $entitiesGenerator = $entityProvider->get(
                store: $store,
                entityIds: $this->getUniqueEntityIds($entityIds),
            );
            foreach ($entitiesGenerator as $entityBatch) {
                foreach ($entityBatch as $key => $entity) {
                    $return[$key] = $entity;
                }
            }
        }

        return $return;
    }

    /**
     * @param int[][] $entityIds
     *
     * @return int[]
     */
    private function getUniqueEntityIds(array $entityIds): array
    {
        $entityIdsToLoad = [];
        array_walk(
            array: $entityIds,
            callback: static function (array $entity) use (&$entityIdsToLoad): void { //phpcs:ignore SlevomatCodingStandard.PHP.DisallowReference.DisallowedInheritingVariableByReference, Generic.Files.LineLength.TooLong
                $entityIdsToLoad[] = (int)$entity['entity_id'];
                if ($entity['parent_id'] ?? null) {
                    $entityIdsToLoad[] = (int)$entity['parent_id'];
                }
            },
        );

        return array_unique($entityIdsToLoad);
    }

    /**
     * @param array<string, int|null> $entity
     * @param array<string, ExtensibleDataInterface|PageInterface> $entitiesCache
     *
     * @return EntityIndexingRecordInterface|EntityIndexingDeleteRecordInterface
     */
    private function generateIndexingRecord(
        array $entity,
        array $entitiesCache,
    ): EntityIndexingRecordInterface|EntityIndexingDeleteRecordInterface {
        if ($this->action === Actions::DELETE) {
            return $this->indexingRecordDeleteCreatorService->execute(
                recordId: $entity['record_id'],
                entityId: $entity['entity_id'],
                parentId: $entity['parent_id'],
            );
        }

        return $this->indexingRecordCreatorService->execute(
            recordId: $entity['record_id'],
            action: $this->action,
            entity: $entitiesCache[(string)$entity['entity_id']],
            parent: $entitiesCache[(string)$entity['parent_id']] ?? null,
        );
    }
}
