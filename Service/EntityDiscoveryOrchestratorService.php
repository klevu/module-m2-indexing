<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Exception\IndexingEntitySaveException;
use Klevu\Indexing\Model\DiscoveryResultFactory;
use Klevu\IndexingApi\Api\Data\DiscoveryResultInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Service\Action\AddIndexingEntitiesActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToBeIndexableActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToDeleteActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToNotBeIndexableActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToUpdateActionInterface;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToAddServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToDeleteServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToSetToIndexableServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToSetToNotIndexableServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToUpdateServiceInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
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
     * @var SetIndexingEntitiesToBeIndexableActionInterface
     */
    private readonly SetIndexingEntitiesToBeIndexableActionInterface $setIndexingEntitiesToBeIndexableAction;
    /**
     * @var SetIndexingEntitiesToNotBeIndexableActionInterface
     */
    private readonly SetIndexingEntitiesToNotBeIndexableActionInterface $setIndexingEntitiesToNotBeIndexableAction;
    /**
     * @var EntityDiscoveryProviderInterface[]
     */
    private array $discoveryProviders = [];
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
     * @param SetIndexingEntitiesToNotBeIndexableActionInterface $setIndexingEntitiesToNotBeIndexableAction
     * @param EntityDiscoveryProviderInterface[] $discoveryProviders
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
        SetIndexingEntitiesToNotBeIndexableActionInterface $setIndexingEntitiesToNotBeIndexableAction,
        array $discoveryProviders = [],
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
        $this->setIndexingEntitiesToNotBeIndexableAction = $setIndexingEntitiesToNotBeIndexableAction;
        array_walk($discoveryProviders, [$this, 'addDiscoveryProvider']);
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
            try {
                $magentoEntitiesByApiKey = $discoveryProvider->getData(
                    apiKeys: $apiKeys,
                    entityIds: $entityIds ?? [],
                    entitySubtypes: $entitySubtypes,
                );
            } catch (LocalizedException $exception) {
                $this->messages[] = $exception->getMessage();
                $this->success = false;
                continue;
            }
            $type = $discoveryProvider->getEntityType();
            // add any missing entities to klevu_indexing_entity
            $this->addMissingEntitiesToIndexEntitiesTable(
                type: $type,
                magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
            );
            // set as indexable any indexable entities that were set to not indexable
            // also sets next action to add
            $this->setNonIndexableEntitiesToBeIndexable(
                type: $type,
                magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
                entityIds: $entityIds ?? [],
            );
            // set next action to update for any entities that require an update
            $this->setEntitiesToUpdate(
                type: $type,
                magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
                entitySubtypes: $entitySubtypes,
                entityIds: $entityIds,
            );
            // set entities that are no longer indexable to and have been indexed to next action delete
            $this->setNonIndexableEntitiesToDelete(
                type: $type,
                magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
                entityIds: $entityIds ?? [],
                entitySubtypes: $entitySubtypes,
            );
            // set entities that are no longer indexable and never synced to not indexable, next action none
            $this->setNonIndexableNonIndexedEntitiesToNotIndexable(
                type: $type,
                magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
                entityIds: $entityIds ?? [],
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
     *
     * @return void
     */
    private function addDiscoveryProvider(EntityDiscoveryProviderInterface $discoveryProvider): void
    {
        $this->discoveryProviders[] = $discoveryProvider;
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
     * @param MagentoEntityInterface[][] $magentoEntitiesByApiKey
     *
     * @return void
     */
    private function addMissingEntitiesToIndexEntitiesTable(
        string $type,
        array $magentoEntitiesByApiKey,
    ): void {
        $filteredMagentoEntitiesByApiKey = $this->filterEntitiesToAddService->execute(
            magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
            type: $type,
        );
        foreach ($filteredMagentoEntitiesByApiKey as $magentoEntities) {
            try {
                $this->addIndexingEntitiesAction->execute(
                    type: $type,
                    magentoEntities: $magentoEntities,
                );
            } catch (IndexingEntitySaveException $exception) {
                $this->success = false;
                $this->messages[] = $exception->getMessage();
            }
        }
    }

    /**
     * @param string $type
     * @param MagentoEntityInterface[][] $magentoEntitiesByApiKey
     * @param int[]|null $entityIds
     *
     * @return void
     */
    private function setNonIndexableEntitiesToBeIndexable(
        string $type,
        array $magentoEntitiesByApiKey,
        ?array $entityIds = [],
    ): void {
        $indexingEntityIds = $this->filterEntitiesToSetToIndexableService->execute(
            magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
            type: $type,
            entityIds: $entityIds,
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
     * @param MagentoEntityInterface[][] $magentoEntitiesByApiKey
     * @param string[] $entitySubtypes
     * @param int[] $entityIds
     *
     * @return void
     */
    private function setEntitiesToUpdate(
        string $type,
        array $magentoEntitiesByApiKey,
        array $entitySubtypes,
        ?array $entityIds = null,
    ): void {
        if (null === $entityIds && !$entitySubtypes) {
            return;
        }
        $klevuEntityIds = $this->filterEntitiesToUpdateService->execute(
            type: $type,
            entityIds: $entityIds,
            apiKeys: array_unique(array_keys($magentoEntitiesByApiKey)),
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
     * @param string $type
     * @param MagentoEntityInterface[][] $magentoEntitiesByApiKey
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function setNonIndexableEntitiesToDelete(
        string $type,
        array $magentoEntitiesByApiKey,
        array $entityIds,
        array $entitySubtypes,
    ): void {
        $klevuEntityIds = $this->filterEntitiesToDeleteService->execute(
            magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
            type: $type,
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
     * @param string $type
     * @param MagentoEntityInterface[][] $magentoEntitiesByApiKey
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return void
     */
    private function setNonIndexableNonIndexedEntitiesToNotIndexable(
        string $type,
        array $magentoEntitiesByApiKey,
        array $entityIds,
        array $entitySubtypes,
    ): void {
        $klevuEntityIds = $this->filterEntitiesToSetToNotIndexableService->execute(
            magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
            type: $type,
            entityIds: $entityIds,
            entitySubtypes: $entitySubtypes,
        );

        try {
            $this->setIndexingEntitiesToNotBeIndexableAction->execute(entityIds: $klevuEntityIds);
        } catch (IndexingEntitySaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }
}
