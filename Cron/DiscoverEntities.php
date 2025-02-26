<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Cron;

use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Psr\Log\LoggerInterface;

class DiscoverEntities
{
    /**
     * @var EntityDiscoveryOrchestratorServiceInterface
     */
    private EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService,
        LoggerInterface $logger,
    ) {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $success = true;
        $this->logger->info(
            message: 'Starting discovery of entities.',
        );
        $responsesGenerator = $this->discoveryOrchestratorService->execute();

        foreach ($responsesGenerator as $responses) {
            $count = 1;
            foreach ($responses as $response) {
                if (!$response->isSuccess()) {
                    $success = false;
                }
                $this->logger->debug(
                    message: sprintf(
                        'Discover %s to %s Batch %s %s.',
                        $response->getEntityType(),
                        $response->getAction(),
                        $count,
                        $response->isSuccess() ? 'Completed Successfully' : 'Failed'),
                );
                $count++;
            }
        }
        $this->logger->info(
            message: sprintf(
                'Discovery of entities completed %s.',
                $success ? 'successfully' : 'with failures',
            ),
        );
    }
}
