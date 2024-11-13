<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Exception\IndexingEntitySaveException;
use Klevu\Indexing\Model\DiscoveryResultFactory;
use Klevu\IndexingApi\Api\Data\DiscoveryResultInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Service\Action\AddIndexingEntitiesActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToBeIndexableActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToDeleteActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToUpdateActionInterface;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToAddServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToDeleteServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToSetToIndexableServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToSetToNotIndexableServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToUpdateServiceInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class EntityDiscoveryOrchestratorService implements EntityDiscoveryOrchestratorServiceInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var DiscoveryResultFactory
     */
    private readonly DiscoveryResultFactory $discoveryResultFactory;
    /**
     * @var FilterEntitiesToAddServiceInterface
     */
    private readonly FilterEntitiesToAddServiceInterface $filterEntitiesToAddService;
    /**
     * @var FilterEntitiesToDeleteServiceInterface
     */
    private readonly FilterEntitiesToDeleteServiceInterface $filterEntitiesToDeleteService;
    /**
     * @var FilterEntitiesToUpdateServiceInterface
     */
    private readonly FilterEntitiesToUpdateServiceInterface $filterEntitiesToUpdateService;
    /**
     * @var FilterEntitiesToSetToIndexableServiceInterface
     */
    private readonly FilterEntitiesToSetToIndexableServiceInterface $filterEntitiesToSetToIndexableService;
    /**
     * @var FilterEntitiesToSetToNotIndexableServiceInterface
     */
    private readonly FilterEntitiesToSetToNotIndexableServiceInterface $filterEntitiesToSetToNotIndexableService;
    /**
     * @var AddIndexingEntitiesActionInterface
     */
    private readonly AddIndexingEntitiesActionInterface $addIndexingEntitiesAction;
    /**
     * @var SetIndexingEntitiesToDeleteActionInterface
     */
    private readonly SetIndexingEntitiesToDeleteActionInterface $setIndexingEntitiesToDeleteAction;
    /**
     * @var SetIndexingEntitiesToUpdateActionInterface
     */
    private readonly SetIndexingEntitiesToUpdateActionInterface $setIndexingEntitiesToUpdateAction;
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;
    /**
     * @var SetIndexingEntitiesToBeIndexableActionInterface
     */
    private readonly SetIndexingEntitiesToBeIndexableActionInterface $setIndexingEntitiesToBeIndexableAction;
    /**
     * @var EntityDiscoveryProviderInterface[]
     */
    private array $discoveryProviders = [];
    /**
     * @var int
     */
    private readonly int $batchSize;
    /**
     * @var bool
     */
    private bool $success = true;
    /**
     * @var string[]
     */
    private array $messages = [];
    /**
     * @var int[]
     */
    private array $processedIds = [];

    /**
     * @param LoggerInterface $logger
     * @param DiscoveryResultFactory $discoveryResultFactory
     * @param FilterEntitiesToAddServiceInterface $filterEntitiesToAddService
     * @param FilterEntitiesToDeleteServiceInterface $filterEntitiesToDeleteService
     * @param FilterEntitiesToUpdateServiceInterface $filterEntitiesToUpdateService
     * @param FilterEntitiesToSetToIndexableServiceInterface $filterEntitiesToSetToIndexableService
     * @param FilterEntitiesToSetToNotIndexableServiceInterface $filterEntitiesToSetToNotIndexableService
     * @param AddIndexingEntitiesActionInterface $addIndexingEntitiesAction
     * @param SetIndexingEntitiesToDeleteActionInterface $setIndexingEntitiesToDeleteAction
     * @param SetIndexingEntitiesToUpdateActionInterface $setIndexingEntitiesToUpdateAction
     * @param SetIndexingEntitiesToBeIndexableActionInterface $setIndexingEntitiesToBeIndexableAction
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param EntityDiscoveryProviderInterface[] $discoveryProviders
     * @param int $batchSize
     */
    public function __construct(
        LoggerInterface $logger,
        DiscoveryResultFactory $discoveryResultFactory,
        FilterEntitiesToAddServiceInterface $filterEntitiesToAddService,
        FilterEntitiesToDeleteServiceInterface $filterEntitiesToDeleteService,
        FilterEntitiesToUpdateServiceInterface $filterEntitiesToUpdateService,
        FilterEntitiesToSetToIndexableServiceInterface $filterEntitiesToSetToIndexableService,
        FilterEntitiesToSetToNotIndexableServiceInterface $filterEntitiesToSetToNotIndexableService,
        AddIndexingEntitiesActionInterface $addIndexingEntitiesAction,
        SetIndexingEntitiesToDeleteActionInterface $setIndexingEntitiesToDeleteAction,
        SetIndexingEntitiesToUpdateActionInterface $setIndexingEntitiesToUpdateAction,
        SetIndexingEntitiesToBeIndexableActionInterface $setIndexingEntitiesToBeIndexableAction,
        IndexingEntityProviderInterface $indexingEntityProvider,
        array $discoveryProviders = [],
        int $batchSize = 2500,
    ) {
        $this->logger = $logger;
        $this->discoveryResultFactory = $discoveryResultFactory;
        $this->filterEntitiesToAddService = $filterEntitiesToAddService;
        $this->filterEntitiesToDeleteService = $filterEntitiesToDeleteService;
        $this->filterEntitiesToUpdateService = $filterEntitiesToUpdateService;
        $this->filterEntitiesToSetToIndexableService = $filterEntitiesToSetToIndexableService;
        $this->filterEntitiesToSetToNotIndexableService = $filterEntitiesToSetToNotIndexableService;
        $this->addIndexingEntitiesAction = $addIndexingEntitiesAction;
        $this->setIndexingEntitiesToDeleteAction = $setIndexingEntitiesToDeleteAction;
        $this->setIndexingEntitiesToUpdateAction = $setIndexingEntitiesToUpdateAction;
        $this->setIndexingEntitiesToBeIndexableAction = $setIndexingEntitiesToBeIndexableAction;
        $this->indexingEntityProvider = $indexingEntityProvider;
        array_walk($discoveryProviders, [$this, 'addDiscoveryProvider']);
        $this->batchSize = $batchSize;
    }

    /**
     * @param string[] $entityTypes
     * @param string[] $apiKeys
     * @param int[]|null $entityIds
     * @param string[]|null $entitySubtypes
     *
     * @return DiscoveryResultInterface
     */
    public function execute(
        array $entityTypes = [],
        array $apiKeys = [],
        ?array $entityIds = null,
        ?array $entitySubtypes = [],
    ): DiscoveryResultInterface {
        $discoveryProviders = $this->getDiscoveryProviders($entityTypes);
        foreach ($discoveryProviders as $discoveryProvider) {
            $this->processEntityAdditionsAndUpdates(
                discoveryProvider: $discoveryProvider,
                apiKeys: $apiKeys,
                entityIds: $entityIds,
                entitySubtypes: $entitySubtypes,
            );
            $this->processEntityDeletions(
                discoveryProvider: $discoveryProvider,
                apiKeys: $apiKeys,
                entityIds: $entityIds,
                entitySubtypes: $entitySubtypes,
            );
        }

        return $this->discoveryResultFactory->create(data: [
            'isSuccess' => $this->success,
            'messages' => $this->messages,
            'processedIds' => $this->processedIds,
        ]);
    }

    /**
     * @param EntityDiscoveryProviderInterface $discoveryProvider
     * @param string $entityType
     *
     * @return void
     */
    private function addDiscoveryProvider(EntityDiscoveryProviderInterface $discoveryProvider, string $entityType): void
    {
        $this->discoveryProviders[$entityType] = $discoveryProvider;
    }

    /**
     * @param EntityDiscoveryProviderInterface $discoveryProvider
     * @param string[] $apiKeys
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function processEntityAdditionsAndUpdates(
        EntityDiscoveryProviderInterface $discoveryProvider,
        array $apiKeys,
        ?array $entityIds = null,
        ?array $entitySubtypes = [],
    ): void {
        try {
            /** @var \Generator<string, \Generator<MagentoEntityInterface[]>> $magentoEntitiesByApiKey */
            $magentoEntitiesByApiKey = $discoveryProvider->getData(
                apiKeys: $apiKeys,
                entityIds: $entityIds ?? [],
                entitySubtypes: $entitySubtypes,
            );
            $type = $discoveryProvider->getEntityType();
            foreach ($magentoEntitiesByApiKey as $apiKey => $magentoEntitiesById) {
                foreach ($magentoEntitiesById as $magentoEntities) {
                    // set as indexable any indexable entities that were set to not indexable
                    // also sets next action to add
                    $this->setNewlyIndexableEntitiesToBeIndexable(
                        type: $type,
                        magentoEntities: $magentoEntities,
                        apiKey: $apiKey,
                        entityIds: $entityIds ?? [],
                        entitySubtypes: $entitySubtypes,
                    );
                    // set next action to update for any entities that require an update
                    $this->setEntitiesToUpdate(
                        type: $type,
                        apiKey: $apiKey,
                        entityIds: $entityIds,
                        entitySubtypes: $entitySubtypes,
                    );
                    // set entities that are no longer indexable
                    $this->setNewlyNonIndexableEntitiesToNotIndexable(
                        type: $type,
                        magentoEntities: $magentoEntities,
                        apiKey: $apiKey,
                        entityIds: $entityIds ?? [],
                        entitySubtypes: $entitySubtypes,
                    );
                    // add any missing entities to klevu_indexing_entity
                    // do this last so the previous stages don't have to check these new entities
                    $this->addMissingEntitiesToIndexEntitiesTable(
                        type: $type,
                        magentoEntities: $magentoEntities,
                        apiKey: $apiKey,
                        entitySubtypes: $entitySubtypes,
                    );
                }
            }
        } catch (LocalizedException $exception) {
            $this->messages[] = $exception->getMessage();
            $this->success = false;
        }
    }

    /**
     * @param EntityDiscoveryProviderInterface $discoveryProvider
     * @param string[] $apiKeys
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function processEntityDeletions(
        EntityDiscoveryProviderInterface $discoveryProvider,
        array $apiKeys,
        ?array $entityIds,
        ?array $entitySubtypes,
    ): void {
        $type = $discoveryProvider->getEntityType();
        if (!$entitySubtypes) {
            $entitySubtypes = $discoveryProvider->getEntityProviderTypes();
        }
        foreach ($entitySubtypes as $entitySubtype) {
            $klevuIndexingEntitiesGenerator = $this->getKlevuIndexingEntitiesGenerator(
                entityType: $type,
                apiKeys: $apiKeys,
                entityIds: $entityIds ?? [],
                entitySubtypes: [$entitySubtype],
            );
            foreach ($klevuIndexingEntitiesGenerator as $klevuIndexingEntities) {
                $this->setRemovedEntitiesToDelete(
                    klevuIndexingEntities: $klevuIndexingEntities,
                    entityType: $type,
                    apiKeys: $apiKeys,
                    entitySubtypes: [$entitySubtype],
                );
            }
        }
    }

    /**
     * @param string[] $entityTypes
     *
     * @return EntityDiscoveryProviderInterface[]
     */
    private function getDiscoveryProviders(array $entityTypes): array
    {
        $this->validateDiscoveryProviders();
        if (!$entityTypes) {
            return $this->discoveryProviders;
        }

        $return = array_filter(
            array: $this->discoveryProviders,
            callback: static fn (EntityDiscoveryProviderInterface $provider) => (
                in_array(needle: $provider->getEntityType(), haystack: $entityTypes, strict: true)
            ),
        );
        if (!$return) {
            $this->success = false;
            $this->messages[] = 'Supplied entity types did not match any providers.';
        }

        return $return;
    }

    /**
     * @return void
     */
    private function validateDiscoveryProviders(): void
    {
        if (!$this->discoveryProviders) {
            $message = 'No providers available for entity discovery.';
            $this->logger->warning(
                message: 'Method: {method} - Warning: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $message,
                ],
            );
            $this->success = false;
            $this->messages[] = $message;
        }
    }

    /**
     * @param string $type
     * @param MagentoEntityInterface[] $magentoEntities
     * @param string $apiKey
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function addMissingEntitiesToIndexEntitiesTable(
        string $type,
        array $magentoEntities,
        string $apiKey,
        array $entitySubtypes,
    ): void {
        $filteredMagentoEntities = $this->filterEntitiesToAddService->execute(
            magentoEntities: $magentoEntities,
            type: $type,
            apiKey: $apiKey,
            entitySubtypes: $entitySubtypes,
        );
        try {
            $this->addIndexingEntitiesAction->execute(
                type: $type,
                magentoEntities: iterator_to_array($filteredMagentoEntities),
            );
        } catch (IndexingEntitySaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }

    /**
     * @param string $type
     * @param MagentoEntityInterface[] $magentoEntities
     * @param string $apiKey
     * @param int[]|null $entityIds
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function setNewlyIndexableEntitiesToBeIndexable(
        string $type,
        array $magentoEntities,
        string $apiKey,
        ?array $entityIds,
        array $entitySubtypes,
    ): void {
        $indexingEntityIds = $this->filterEntitiesToSetToIndexableService->execute(
            magentoEntities: $magentoEntities,
            type: $type,
            apiKey: $apiKey,
            entityIds: $entityIds,
            entitySubtypes: $entitySubtypes,
        );
        try {
            $this->setIndexingEntitiesToBeIndexableAction->execute(entityIds:$indexingEntityIds);
        } catch (IndexingEntitySaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }

    /**
     * @param string $type
     * @param string $apiKey
     * @param int[] $entityIds
     *
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function setEntitiesToUpdate(
        string $type,
        string $apiKey,
        ?array $entityIds,
        array $entitySubtypes,
    ): void {
        if (null === $entityIds && !$entitySubtypes) {
            return;
        }
        $klevuEntityIds = $this->filterEntitiesToUpdateService->execute(
            type: $type,
            entityIds: $entityIds,
            apiKey: $apiKey,
            entitySubtypes: $entitySubtypes,
        );

        try {
            $this->setIndexingEntitiesToUpdateAction->execute(entityIds: $klevuEntityIds);
            $this->processedIds[] = $klevuEntityIds;
        } catch (IndexingEntitySaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }

    /**
     * @param IndexingEntityInterface[] $klevuIndexingEntities
     * @param string $entityType
     * @param string[] $apiKeys
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function setRemovedEntitiesToDelete(
        array $klevuIndexingEntities,
        string $entityType,
        array $apiKeys,
        array $entitySubtypes,
    ): void {
        $klevuEntityIds = $this->filterEntitiesToDeleteService->execute(
            klevuIndexingEntities: $klevuIndexingEntities,
            type: $entityType,
            apiKeys: $apiKeys,
            entitySubtypes: $entitySubtypes,
        );

        try {
            $this->setIndexingEntitiesToDeleteAction->execute(entityIds: $klevuEntityIds);
        } catch (IndexingEntitySaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }

    /**
     * @param string $type
     * @param MagentoEntityInterface[] $magentoEntities
     * @param string $apiKey
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function setNewlyNonIndexableEntitiesToNotIndexable(
        string $type,
        array $magentoEntities,
        string $apiKey,
        ?array $entityIds,
        array $entitySubtypes,
    ): void {
        $klevuEntityIds = $this->filterEntitiesToSetToNotIndexableService->execute(
            magentoEntities: $magentoEntities,
            type: $type,
            apiKey: $apiKey,
            entityIds: $entityIds,
            entitySubtypes: $entitySubtypes,
        );

        try {
            $this->setIndexingEntitiesToDeleteAction->execute(entityIds: $klevuEntityIds);
        } catch (IndexingEntitySaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }

    /**
     * @param string $entityType
     * @param string[] $apiKeys
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return \Generator<IndexingEntityInterface[]>
     */
    private function getKlevuIndexingEntitiesGenerator(
        string $entityType,
        array $apiKeys,
        array $entityIds,
        array $entitySubtypes,
    ): \Generator {
        $lastRecordId = 0;
        while (true) {
            $indexingEntities = $this->indexingEntityProvider->get(
                entityType: $entityType,
                apiKeys: $apiKeys,
                entityIds: $entityIds,
                isIndexable: true,
                pageSize: $this->batchSize,
                startFrom: $lastRecordId + 1,
                entitySubtypes: $entitySubtypes,
            );
            if (!count($indexingEntities)) {
                break;
            }
            yield $indexingEntities;
            $lastRecord = array_pop($indexingEntities);
            $lastRecordId = $lastRecord->getId();
            if (!$lastRecordId) {
                break;
            }
        }
    }
}
