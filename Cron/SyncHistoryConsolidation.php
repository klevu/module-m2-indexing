<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Cron;

use Klevu\IndexingApi\Service\ConsolidateSyncHistoryServiceInterface;
use Psr\Log\LoggerInterface;

class SyncHistoryConsolidation
{
    /**
     * @var ConsolidateSyncHistoryServiceInterface
     */
    private readonly ConsolidateSyncHistoryServiceInterface $consolidateSyncHistoryService;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param ConsolidateSyncHistoryServiceInterface $consolidateSyncHistoryService
     * @param LoggerInterface $logger
     */
    public function __construct(
        ConsolidateSyncHistoryServiceInterface $consolidateSyncHistoryService,
        LoggerInterface $logger,
    ) {
        $this->consolidateSyncHistoryService = $consolidateSyncHistoryService;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info(
            message: 'Starting consolidation of indexing history.',
        );
        $this->consolidateSyncHistoryService->execute();
        $this->logger->info(
            message: 'Consolidation of indexing history finished.',
        );
    }
}
