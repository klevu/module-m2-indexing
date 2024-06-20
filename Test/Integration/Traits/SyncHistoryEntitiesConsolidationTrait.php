<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Traits;

use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityConsolidationRecord\Collection;
use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityConsolidationRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

trait SyncHistoryEntitiesConsolidationTrait
{
    /**
     * @param mixed[] $data
     *
     * @return SyncHistoryEntityConsolidationRecordInterface
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws \JsonException
     */
    private function createSyncHistoryConsolidationEntity(
        array $data = [],
    ): SyncHistoryEntityConsolidationRecordInterface {
        /** @var SyncHistoryEntityConsolidationRepositoryInterface $repository */
        $repository = $this->objectManager->get(SyncHistoryEntityConsolidationRepositoryInterface::class);
        $record = $repository->create();
        $record->setTargetEntityType(
            entityType: $data[SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE] ?? 'KLEVU_PRODUCT',
        );
        $record->setTargetId(
            targetId: $data[SyncHistoryEntityConsolidationRecord::TARGET_ID] ?? 1,
        );
        $record->setTargetParentId(
            targetParentId: $data[SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID] ?? null,
        );
        $record->setApiKey(
            apiKey: $data[SyncHistoryEntityConsolidationRecord::API_KEY] ?? 'klevu-js-api-key',
        );
        $record->setHistory(
            history: json_encode(
                value: $data[SyncHistoryEntityConsolidationRecord::HISTORY]
                ?? [
                    [
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::ACTION => Actions::ADD,
                        SyncHistoryEntityRecord::IS_SUCCESS => true,
                        SyncHistoryEntityRecord::MESSAGE => 'Success',
                    ],
                ],
                flags: JSON_THROW_ON_ERROR,
            ),
        );
        $record->setDate(
            date: $data[SyncHistoryEntityConsolidationRecord::DATE] ?? date(format: 'Y-m-d'),
        );

        return $repository->save(syncHistoryEntityConsolidationRecord: $record);
    }

    /**
     * @param string $apiKey
     *
     * @return void
     */
    private function clearSyncHistoryConsolidationEntities(string $apiKey): void
    {
        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: SyncHistoryEntityConsolidationRecord::API_KEY,
            value: $apiKey,
        );
        $searchCriteria = $searchCriteriaBuilder->create();

        /** @var SyncHistoryEntityConsolidationRepositoryInterface $repository */
        $repository = $this->objectManager->get(SyncHistoryEntityConsolidationRepositoryInterface::class);
        $syncHistoryRecordsToDelete = $repository->getList(searchCriteria: $searchCriteria);
        foreach ($syncHistoryRecordsToDelete->getItems() as $record) {
            try {
                $repository->delete(syncHistoryEntityConsolidationRecord: $record);
            } catch (LocalizedException) {
                // this is fine, sync history record already deleted
            }
        }
    }

    /**
     * @param string|null $type
     * @param string|null $apiKey
     *
     * @return SyncHistoryEntityConsolidationRecordInterface[]
     */
    private function getIndexingEntityHistoryConsolidation(?string $type = null, ?string $apiKey = null): array
    {
        $collection = $this->objectManager->create(Collection::class);
        if ($type) {
            $collection->addFieldToFilter(
                field: SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE,
                condition: ['eq' => $type],
            );
        }
        if ($apiKey) {
            $collection->addFieldToFilter(
                field: SyncHistoryEntityConsolidationRecord::API_KEY,
                condition: ['eq' => $apiKey],
            );
        }

        return $collection->getItems();
    }
}
