<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Cron;

use Klevu\IndexingApi\Service\CleanConsolidatedSyncHistoryServiceInterface;
use Psr\Log\LoggerInterface;

class SyncHistoryConsolidationClean
{
    /**
     * @var CleanConsolidatedSyncHistoryServiceInterface
     */
    private readonly CleanConsolidatedSyncHistoryServiceInterface $cleanConsolidatedSyncHistoryService;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param CleanConsolidatedSyncHistoryServiceInterface $cleanConsolidatedSyncHistoryService
     * @param LoggerInterface $logger
     */
    public function __construct(
        CleanConsolidatedSyncHistoryServiceInterface $cleanConsolidatedSyncHistoryService,
        LoggerInterface $logger,
    ) {
        $this->cleanConsolidatedSyncHistoryService = $cleanConsolidatedSyncHistoryService;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info(
            message: 'Starting cleaning consolidation indexing history records.',
        );
        $this->cleanConsolidatedSyncHistoryService->execute();
        $this->logger->info(
            message: 'Cleaning of consolidation indexing history records finished.',
        );
    }
}
