<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Cron;

use Klevu\IndexingApi\Service\EntitySyncOrchestratorServiceInterface;
use Psr\Log\LoggerInterface;

class SyncEntities
{
    /**
     * @var EntitySyncOrchestratorServiceInterface
     */
    private readonly EntitySyncOrchestratorServiceInterface $syncOrchestratorService;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param EntitySyncOrchestratorServiceInterface $syncOrchestratorService
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntitySyncOrchestratorServiceInterface $syncOrchestratorService,
        LoggerInterface $logger,
    ) {
        $this->syncOrchestratorService = $syncOrchestratorService;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info(
            message: 'Starting sync of entities.',
        );

        $results = $this->syncOrchestratorService->execute(
            via: 'Cron: ' . self::class,
        );

        foreach ($results as $apiKey => $syncResult) {
            $pipelineResult = $syncResult->getPipelineResult();
            foreach ($pipelineResult as $action => $apiPipelineResults) {
                foreach ($apiPipelineResults as $batch => $apiPipelineResult) {
                    $this->logger->info(
                        message: sprintf(
                            'Sync of entities for apiKey: %s, %s batch %s: completed %s.',
                            $apiKey,
                            $action,
                            $batch,
                            $apiPipelineResult->success
                                ? 'successfully'
                                : 'with failures. See logs for more details',
                        ),
                    );
                }
            }
        }
    }
}
