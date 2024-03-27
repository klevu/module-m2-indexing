<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Cron;

use Klevu\IndexingApi\Service\AttributeSyncOrchestratorServiceInterface;
use Psr\Log\LoggerInterface;

class SyncAttributes
{
    /**
     * @var AttributeSyncOrchestratorServiceInterface
     */
    private readonly AttributeSyncOrchestratorServiceInterface $syncOrchestratorService;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param AttributeSyncOrchestratorServiceInterface $syncOrchestratorService
     * @param LoggerInterface $logger
     */
    public function __construct(
        AttributeSyncOrchestratorServiceInterface $syncOrchestratorService,
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
            message: 'Starting sync of attributes.',
        );

        $results = $this->syncOrchestratorService->execute();

        foreach ($results as $apiKey => $actions) {
            foreach ($actions as $action => $attributes) {
                foreach ($attributes as $attributeCode => $syncResult) {
                    $this->logger->info(
                        message: sprintf(
                            'Sync of attributes for apiKey: %s, %s %s: completed %s.',
                            $apiKey,
                            $action,
                            $attributeCode,
                            $syncResult->isSuccess() ? 'successfully' : 'with failures. See logs for more details',
                        ),
                    );
                }
            }
        }
    }
}
