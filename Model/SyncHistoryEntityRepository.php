<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\Indexing\Exception\CouldNotDeleteException;
use Klevu\Indexing\Exception\InvalidSyncHistoryEntityRecordException;
use Klevu\Indexing\Logger\Logger as LoggerVirtualType;
use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityRecord as SyncHistoryEntityRecordResourceModel;
use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityRecord\Collection;
use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityRecord\CollectionFactory;
use Klevu\Indexing\Model\SyncHistoryEntitySearchResultsFactory;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterfaceFactory;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntitySearchResultsInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityRepositoryInterface;
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

class SyncHistoryEntityRepository implements SyncHistoryEntityRepositoryInterface
{
    /**
     * @var SyncHistoryEntityRecordInterfaceFactory
     */
    private readonly SyncHistoryEntityRecordInterfaceFactory $syncHistoryEntityRecordFactory;
    /**
     * @var SyncHistoryEntityRecordResourceModel
     */
    private readonly SyncHistoryEntityRecordResourceModel $syncHistoryEntityRecordResourceModel;
    /**
     * @var ValidatorInterface
     */
    private readonly ValidatorInterface $syncHistoryRecordValidator;
    /**
     * @var SyncHistoryEntitySearchResultsFactory
     */
    private readonly SyncHistoryEntitySearchResultsFactory $searchResultsFactory;
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
     * @param SyncHistoryEntityRecordInterfaceFactory $syncHistoryEntityRecordFactory
     * @param SyncHistoryEntityRecordResourceModel $syncHistoryEntityRecordResourceModel
     * @param ValidatorInterface $syncHistoryRecordValidator
     * @param SyncHistoryEntitySearchResultsFactory $searchResultsFactory
     * @param CollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        SyncHistoryEntityRecordInterfaceFactory $syncHistoryEntityRecordFactory,
        SyncHistoryEntityRecordResourceModel $syncHistoryEntityRecordResourceModel,
        ValidatorInterface $syncHistoryRecordValidator,
        SyncHistoryEntitySearchResultsFactory $searchResultsFactory,
        CollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        ?LoggerInterface $logger = null,
    ) {
        $this->syncHistoryEntityRecordFactory = $syncHistoryEntityRecordFactory;
        $this->syncHistoryEntityRecordResourceModel = $syncHistoryEntityRecordResourceModel;
        $this->syncHistoryRecordValidator = $syncHistoryRecordValidator;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerVirtualType::class); // @phpstan-ignore-line
    }

    /**
     * @return SyncHistoryEntityRecordInterface
     * @throws InvalidSyncHistoryEntityRecordException
     */
    public function create(): SyncHistoryEntityRecordInterface
    {
        $record = $this->syncHistoryEntityRecordFactory->create();
        if (!($record instanceof AbstractModel)) {
            throw new InvalidSyncHistoryEntityRecordException(
                phrase: __('Create must return instance of %1', AbstractModel::class),
            );
        }

        return $record;
    }

    /**
     * @param int $entityId
     *
     * @return SyncHistoryEntityRecordInterface
     * @throws NoSuchEntityException
     * @throws InvalidSyncHistoryEntityRecordException
     */
    public function getById(int $entityId): SyncHistoryEntityRecordInterface
    {
        /** @var SyncHistoryEntityRecordInterface&AbstractModel $record */
        $record = $this->create();
        $this->syncHistoryEntityRecordResourceModel->load(
            object: $record,
            value: $entityId,
            field: SyncHistoryEntityRecord::ENTITY_ID,
        );
        if (!$record->getId()) {
            throw NoSuchEntityException::singleField(
                fieldName: SyncHistoryEntityRecordResourceModel::ID_FIELD_NAME,
                fieldValue: $entityId,
            );
        }

        return $record;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return SyncHistoryEntitySearchResultsInterface
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria,
    ): SyncHistoryEntitySearchResultsInterface {
        /** @var SyncHistoryEntitySearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria(searchCriteria: $searchCriteria);

        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process(
            searchCriteria: $searchCriteria,
            collection: $collection,
        );
        $this->logger->debug(
            message: 'Method: {method}, Indexing Sync History getList Query: {query}',
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
     * @param SyncHistoryEntityRecordInterface $syncHistoryEntityRecord
     *
     * @return SyncHistoryEntityRecordInterface
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws InvalidSyncHistoryEntityRecordException
     */
    public function save(SyncHistoryEntityRecordInterface $syncHistoryEntityRecord): SyncHistoryEntityRecordInterface
    {
        if (!$this->syncHistoryRecordValidator->isValid($syncHistoryEntityRecord)) {
            throw new CouldNotSaveException(
                phrase: __(
                    'Could not save Sync History Record: %1',
                    implode('; ', $this->syncHistoryRecordValidator->getMessages()),
                ),
            );
        }
        try {
            /** @var AbstractModel $syncHistoryEntityRecord */
            $this->syncHistoryEntityRecordResourceModel->save(object: $syncHistoryEntityRecord);
        } catch (AlreadyExistsException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                phrase: __('Could not save Sync History Record: %1', $exception->getMessage()),
                cause: $exception,
                code: $exception->getCode(),
            );
        }

        return $this->getById(
            entityId: (int)$syncHistoryEntityRecord->getId(),
        );
    }

    //phpcs:disable Security.BadFunctions.FilesystemFunctions.WarnFilesystem
    /**
     * @param SyncHistoryEntityRecordInterface $syncHistoryEntityRecord
     *
     * @return void
     * @throws CouldNotDeleteException
     * @throws LocalizedException
     */
    public function delete(SyncHistoryEntityRecordInterface $syncHistoryEntityRecord): void
    {
        //phpcs:enable Security.BadFunctions.FilesystemFunctions.WarnFilesystem
        try {
            /** @var AbstractModel $syncHistoryEntityRecord */
            $this->syncHistoryEntityRecordResourceModel->delete($syncHistoryEntityRecord);
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $message = __('Could not delete Sync History Record: %1', $exception->getMessage());

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
            syncHistoryEntityRecord: $this->getById(entityId: $entityId),
        );
    }
}
