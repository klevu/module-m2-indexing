<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Constants;
use Klevu\Indexing\Exception\IndexingEntitySaveException;
use Klevu\Indexing\Model\DiscoveryResultFactory;
use Klevu\Indexing\Validator\BatchSizeValidator;
use Klevu\IndexingApi\Api\Data\DiscoveryResultInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
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
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Framework\App\ObjectManager;
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
     * @param ValidatorInterface|null $batchSizeValidator
     *
     * @throws \InvalidArgumentException
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
        int $batchSize = Constants::DEFAULT_INDEXING_BATCH_SIZE,
        ?ValidatorInterface $batchSizeValidator = null,
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

        $objectManager = ObjectManager::getInstance();
        $batchSizeValidator = $batchSizeValidator ?: $objectManager->get(BatchSizeValidator::class);
        if (!$batchSizeValidator->isValid($batchSize)) {
            throw new \InvalidArgumentException(
                message: sprintf(
                    'Invalid Batch Size: %s',
                    implode(', ', $batchSizeValidator->getMessages()),
                ),
            );
        }
        $this->batchSize = $batchSize;
    }

    /**
     * @param string[] $entityTypes
     * @param string[] $apiKeys
     * @param int[]|null $entityIds
     * @param string[]|null $entitySubtypes
     *
     * @return \Generator<\Generator<DiscoveryResultInterface>>
     */
    public function execute(
        array $entityTypes = [],
        array $apiKeys = [],
        ?array $entityIds = null,
        ?array $entitySubtypes = [],
    ): \Generator {
        $discoveryProviders = $this->getDiscoveryProviders($entityTypes);
        if (!$discoveryProviders) {
            yield $this->noDiscoveryProvidersResponse();
            return;
        }
        foreach ($discoveryProviders as $discoveryProvider) {
            yield $this->processEntityUpdates(
                discoveryProvider: $discoveryProvider,
                apiKeys: $apiKeys,
                entityIds: $entityIds,
                entitySubtypes: $entitySubtypes,
            );
            yield $this->processEntityDeletions(
                discoveryProvider: $discoveryProvider,
                apiKeys: $apiKeys,
                entityIds: $entityIds,
                entitySubtypes: $entitySubtypes,
            );
            // add any missing entities to klevu_indexing_entity
            // do this last so the update and delete stages don't have to check these new entities
            yield $this->processEntityAdditions(
                discoveryProvider: $discoveryProvider,
                apiKeys: $apiKeys,
                entityIds: $entityIds,
                entitySubtypes: $entitySubtypes,
            );
        }
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
     * @return \Generator<DiscoveryResultInterface>
     */
    private function noDiscoveryProvidersResponse(): \Generator
    {
        yield $this->discoveryResultFactory->create(data: [
            'isSuccess' => $this->success,
            'messages' => $this->messages,
        ]);
    }

    /**
     * @param EntityDiscoveryProviderInterface $discoveryProvider
     * @param string[] $apiKeys
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return \Generator<DiscoveryResultInterface>
     */
    private function processEntityUpdates(
        EntityDiscoveryProviderInterface $discoveryProvider,
        array $apiKeys,
        ?array $entityIds = null,
        ?array $entitySubtypes = [],
    ): \Generator {
        $type = $discoveryProvider->getEntityType();
        $entitySubtypesSubmitted = true;
        if (!$entitySubtypes) {
            $entitySubtypesSubmitted = false;
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
                $this->setNewlyIndexableEntitiesToBeIndexable(
                    klevuIndexingEntities: $klevuIndexingEntities,
                    entityType: $type,
                    apiKeys: $apiKeys,
                    entitySubtypes: [$entitySubtype],
                );
                if ($entitySubtypesSubmitted || null !== $entityIds) {
                    $this->setEntitiesToUpdate(
                        type: $type,
                        apiKeys: $apiKeys,
                        entityIds: $entityIds,
                        entitySubtypes: [$entitySubtype],
                    );
                }
                $this->setNewlyNonIndexableEntitiesToNotIndexable(
                    klevuIndexingEntities: $klevuIndexingEntities,
                    entityType: $type,
                    apiKeys: $apiKeys,
                    entitySubtypes: [$entitySubtype],
                );
                yield $this->discoveryResultFactory->create(data: [
                    'isSuccess' => $this->success,
                    'action' => Actions::UPDATE->value,
                    'entityType' => $type,
                    'messages' => $this->messages,
                    'processedIds' => array_map(
                        callback: static fn (IndexingEntityInterface $entity) => $entity->getId(),
                        array: $klevuIndexingEntities,
                    ),
                ]);
                unset($klevuIndexingEntities);
                $this->success = true;
                $this->messages = [];
            }
        }
    }

    /**
     * @param EntityDiscoveryProviderInterface $discoveryProvider
     * @param string[] $apiKeys
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return \Generator<DiscoveryResultInterface>
     */
    private function processEntityDeletions(
        EntityDiscoveryProviderInterface $discoveryProvider,
        array $apiKeys,
        ?array $entityIds,
        ?array $entitySubtypes,
    ): \Generator {
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
                isIndexable: true,
            );
            foreach ($klevuIndexingEntitiesGenerator as $klevuIndexingEntities) {
                $this->setRemovedEntitiesToDelete(
                    klevuIndexingEntities: $klevuIndexingEntities,
                    entityType: $type,
                    apiKeys: $apiKeys,
                    entitySubtypes: [$entitySubtype],
                );
                unset($klevuIndexingEntities);
                yield $this->discoveryResultFactory->create(data: [
                    'isSuccess' => $this->success,
                    'action' => Actions::DELETE->value,
                    'entityType' => $type,
                    'messages' => $this->messages,
                ]);
                $this->success = true;
                $this->messages = [];
            }
        }
    }

    /**
     * @param EntityDiscoveryProviderInterface $discoveryProvider
     * @param string[] $apiKeys
     * @param int[]|null $entityIds
     * @param string[]|null $entitySubtypes
     *
     * @return \Generator<DiscoveryResultInterface>
     */
    private function processEntityAdditions(
        EntityDiscoveryProviderInterface $discoveryProvider,
        array $apiKeys,
        ?array $entityIds,
        ?array $entitySubtypes,
    ): \Generator {
        $type = $discoveryProvider->getEntityType();
        try {
            /** @var \Generator<string, \Generator<MagentoEntityInterface[]>> $magentoEntitiesByApiKey */
            $magentoEntitiesByApiKey = $discoveryProvider->getData(
                apiKeys: $apiKeys,
                entityIds: $entityIds ?? [],
                entitySubtypes: $entitySubtypes,
            );
            foreach ($magentoEntitiesByApiKey as $apiKey => $magentoEntitiesById) {
                foreach ($magentoEntitiesById as $magentoEntities) {
                    $this->addMissingEntitiesToIndexEntitiesTable(
                        type: $type,
                        magentoEntities: $magentoEntities,
                        apiKey: $apiKey,
                        entitySubtypes: $entitySubtypes,
                    );
                    unset($magentoEntities);
                    yield $this->discoveryResultFactory->create(data: [
                        'isSuccess' => $this->success,
                        'action' => Actions::ADD->value,
                        'entityType' => $type,
                        'messages' => $this->messages,
                    ]);
                    $this->success = true;
                    $this->messages = [];
                }
            }
        } catch (LocalizedException $exception) {
            $this->messages[] = $exception->getMessage();
            $this->success = false;
            yield $this->discoveryResultFactory->create(data: [
                'isSuccess' => $this->success,
                'action' => Actions::ADD->value,
                'entityType' => $type,
                'messages' => $this->messages,
            ]);
            $this->success = true;
            $this->messages = [];
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
            $this->logger->warning(
                message: 'Method: {method}, Warning: {message}',
                context: [
                    'method' => __METHOD__,
                    'line' => __LINE__,
                    'message' => sprintf(
                        'Supplied entity types (%s) did not match any providers.',
                        implode(', ', $entityTypes),
                    ),
                ],
            );
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
                magentoEntities: $filteredMagentoEntities,
            );
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
    private function setNewlyIndexableEntitiesToBeIndexable(
        array $klevuIndexingEntities,
        string $entityType,
        array $apiKeys,
        array $entitySubtypes,
    ): void {
        $indexingEntityIds = $this->filterEntitiesToSetToIndexableService->execute(
            klevuIndexingEntities: $klevuIndexingEntities,
            type: $entityType,
            apiKeys: $apiKeys,
            entitySubtypes: $entitySubtypes,
        );

        try {
            $this->setIndexingEntitiesToBeIndexableAction->execute(entityIds: $indexingEntityIds);
        } catch (IndexingEntitySaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }

    /**
     * @param string $type
     * @param string[] $apiKeys
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function setEntitiesToUpdate(
        string $type,
        array $apiKeys,
        ?array $entityIds,
        array $entitySubtypes,
    ): void {
        if (null === $entityIds && !$entitySubtypes) {
            return;
        }
        /** @var \Generator<int[]> $klevuEntityIdsBatch */
        $klevuEntityIdsBatch = $this->filterEntitiesToUpdateService->execute(
            type: $type,
            entityIds: $entityIds,
            apiKeys: $apiKeys,
            entitySubtypes: $entitySubtypes,
        );

        foreach ($klevuEntityIdsBatch as $klevuEntityIds) {
            try {
                $this->setIndexingEntitiesToUpdateAction->execute(entityIds: $klevuEntityIds);
            } catch (IndexingEntitySaveException $exception) {
                $this->success = false;
                $this->messages[] = $exception->getMessage();
            }
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
     * @param IndexingEntityInterface[] $klevuIndexingEntities
     * @param string $entityType
     * @param string[] $apiKeys
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function setNewlyNonIndexableEntitiesToNotIndexable(
        array $klevuIndexingEntities,
        string $entityType,
        array $apiKeys,
        array $entitySubtypes,
    ): void {
        $klevuEntityIds = $this->filterEntitiesToSetToNotIndexableService->execute(
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
     * @param string $entityType
     * @param string[] $apiKeys
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     * @param bool|null $isIndexable
     *
     * @return \Generator<IndexingEntityInterface[]>
     */
    private function getKlevuIndexingEntitiesGenerator(
        string $entityType,
        array $apiKeys,
        array $entityIds,
        array $entitySubtypes,
        ?bool $isIndexable = null,
    ): \Generator {
        $lastRecordId = 0;
        while (true) {
            $indexingEntities = $this->indexingEntityProvider->get(
                entityType: $entityType,
                apiKeys: $apiKeys,
                entityIds: $entityIds,
                isIndexable: $isIndexable,
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
            foreach ($indexingEntities as $indexingEntity) {
                if (method_exists($indexingEntity, 'clearInstance')) {
                    $indexingEntity->clearInstance();
                }
            }
            unset($indexingEntities);
            if (!$lastRecordId) {
                break;
            }
        }
    }
}
