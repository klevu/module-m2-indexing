<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\Indexing\Exception\CouldNotDeleteException;
use Klevu\Indexing\Model\IndexingEntitySearchResultsFactory;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity as IndexingEntityResourceModel;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\CollectionFactory;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterfaceFactory;
use Klevu\IndexingApi\Api\Data\IndexingEntitySearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Psr\Log\LoggerInterface;

class IndexingEntityRepository implements IndexingEntityRepositoryInterface
{
    /**
     * @var IndexingEntityInterfaceFactory
     */
    private readonly IndexingEntityInterfaceFactory $indexingEntityFactory;
    /**
     * @var IndexingEntityResourceModel
     */
    private readonly IndexingEntityResourceModel $indexingEntityResourceModel;
    /**
     * @var IndexingEntitySearchResultsFactory
     */
    private readonly IndexingEntitySearchResultsFactory $searchResultsFactory;
    /**
     * @var CollectionProcessorInterface
     */
    private readonly CollectionProcessorInterface $collectionProcessor;
    /**
     * @var CollectionFactory
     */
    private readonly CollectionFactory $indexingEntityCollectionFactory;
    /**
     * @var ValidatorInterface
     */
    private readonly ValidatorInterface $indexingEntityValidator;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var array<IndexingEntityInterface>
     */
    private array $batchSaveEntities = [];

    /**
     * @param IndexingEntityInterfaceFactory $indexingEntityFactory
     * @param IndexingEntityResourceModel $indexingEntityResourceModel
     * @param IndexingEntitySearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param CollectionFactory $indexingEntityCollectionFactory
     * @param ValidatorInterface $indexingEntityValidator
     * @param LoggerInterface $logger
     */
    public function __construct(
        IndexingEntityInterfaceFactory $indexingEntityFactory,
        IndexingEntityResourceModel $indexingEntityResourceModel,
        IndexingEntitySearchResultsFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor,
        CollectionFactory $indexingEntityCollectionFactory,
        ValidatorInterface $indexingEntityValidator,
        LoggerInterface $logger,
    ) {
        $this->indexingEntityFactory = $indexingEntityFactory;
        $this->indexingEntityResourceModel = $indexingEntityResourceModel;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->indexingEntityCollectionFactory = $indexingEntityCollectionFactory;
        $this->indexingEntityValidator = $indexingEntityValidator;
        $this->logger = $logger;
    }

    /**
     * @return IndexingEntityInterface
     */
    public function create(): IndexingEntityInterface
    {
        return $this->indexingEntityFactory->create();
    }

    /**
     * @param int $indexingEntityId
     *
     * @return IndexingEntityInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $indexingEntityId): IndexingEntityInterface
    {
        /** @var AbstractModel|IndexingEntityInterface $indexingEntity */
        $indexingEntity = $this->create();
        $this->indexingEntityResourceModel->load(
            object: $indexingEntity,
            value: $indexingEntityId,
            field: IndexingEntityResourceModel::ID_FIELD_NAME,
        );
        if (!$indexingEntity->getId()) {
            throw NoSuchEntityException::singleField(
                fieldName: IndexingEntityResourceModel::ID_FIELD_NAME,
                fieldValue: $indexingEntityId,
            );
        }

