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
     *
     * @return IndexingEntitySearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): IndexingEntitySearchResultsInterface
    {
        /** @var IndexingEntitySearchResults $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria(searchCriteria: $searchCriteria);

        $collection = $this->indexingEntityCollectionFactory->create();

        $this->collectionProcessor->process(
            searchCriteria: $searchCriteria,
            collection: $collection,
        );

        $count = $searchCriteria->getPageSize()
            ? $collection->getSize()
            : count($collection);
        $searchResults->setTotalCount(count: $count);
        $searchResults->setItems(
            items: $collection->getItems(), //@phpstan-ignore-line
        );

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
}
