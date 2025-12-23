<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Cron;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToNotRequireUpdateActionInterface;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesRequireUpdateServiceInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityTargetIdsProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityTargetIdsRequireUpdateProviderInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

class ProcessRequireUpdateEntities
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;
    /**
     * @var IndexingEntityTargetIdsProviderInterface
     */
    private readonly IndexingEntityTargetIdsProviderInterface $indexingEntityTargetIdsProvider;
    /**
     * @var IndexingEntityTargetIdsRequireUpdateProviderInterface
     */
    private readonly IndexingEntityTargetIdsRequireUpdateProviderInterface $indexingEntityTargetIdsRequireUpdateProvider; // phpcs:ignore Generic.Files.LineLength.TooLong
    /**
     * @var FilterEntitiesRequireUpdateServiceInterface
     */
    private readonly FilterEntitiesRequireUpdateServiceInterface $filterEntitiesRequireUpdateService;
    /**
     * @var EntityDiscoveryOrchestratorServiceInterface
     */
    private readonly EntityDiscoveryOrchestratorServiceInterface $entityDiscoveryOrchestratorService;
    /**
     * @var SetIndexingEntitiesToNotRequireUpdateActionInterface
     */
    private readonly SetIndexingEntitiesToNotRequireUpdateActionInterface $setIndexingEntitiesToNotRequireUpdateAction;
    /**
     * @var int
     */
    private readonly int $batchSize;
    /**
     * @var array<string, EntityDiscoveryProviderInterface>
     */
    private array $entityDiscoveryProviders = [];

    /**
     * @param LoggerInterface $logger
     * @param ApiKeysProviderInterface $apiKeysProvider
     * @param IndexingEntityTargetIdsProviderInterface $indexingEntityTargetIdsProvider
     * @param IndexingEntityTargetIdsRequireUpdateProviderInterface $indexingEntityTargetIdsRequireUpdateProvider
     * @param FilterEntitiesRequireUpdateServiceInterface $filterEntitiesRequireUpdateService
     * @param EntityDiscoveryOrchestratorServiceInterface $entityDiscoveryOrchestratorService
     * @param SetIndexingEntitiesToNotRequireUpdateActionInterface $setIndexingEntitiesToNotRequireUpdateAction
     * @param ValidatorInterface $batchSizeValidator
     * @param int $batchSize
     * @param array<string, EntityDiscoveryProviderInterface> $entityDiscoveryProviders
     */
    public function __construct(
        LoggerInterface $logger,
        ApiKeysProviderInterface $apiKeysProvider,
        IndexingEntityTargetIdsProviderInterface $indexingEntityTargetIdsProvider,
        IndexingEntityTargetIdsRequireUpdateProviderInterface $indexingEntityTargetIdsRequireUpdateProvider,
        FilterEntitiesRequireUpdateServiceInterface $filterEntitiesRequireUpdateService,
        EntityDiscoveryOrchestratorServiceInterface $entityDiscoveryOrchestratorService,
        SetIndexingEntitiesToNotRequireUpdateActionInterface $setIndexingEntitiesToNotRequireUpdateAction,
        ValidatorInterface $batchSizeValidator,
        int $batchSize = 100,
        array $entityDiscoveryProviders = [],
    ) {
        $this->logger = $logger;
        $this->apiKeysProvider = $apiKeysProvider;
        $this->indexingEntityTargetIdsProvider = $indexingEntityTargetIdsProvider;
        $this->indexingEntityTargetIdsRequireUpdateProvider = $indexingEntityTargetIdsRequireUpdateProvider;
        $this->filterEntitiesRequireUpdateService = $filterEntitiesRequireUpdateService;
        $this->entityDiscoveryOrchestratorService = $entityDiscoveryOrchestratorService;
        $this->setIndexingEntitiesToNotRequireUpdateAction = $setIndexingEntitiesToNotRequireUpdateAction;
        if (!$batchSizeValidator->isValid($batchSize)) {
            throw new \InvalidArgumentException(
                message: sprintf(
                    'Invalid Batch Size: %s',
                    implode(', ', $batchSizeValidator->getMessages()),
                ),
            );
        }
        $this->batchSize = $batchSize;
        array_walk($entityDiscoveryProviders, [$this, 'addEntityDiscoveryProvider']);
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info(
            message: '[CRON] Starting processing of entities requiring update.',
        );

        $success = true;
        $entityTypes = $this->getEntityTypes();
        $apiKeys = $this->apiKeysProvider->get([]);
        foreach ($apiKeys as $apiKey) {
            foreach ($entityTypes as $entityType) {
                $success = $this->executeForApiKeyAndEntityType(
                    apiKey: $apiKey,
                    entityType: $entityType,
                ) && $success;
            }
        }

        $this->logger->info(
            message: sprintf(
                '[CRON] Processing of entities requiring update completed %s.',
                $success ? 'successfully' : 'with failures',
            ),
        );
    }

    /**
     * @param string $apiKey
     * @param string $entityType
     *
     * @return bool
     */
    private function executeForApiKeyAndEntityType(
        string $apiKey,
        string $entityType,
    ): bool {
        $return = true;

        $entityTargetIds = $this->indexingEntityTargetIdsRequireUpdateProvider->get(
            entityType: $entityType,
            apiKeys: [$apiKey],
        );
        $batchedEntityTargetIds = array_chunk(
            array: $entityTargetIds,
            length: $this->batchSize,
        );

        foreach ($batchedEntityTargetIds as $batchNumber => $entityTargetIdsBatch) {
            $indexingEntityIdsRequiringUpdate = $this->filterEntitiesRequireUpdateService->execute(
                type: $entityType,
                entityIds: $entityTargetIdsBatch,
                apiKeys: [$apiKey],
            );
            $indexingEntityIdsRequiringUpdate = array_merge(
                ...iterator_to_array($indexingEntityIdsRequiringUpdate),
            );

            $entityTargetIdsRequiringUpdate = $this->indexingEntityTargetIdsProvider->getByEntityIds(
                entityIds: $indexingEntityIdsRequiringUpdate,
            );

            if ($entityTargetIdsRequiringUpdate) {
                $responsesGenerator = $this->entityDiscoveryOrchestratorService->execute(
                    entityTypes: [$entityType],
                    apiKeys: [$apiKey],
                    entityIds: $entityTargetIdsRequiringUpdate,
                );

                foreach ($responsesGenerator as $responses) {
                    foreach ($responses as $response) {
                        if ($response->isSuccess()) {
                            $this->logger->debug(
                                message: '[CRON] Successfully processed entities requiring update.',
                                context: [
                                    'method' => __METHOD__,
                                    'apiKey' => $apiKey,
                                    'entityType' => $entityType,
                                    'batchNumber' => $batchNumber,
                                    'entityTargetIds' => $entityTargetIdsBatch,
                                    'messages' => $response->getMessages(),
                                    'action' => $response->getAction(),
                                    'processedEntityIds' => $response->getProcessedIds(),
                                ],
                            );
                        } else {
                            $this->logger->warning(
                                message: '[CRON] Error when processing entities requiring update.',
                                context: [
                                    'method' => __METHOD__,
                                    'apiKey' => $apiKey,
                                    'entityType' => $entityType,
                                    'batchNumber' => $batchNumber,
                                    'entityIds' => $entityTargetIdsBatch,
                                    'messages' => $response->getMessages(),
                                    'action' => $response->getAction(),
                                    'processedEntityIds' => $response->getProcessedIds(),
                                ],
                            );
                            $return = false;
                        }
                    }
                }
            }

            $this->setIndexingEntitiesToNotRequireUpdateAction->execute(
                entityType: $entityType,
                apiKey: $apiKey,
                entityIds: $entityTargetIdsBatch,
            );
        }

        return $return;
    }

    /**
     * @param EntityDiscoveryProviderInterface|null $entityDiscoveryProvider
     * @param string $entityType
     *
     * @return void
     */
    private function addEntityDiscoveryProvider(
        ?EntityDiscoveryProviderInterface $entityDiscoveryProvider,
        string $entityType,
    ): void {
        if ($entityDiscoveryProvider) {
            $this->entityDiscoveryProviders[$entityType] = $entityDiscoveryProvider;
        }
    }

    /**
     * @return string[]
     */
    private function getEntityTypes(): array
    {
        return array_map(
            callback: static fn (EntityDiscoveryProviderInterface $provider): string => $provider->getEntityType(),
            array: $this->entityDiscoveryProviders,
        );
    }
}
