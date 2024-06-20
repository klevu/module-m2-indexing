<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\BatchResponderServiceInterface;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class RecordIndexingEntityHistoryService implements BatchResponderServiceInterface
{
    /**
     * @var SyncHistoryEntityRepositoryInterface
     */
    private readonly SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository,
        LoggerInterface $logger,
    ) {
        $this->syncHistoryEntityRepository = $syncHistoryEntityRepository;
        $this->logger = $logger;
    }

    /**
     * @param ApiPipelineResult $apiPipelineResult
     * @param Actions $action
     * @param array<int, IndexingEntityInterface> $indexingEntities
     * @param string $entityType
     * @param string $apiKey
     *
     * @return void
     */
    public function execute(
        ApiPipelineResult $apiPipelineResult,
        Actions $action,
        array $indexingEntities,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        string $entityType,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        string $apiKey,
    ): void {
        foreach ($indexingEntities as $indexingEntity) {
            $this->saveHistoryRecord(
                historyRecord: $this->createHistoryRecord(
                    apiPipelineResult: $apiPipelineResult,
                    action: $action,
                    indexingEntity: $indexingEntity,
                ),
            );
        }
    }

    /**
     * @param SyncHistoryEntityRecordInterface $historyRecord
     *
     * @return void
     */
    private function saveHistoryRecord(SyncHistoryEntityRecordInterface $historyRecord): void
    {
        try {
            $this->syncHistoryEntityRepository->save(syncHistoryEntityRecord: $historyRecord);
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

    /**
     * @param ApiPipelineResult $apiPipelineResult
     * @param Actions $action
     * @param IndexingEntityInterface $indexingEntity
     *
     * @return SyncHistoryEntityRecordInterface
     */
    private function createHistoryRecord(
        ApiPipelineResult $apiPipelineResult,
        Actions $action,
        IndexingEntityInterface $indexingEntity,
    ): SyncHistoryEntityRecordInterface {
        $historyRecord = $this->syncHistoryEntityRepository->create();
        $historyRecord->setApiKey(apiKey: $indexingEntity->getApiKey());
        $historyRecord->setTargetId(targetId: $indexingEntity->getTargetId());
        $historyRecord->setTargetParentId(targetParentId: $indexingEntity->getTargetParentId());
        $historyRecord->setTargetEntityType(entityType: $indexingEntity->getTargetEntityType());
        $historyRecord->setAction(action: $action);
        $historyRecord->setActionTimestamp(actionTimestamp: date(format: 'Y-m-d H:i:s'));
        $historyRecord->setIsSuccess(success: $apiPipelineResult->success);
        $historyRecord->setMessage(message: implode(separator: ', ', array: $apiPipelineResult->messages));

        return $historyRecord;
    }
}
