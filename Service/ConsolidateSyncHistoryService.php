<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityConsolidationRepositoryInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityRepositoryInterface;
use Klevu\IndexingApi\Service\ConsolidateSyncHistoryServiceInterface;
use Klevu\IndexingApi\Service\Provider\SyncHistoryEntityByDateProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class ConsolidateSyncHistoryService implements ConsolidateSyncHistoryServiceInterface
{
    /**
     * @var SyncHistoryEntityByDateProviderInterface
     */
    private readonly SyncHistoryEntityByDateProviderInterface $syncHistoryProvider;
    /**
     * @var SyncHistoryEntityConsolidationRepositoryInterface
     */
    private readonly SyncHistoryEntityConsolidationRepositoryInterface $syncHistoryEntityConsolidationRepository;
    /**
     * @var SyncHistoryEntityRepositoryInterface
     */
    private readonly SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;

    /**
     * @param SyncHistoryEntityByDateProviderInterface $syncHistoryProvider
     * @param SyncHistoryEntityConsolidationRepositoryInterface $syncHistoryEntityConsolidationRepository
     * @param SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository
     * @param LoggerInterface $logger
     * @param SerializerInterface $serializer
     */
    public function __construct(
        SyncHistoryEntityByDateProviderInterface $syncHistoryProvider,
        SyncHistoryEntityConsolidationRepositoryInterface $syncHistoryEntityConsolidationRepository,
        SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository,
        LoggerInterface $logger,
        SerializerInterface $serializer,
    ) {
        $this->syncHistoryProvider = $syncHistoryProvider;
        $this->syncHistoryEntityConsolidationRepository = $syncHistoryEntityConsolidationRepository;
        $this->syncHistoryEntityRepository = $syncHistoryEntityRepository;
        $this->logger = $logger;
        $this->serializer = $serializer;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        foreach ($this->getSyncHistory() as $entityType => $records) {
            $consolidationData = $this->generateConsolidationData(
                entityType: $entityType,
                records: $records,
            );
            try {
                $this->persistConsolidationData(
                    consolidationData: $consolidationData,
                );
            } catch (\InvalidArgumentException $exception) {
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
     * @return array<string, SyncHistoryEntityRecordInterface[]>
     */
    private function getSyncHistory(): array
    {
        return $this->syncHistoryProvider->get(
            date: date(format: 'Y-m-d'),
        );
    }

    /**
     * @param string $entityType
     * @param SyncHistoryEntityRecordInterface[] $records
     *
     * @return mixed[]
     */
    private function generateConsolidationData(string $entityType, array $records): array
    {
        $data = [];
        foreach ($records as $record) {
            $dateArray = explode(' ', $record->getActionTimestamp());
            $date = array_shift($dateArray);
            $targetId = $record->getTargetId();
            $targetParentId = $record->getTargetParentId();
            $apiKey = $record->getApiKey();
            $key = $date . ' - ' . $apiKey . ' - ' . $targetId . ' - ' . $targetParentId;
            $data[$key] ??= [];
            $data[$key][SyncHistoryEntityConsolidationRecord::TARGET_ID] ??= $targetId;
            $data[$key][SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID] ??= $targetParentId;
            $data[$key][SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE] ??= $entityType;
            $data[$key][SyncHistoryEntityConsolidationRecord::API_KEY] ??= $apiKey;
            $data[$key][SyncHistoryEntityConsolidationRecord::DATE] ??= $date;
            $data[$key][SyncHistoryEntityConsolidationRecord::HISTORY] ??= [];
            $data[$key][SyncHistoryEntityConsolidationRecord::HISTORY][] = $this->generateHistory($record);
            $data[$key]['sync_record_entity_ids'][] = (int)$record->getId();
        }

        return $data;
    }

    /**
     * @param SyncHistoryEntityRecordInterface $record
     *
     * @return mixed[]
     */
    private function generateHistory(SyncHistoryEntityRecordInterface $record): array
    {
        return [
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $record->getActionTimestamp(),
            SyncHistoryEntityRecord::ACTION => $record->getAction()->value,
            SyncHistoryEntityRecord::IS_SUCCESS => $record->getIsSuccess(),
            SyncHistoryEntityRecord::MESSAGE => $record->getMessage(),
        ];
    }

    /**
     * @param mixed[] $consolidationData
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function persistConsolidationData(array $consolidationData): void
    {
        foreach ($consolidationData as $data) {
            $consolidation = $this->syncHistoryEntityConsolidationRepository->create();
            $consolidation->setTargetEntityType(
                entityType: $data[SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE],
            );
            $consolidation->setTargetId(targetId: $data[SyncHistoryEntityConsolidationRecord::TARGET_ID]);
            $consolidation->setTargetParentId(
                targetParentId: $data[SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID] ?? null,
            );
            $consolidation->setApiKey(apiKey: $data[SyncHistoryEntityConsolidationRecord::API_KEY]);
            $consolidation->setDate(date: $data[SyncHistoryEntityConsolidationRecord::DATE]);
            $consolidation->setHistory(
                history: $this->serializer->serialize(
                    data: $data[SyncHistoryEntityConsolidationRecord::HISTORY] ?? [],
                ),
            );
            try {
                $this->syncHistoryEntityConsolidationRepository->save(
                    syncHistoryEntityConsolidationRecord: $consolidation,
                );
                foreach ($data['sync_record_entity_ids'] ?? [] as $entityId) {
                    $this->syncHistoryEntityRepository->deleteById(entityId: $entityId);
                }
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
}
