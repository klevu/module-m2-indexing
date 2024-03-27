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
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToUpdateActionInterface;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToAddServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToDeleteServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToSetToIndexableServiceInterface;
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
     * @param AddIndexingEntitiesActionInterface $addIndexingEntitiesAction
     * @param SetIndexingEntitiesToDeleteActionInterface $setIndexingEntitiesToDeleteAction
     * @param SetIndexingEntitiesToUpdateActionInterface $setIndexingEntitiesToUpdateAction
     * @param SetIndexingEntitiesToBeIndexableActionInterface $setIndexingEntitiesToBeIndexableAction
     * @param EntityDiscoveryProviderInterface[] $discoveryProviders
     */
    public function __construct(
        LoggerInterface $logger,
        DiscoveryResultFactory $discoveryResultFactory,
        FilterEntitiesToAddServiceInterface $filterEntitiesToAddService,
        FilterEntitiesToDeleteServiceInterface $filterEntitiesToDeleteService,
        FilterEntitiesToUpdateServiceInterface $filterEntitiesToUpdateService,
        FilterEntitiesToSetToIndexableServiceInterface $filterEntitiesToSetToIndexableService,
        AddIndexingEntitiesActionInterface $addIndexingEntitiesAction,
        SetIndexingEntitiesToDeleteActionInterface $setIndexingEntitiesToDeleteAction,
        SetIndexingEntitiesToUpdateActionInterface $setIndexingEntitiesToUpdateAction,
        SetIndexingEntitiesToBeIndexableActionInterface $setIndexingEntitiesToBeIndexableAction,
        array $discoveryProviders = [],
    ) {
        $this->logger = $logger;
        $this->discoveryResultFactory = $discoveryResultFactory;
        $this->filterEntitiesToAddService = $filterEntitiesToAddService;
        $this->filterEntitiesToDeleteService = $filterEntitiesToDeleteService;
        $this->filterEntitiesToUpdateService = $filterEntitiesToUpdateService;
        $this->filterEntitiesToSetToIndexableService = $filterEntitiesToSetToIndexableService;
        $this->addIndexingEntitiesAction = $addIndexingEntitiesAction;
        $this->setIndexingEntitiesToDeleteAction = $setIndexingEntitiesToDeleteAction;
        $this->setIndexingEntitiesToUpdateAction = $setIndexingEntitiesToUpdateAction;
        $this->setIndexingEntitiesToBeIndexableAction = $setIndexingEntitiesToBeIndexableAction;
        array_walk($discoveryProviders, [$this, 'addDiscoveryProvider']);
    }

    /**
     * @param string|null $entityType
     * @param string[]|null $apiKeys
     * @param int[]|null $entityIds
     *
     * @return DiscoveryResultInterface
     */
    public function execute(
        ?string $entityType = null,
        ?array $apiKeys = [],
        ?array $entityIds = [],
    ): DiscoveryResultInterface {
        $discoveryProviders = $this->getDiscoveryProviders($entityType);
        foreach ($discoveryProviders as $discoveryProvider) {
            try {
                $magentoEntitiesByApiKey = $discoveryProvider->getData(apiKeys: $apiKeys, entityIds: $entityIds);
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
                entityIds: $entityIds,
            );
            // set next action to update for any entities that require an update
            $this->setEntitiesToUpdate(
                type: $type,
                entityIds: $entityIds,
                apiKeys: array_keys($magentoEntitiesByApiKey),
            );
            // set entities that are no longer indexable to next action delete
            $this->setNonIndexableEntitiesToDelete(
                type: $type,
                magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
                entityIds: $entityIds,
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
     * @param string|null $entityType
     *
     * @return EntityDiscoveryProviderInterface[]
     */
    private function getDiscoveryProviders(?string $entityType = null): array
    {
        $this->validateDiscoveryProviders();
        if (!$entityType) {
            return $this->discoveryProviders;
        }

        $return = array_filter(
            array: $this->discoveryProviders,
            callback: static fn (EntityDiscoveryProviderInterface $provider) => (
                $provider->getEntityType() === $entityType
            ),
        );
        if (!$return) {
            $this->success = false;
            $this->messages[] = 'Supplied entity type did not match any providers.';
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
     * @param int[] $entityIds
     * @param string[] $apiKeys
     *
     * @return void
     */
    private function setEntitiesToUpdate(
        string $type,
        array $entityIds,
        array $apiKeys,
    ): void {
        if (!$entityIds) {
            return;
        }
        $klevuEntityIds = $this->filterEntitiesToUpdateService->execute(
            type: $type,
            entityIds: $entityIds,
            apiKeys: array_unique($apiKeys),
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
     *
     * @return void
     */
    private function setNonIndexableEntitiesToDelete(
        string $type,
        array $magentoEntitiesByApiKey,
        array $entityIds,
    ): void {
        $klevuEntityIds = $this->filterEntitiesToDeleteService->execute(
            magentoEntitiesByApiKey: $magentoEntitiesByApiKey,
            type: $type,
            entityIds: $entityIds,
        );

        try {
            $this->setIndexingEntitiesToDeleteAction->execute(entityIds: $klevuEntityIds);
        } catch (IndexingEntitySaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }
}
