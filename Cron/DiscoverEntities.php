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
        $this->logger->info(
            message: 'Starting discovery of entities.',
        );

        $success = $this->discoveryOrchestratorService->execute();

        $this->logger->info(
            message: sprintf(
                'Discovery of entities completed %s.',
                $success->isSuccess() ? 'successfully' : 'with failures. See logs for more details',
            ),
        );
    }
}
