<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\Indexing\Exception\CouldNotDeleteException;
use Klevu\Indexing\Exception\InvalidSyncHistoryEntityConsolidationRecordException;
use Klevu\Indexing\Logger\Logger as LoggerVirtualType;
use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityConsolidationRecord as ConsolidationRecordResourceModel;
use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityConsolidationRecord\Collection;
use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityConsolidationRecord\CollectionFactory;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterfaceFactory;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationSearchResultsInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityConsolidationRepositoryInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Psr\Log\LoggerInterface;

class SyncHistoryEntityConsolidationRepository implements SyncHistoryEntityConsolidationRepositoryInterface
{
    /**
     * @var SyncHistoryEntityConsolidationRecordInterfaceFactory
     */
    private readonly SyncHistoryEntityConsolidationRecordInterfaceFactory $syncHistoryEntityConsolidationRecordFactory;
    /**
     * @var ConsolidationRecordResourceModel
     */
    private readonly ConsolidationRecordResourceModel $consolidationResourceModel;
    /**
     * @var ValidatorInterface
     */
    private readonly ValidatorInterface $syncHistoryConsolidationRecordValidator;
    /**
     * @var SyncHistoryEntityConsolidationSearchResultsFactory
     */
    private readonly SyncHistoryEntityConsolidationSearchResultsFactory $searchResultsFactory;
    /**
     * @var CollectionFactory
     */
    private readonly CollectionFactory $collectionFactory;
    /**
     * @var CollectionProcessorInterface
     */
    private readonly CollectionProcessorInterface $collectionProcessor;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param SyncHistoryEntityConsolidationRecordInterfaceFactory $syncHistoryEntityConsolidationRecordFactory
     * @param ConsolidationRecordResourceModel $consolidationResourceModel
     * @param ValidatorInterface $syncHistoryConsolidationRecordValidator
     * @param SyncHistoryEntityConsolidationSearchResultsFactory $searchResultsFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        SyncHistoryEntityConsolidationRecordInterfaceFactory $syncHistoryEntityConsolidationRecordFactory,
        ConsolidationRecordResourceModel $consolidationResourceModel,
        ValidatorInterface $syncHistoryConsolidationRecordValidator,
        SyncHistoryEntityConsolidationSearchResultsFactory $searchResultsFactory,
        CollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        ?LoggerInterface $logger = null,
    ) {
        $this->syncHistoryEntityConsolidationRecordFactory = $syncHistoryEntityConsolidationRecordFactory;
        $this->consolidationResourceModel = $consolidationResourceModel;
        $this->syncHistoryConsolidationRecordValidator = $syncHistoryConsolidationRecordValidator;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerVirtualType::class); // @phpstan-ignore-line
    }

    /**
     * @return SyncHistoryEntityConsolidationRecordInterface
     * @throws InvalidSyncHistoryEntityConsolidationRecordException
     */
    public function create(): SyncHistoryEntityConsolidationRecordInterface
    {
        $record = $this->syncHistoryEntityConsolidationRecordFactory->create();
        if (!($record instanceof AbstractModel)) {
            throw new InvalidSyncHistoryEntityConsolidationRecordException(
                phrase: __('Create must return instance of %1', AbstractModel::class),
            );
        }

        return $record;
    }

    /**
     * @param int $entityId
     *
     * @return SyncHistoryEntityConsolidationRecordInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getById(int $entityId): SyncHistoryEntityConsolidationRecordInterface
    {
        /** @var SyncHistoryEntityConsolidationRecordInterface&AbstractModel $record */
        $record = $this->create();
        $this->consolidationResourceModel->load(
            object: $record,
            value: $entityId,
            field: SyncHistoryEntityConsolidationRecord::ENTITY_ID,
        );
        if (!$record->getId()) {
            throw NoSuchEntityException::singleField(
                fieldName: ConsolidationRecordResourceModel::ID_FIELD_NAME,
                fieldValue: $entityId,
            );
        }

        return $record;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return SyncHistoryEntityConsolidationSearchResultsInterface
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria,
    ): SyncHistoryEntityConsolidationSearchResultsInterface {
        /** @var SyncHistoryEntityConsolidationSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria(searchCriteria: $searchCriteria);

        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process(
            searchCriteria: $searchCriteria,
            collection: $collection,
        );
        $this->logger->debug(
            message: 'Method: {method}, Indexing Sync History Consolidation getList Query: {query}',
            context: [
                'method' => __METHOD__,
                'line' => __LINE__,
                'query' => $collection->getSelect()->__toString(),
            ],
        );

        $searchResults->setItems(
            items: $collection->getItems(),
        );
        $count = $searchCriteria->getPageSize()
            ? $collection->getSize()
            : count($collection);
        $searchResults->setTotalCount($count);

        return $searchResults;
    }

    /**
     * @param SyncHistoryEntityConsolidationRecordInterface $syncHistoryEntityConsolidationRecord
     *
     * @return SyncHistoryEntityConsolidationRecordInterface
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function save(
        SyncHistoryEntityConsolidationRecordInterface $syncHistoryEntityConsolidationRecord,
    ): SyncHistoryEntityConsolidationRecordInterface {
        if (!$this->syncHistoryConsolidationRecordValidator->isValid($syncHistoryEntityConsolidationRecord)) {
            throw new CouldNotSaveException(
                phrase: __(
                    'Could not save Consolidated Sync History Record: %1',
                    implode('; ', $this->syncHistoryConsolidationRecordValidator->getMessages()),
                ),
            );
        }
        try {
        /** @var AbstractModel $syncHistoryEntityConsolidationRecord */
            $this->consolidationResourceModel->save($syncHistoryEntityConsolidationRecord);
        } catch (AlreadyExistsException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                phrase: __('Could not save Sync History Consolidation Record: %1', $exception->getMessage()),
                cause: $exception,
                code: $exception->getCode(),
            );
        }

        return $this->getById(
            entityId: (int)$syncHistoryEntityConsolidationRecord->getId(),
        );
    }

    //phpcs:disable Security.BadFunctions.FilesystemFunctions.WarnFilesystem
    /**
     * @param SyncHistoryEntityConsolidationRecordInterface $syncHistoryEntityConsolidationRecord
     *
     * @return void
     * @throws CouldNotDeleteException
     * @throws LocalizedException
     */
    public function delete(SyncHistoryEntityConsolidationRecordInterface $syncHistoryEntityConsolidationRecord): void
    {
        //phpcs:enable Security.BadFunctions.FilesystemFunctions.WarnFilesystem
        try {
            /** @var AbstractModel $syncHistoryEntityConsolidationRecord */
            $this->consolidationResourceModel->delete($syncHistoryEntityConsolidationRecord);
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $message = __('Could not delete Sync History Consolidation Record: %1', $exception->getMessage());

            throw new CouldNotDeleteException(
                phrase: $message,
                cause: $exception,
                code: $exception->getCode(),
            );
        }
    }

    /**
     * @param int $entityId
     *
     * @return void
     * @throws CouldNotDeleteException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $entityId): void
    {
        $this->delete(
            syncHistoryEntityConsolidationRecord: $this->getById(entityId: $entityId),
        );
    }
}