        return $indexingEntity;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @param bool $collectionSizeRequired
     *
     * @return IndexingEntitySearchResultsInterface
     */
    public function getList(
        SearchCriteriaInterface $searchCriteria,
        bool $collectionSizeRequired = false,
    ): IndexingEntitySearchResultsInterface {
        /** @var IndexingEntitySearchResults $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria(searchCriteria: $searchCriteria);

        $collection = $this->indexingEntityCollectionFactory->create();

        $this->collectionProcessor->process(
            searchCriteria: $searchCriteria,
            collection: $collection,
        );
        $this->logger->debug(
            message: 'Method: {method}, Indexing Entity getList Query: {query}',
            context: [
                'method' => __METHOD__,
                'line' => __LINE__,
                'query' => $collection->getSelect()->__toString(),
            ],
        );

        $searchResults->setItems(
            items: $collection->getItems(), //@phpstan-ignore-line
        );
        $count = $searchCriteria->getPageSize() && $collectionSizeRequired
            ? $collection->getSize()
            : count($collection);
        $searchResults->setTotalCount(count: $count);
        $collection->clear();
        unset($collection);

        return $searchResults;
    }

    /**
     * @param IndexingEntityInterface $indexingEntity
     *
     * @return IndexingEntityInterface
     * @throws CouldNotSaveException
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    public function save(IndexingEntityInterface $indexingEntity): IndexingEntityInterface
    {
        if (!$this->indexingEntityValidator->isValid(value: $indexingEntity)) {
            $messages = $this->indexingEntityValidator->hasMessages()
                ? $this->indexingEntityValidator->getMessages()
                : [];
            throw new CouldNotSaveException(
                phrase: __(
                    'Could not save Indexing Entity: %1',
                    implode('; ', $messages),
                ),
            );
        }

        try {
            /** @var AbstractModel $indexingEntity */
            $this->indexingEntityResourceModel->save(object: $indexingEntity);
        } catch (AlreadyExistsException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                phrase: __('Could not save Indexing Entity: %1', $exception->getMessage()),
                cause: $exception,
                code: $exception->getCode(),
            );
        }

        return $this->getById(
            indexingEntityId: (int)$indexingEntity->getId(),
        );
    }

    /**
     * @param IndexingEntityInterface $indexingEntity
     *
     * @return void
     */
    public function addForBatchSave(IndexingEntityInterface $indexingEntity): void
    {
        if (
            $indexingEntity->getId()
            && !!array_filter(
                array: $this->batchSaveEntities,
                callback: static fn (IndexingEntityInterface $batchedEntity) => (
                    $batchedEntity->getId() === $indexingEntity->getId()
                ),
            )
        ) {
            return;
        }

        $this->batchSaveEntities[] = $indexingEntity;
    }

    /**
     * @param int $minimumBatchSize
     *
     * @return void
     * @throws CouldNotSaveException
     */
    public function saveBatch(int $minimumBatchSize): void
    {
        $entityCount = count($this->batchSaveEntities);
        if ($entityCount < $minimumBatchSize) {
            return;
        }

        $validationErrors = [];
        foreach ($this->batchSaveEntities as $indexingEntity) {
            if ($this->indexingEntityValidator->isValid(value: $indexingEntity)) {
                continue;
            }
            
            $entityIdPrefix = $indexingEntity->getId() 
                ? sprintf('#%d', $indexingEntity->getId()) 
                : '(new)';
            
            $validationErrors[] = sprintf(
                '%s: %s',
                $entityIdPrefix,
                implode('; ', $this->indexingEntityValidator->getMessages()),
            );
        }
        if ($validationErrors) {
            throw new CouldNotSaveException(
                phrase: __(
                    'Could not bulk save Indexing Entities: %1',
                    implode(';; ', $validationErrors),
                ),
            );
        }

        try {
            $this->indexingEntityResourceModel->saveMultiple(
                objects: $this->batchSaveEntities,
            );
            $this->batchSaveEntities = [];
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                phrase: __(
                    'Could not bulk save Indexing Entities: %1',
                    $exception->getMessage(),
                ),
                cause: $exception,
                code: $exception->getCode(),
            );
        }
    }

    //phpcs:disable Security.BadFunctions.FilesystemFunctions.WarnFilesystem
    /**
     * @param IndexingEntityInterface $indexingEntity
     *
     * @return void
     * @throws LocalizedException
     */
    public function delete(IndexingEntityInterface $indexingEntity): void
    {
        //phpcs:enable Security.BadFunctions.FilesystemFunctions.WarnFilesystem
        try {
            /** @var AbstractModel $indexingEntity */
            $this->indexingEntityResourceModel->delete($indexingEntity);
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $message = __('Could not delete Indexing Entity: %1', $exception->getMessage());
            $this->logger->error(
                message: (string)$message,
                context: [
                    'exception' => $exception::class,
                    'method' => __METHOD__,
                    'indexingEntity' => [
                        'entityId' => $indexingEntity->getId(),
                        'targetId' => $indexingEntity->getTargetId(),
                        'targetParentId' => $indexingEntity->getTargetParentId(),
                        'targetEntityType' => $indexingEntity->getTargetEntityType(),
                        'targetEntitySubType' => $indexingEntity->getTargetEntitySubtype(),
                        'apiKey' => $indexingEntity->getApiKey(),
                    ],
                ],
            );
            throw new CouldNotDeleteException(
                phrase: $message,
                cause: $exception,
                code: $exception->getCode(),
            );
        }
    }

    /**
     * @param int $indexingEntityId
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $indexingEntityId): void
    {
        $this->delete(
            indexingEntity: $this->getById($indexingEntityId),
        );
    }

    /**
     * @param string|null $entityType
     * @param string|null $apiKey
     * @param Actions|null $nextAction
     * @param bool|null $isIndexable
     *
     * @return int
     */
    public function count(
        ?string $entityType = null,
        ?string $apiKey = null,
        ?Actions $nextAction = null,
        ?bool $isIndexable = null,
    ): int {
        $connection = $this->indexingEntityResourceModel->getConnection();
        $select = $connection->select();
        $select->from(
            name: $this->indexingEntityResourceModel->getTable(
                tableName: $this->indexingEntityResourceModel::TABLE,
            ),
            cols: ['COUNT(*) as total'],
        );
        if ($apiKey) {
            $select->where(cond: IndexingEntity::API_KEY . ' = ?', value: $apiKey);
        }
        if ($entityType) {
            $select->where(cond: IndexingEntity::TARGET_ENTITY_TYPE . ' = ?', value: $entityType);
        }
        if ($nextAction) {
            $select->where(cond: IndexingEntity::NEXT_ACTION . ' = ?', value: $nextAction->value);
        }
        if (null !== $isIndexable) {
            $select->where(cond: IndexingEntity::IS_INDEXABLE . ' = ?', value: $isIndexable ? '1' : '0');
        }

        return (int)$connection->fetchOne(sql: $select);
    }

    /**
     * @param string|null $apiKey
     *
     * @return string[]
     */
    public function getUniqueEntityTypes(?string $apiKey = null): array
    {
        $connection = $this->indexingEntityResourceModel->getConnection();
        $select = $connection->select();
        $select->distinct();
        $select->from(
            name: $this->indexingEntityResourceModel->getTable(
                tableName: $this->indexingEntityResourceModel::TABLE,
            ),
            cols: [IndexingEntity::TARGET_ENTITY_TYPE],
        );
        if ($apiKey) {
            $select->where(cond: IndexingEntity::API_KEY . ' = ?', value: $apiKey);
        }

        return $connection->fetchCol(sql: $select);
    }
}
