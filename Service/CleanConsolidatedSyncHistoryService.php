<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Constants;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityConsolidationRepositoryInterface;
use Klevu\IndexingApi\Service\CleanConsolidatedSyncHistoryServiceInterface;
use Klevu\IndexingApi\Service\Provider\SyncHistoryEntityConsolidatedByDateProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class CleanConsolidatedSyncHistoryService implements CleanConsolidatedSyncHistoryServiceInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var SyncHistoryEntityConsolidatedByDateProviderInterface
     */
    private readonly SyncHistoryEntityConsolidatedByDateProviderInterface $syncHistoryEntityConsolidationByDateProvider;
    /**
     * @var SyncHistoryEntityConsolidationRepositoryInterface
     */
    private readonly SyncHistoryEntityConsolidationRepositoryInterface $syncHistoryEntityConsolidationRepository;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param SyncHistoryEntityConsolidatedByDateProviderInterface $syncHistoryEntityConsolidationByDateProvider
     * @param SyncHistoryEntityConsolidationRepositoryInterface $syncHistoryEntityConsolidationRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        SyncHistoryEntityConsolidatedByDateProviderInterface $syncHistoryEntityConsolidationByDateProvider,
        SyncHistoryEntityConsolidationRepositoryInterface $syncHistoryEntityConsolidationRepository,
        LoggerInterface $logger,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->syncHistoryEntityConsolidationByDateProvider = $syncHistoryEntityConsolidationByDateProvider;
        $this->syncHistoryEntityConsolidationRepository = $syncHistoryEntityConsolidationRepository;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        foreach ($this->getRecordsToDelete() as $record) {
            try {
                $this->syncHistoryEntityConsolidationRepository->delete(
                    syncHistoryEntityConsolidationRecord: $record,
                );
            } catch (LocalizedException $exception) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }
    }

    /**
     * @return SyncHistoryEntityConsolidationRecordInterface[]
     */
    private function getRecordsToDelete(): array
    {
        $days = $this->scopeConfig->getValue(
            Constants::XML_PATH_INDEXING_HISTORY_REMOVAL_AFTER_DAYS,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            null,
        );
        $date = date(
            format:'Y-m-d',
            timestamp: time() - ($days * 24 * 3600),
        );

        return $this->syncHistoryEntityConsolidationByDateProvider->get(date: $date);
    }
}
