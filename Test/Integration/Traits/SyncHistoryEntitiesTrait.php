<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Traits;

use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityRecord\Collection;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

trait SyncHistoryEntitiesTrait
{
    /**
     * @param mixed[] $data
     *
     * @return SyncHistoryEntityRecordInterface
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createSyncHistoryEntity(array $data): SyncHistoryEntityRecordInterface
    {
        /** @var SyncHistoryEntityRepositoryInterface $repository */
        $repository = $this->objectManager->get(SyncHistoryEntityRepositoryInterface::class);
        $record = $repository->create();
        $record->setTargetEntityType(entityType: $data[SyncHistoryEntityRecord::TARGET_ENTITY_TYPE] ?? 'KLEVU_PRODUCT');
        $record->setTargetId(targetId: $data[SyncHistoryEntityRecord::TARGET_ID] ?? 1);
        $record->setTargetParentId(targetParentId: $data[SyncHistoryEntityRecord::TARGET_PARENT_ID] ?? null);
        $record->setApiKey(
            apiKey: $data[SyncHistoryEntityRecord::API_KEY] ?? 'klevu-js-api-key',
        );
        $record->setAction(action: $data[SyncHistoryEntityRecord::ACTION] ?? Actions::ADD);
        $record->setActionTimestamp(
            actionTimestamp: $data[SyncHistoryEntityRecord::ACTION_TIMESTAMP] ?? date(format: 'Y-m-d H:i:s'),
        );
        $record->setIsSuccess(success: $data[SyncHistoryEntityRecord::IS_SUCCESS] ?? true);
        $record->setMessage(message: $data[SyncHistoryEntityRecord::MESSAGE] ?? 'Sync Successful');

        return $repository->save(syncHistoryEntityRecord: $record);
    }

    /**
     * @param string $apiKey
     *
     * @return void
     */
    private function clearSyncHistoryEntities(string $apiKey): void
    {
        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: SyncHistoryEntityRecord::API_KEY,
            value: $apiKey,
        );
        $searchCriteria = $searchCriteriaBuilder->create();

        /** @var SyncHistoryEntityRepositoryInterface $repository */
        $repository = $this->objectManager->get(SyncHistoryEntityRepositoryInterface::class);
        $syncHistoryRecordsToDelete = $repository->getList(searchCriteria: $searchCriteria);
        foreach ($syncHistoryRecordsToDelete->getItems() as $record) {
            try {
                $repository->delete(syncHistoryEntityRecord: $record);
            } catch (LocalizedException) {
                // this is fine, sync history record already deleted
            }
        }
    }

    /**
     * @param string|null $type
     * @param string|null $apiKey
     *
     * @return SyncHistoryEntityRecordInterface[]
     */
    private function getIndexingEntityHistory(?string $type = null, ?string $apiKey = null): array
    {
        $collection = $this->objectManager->create(Collection::class);
        if ($type) {
            $collection->addFieldToFilter(
                field: SyncHistoryEntityRecord::TARGET_ENTITY_TYPE,
                condition: ['eq' => $type],
            );
        }
        if ($apiKey) {
            $collection->addFieldToFilter(
                field: SyncHistoryEntityRecord::API_KEY,
                condition: ['eq' => $apiKey],
            );
        }

        return $collection->getItems();
    }
}
