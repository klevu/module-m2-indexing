<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\Indexing\Exception\CouldNotDeleteException;
use Klevu\Indexing\Model\IndexingAttributeSearchResultsFactory;
use Klevu\Indexing\Model\ResourceModel\IndexingAttribute as IndexingAttributeResourceModel;
use Klevu\Indexing\Model\ResourceModel\IndexingAttribute\CollectionFactory as IndexingAttributeCollectionFactory;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterfaceFactory;
use Klevu\IndexingApi\Api\Data\IndexingAttributeSearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Psr\Log\LoggerInterface;

class IndexingAttributeRepository implements IndexingAttributeRepositoryInterface
{
    /**
     * @var IndexingAttributeInterfaceFactory
     */
    private readonly IndexingAttributeInterfaceFactory $indexingEntityFactory;
    /**
     * @var IndexingAttributeResourceModel
     */
    private readonly IndexingAttributeResourceModel $indexingAttributeResourceModel;
    /**
     * @var ValidatorInterface
     */
    private readonly ValidatorInterface $indexingAttributeValidator;
    /**
     * @var IndexingAttributeSearchResultsFactory
     */
    private IndexingAttributeSearchResultsFactory $searchResultsFactory;
    /**
     * @var CollectionProcessorInterface
     */
    private readonly CollectionProcessorInterface $collectionProcessor;
    /**
     * @var IndexingAttributeCollectionFactory
     */
    private readonly IndexingAttributeCollectionFactory $indexingAttributeCollectionFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param IndexingAttributeInterfaceFactory $indexingEntityFactory
     * @param IndexingAttributeResourceModel $indexingAttributeResourceModel
     * @param ValidatorInterface $indexingAttributeValidator
     * @param IndexingAttributeSearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param IndexingAttributeCollectionFactory $indexingAttributeCollectionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        IndexingAttributeInterfaceFactory $indexingEntityFactory,
        IndexingAttributeResourceModel $indexingAttributeResourceModel,
        ValidatorInterface $indexingAttributeValidator,
        IndexingAttributeSearchResultsFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor,
        IndexingAttributeCollectionFactory $indexingAttributeCollectionFactory,
        LoggerInterface $logger,
    ) {
        $this->indexingEntityFactory = $indexingEntityFactory;
        $this->indexingAttributeResourceModel = $indexingAttributeResourceModel;
        $this->indexingAttributeValidator = $indexingAttributeValidator;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->indexingAttributeCollectionFactory = $indexingAttributeCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * @param int $indexingAttributeId
     *
     * @return IndexingAttributeInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $indexingAttributeId): IndexingAttributeInterface
    {
        /** @var AbstractModel|IndexingAttributeInterface $indexingAttribute */
        $indexingAttribute = $this->create();
        $this->indexingAttributeResourceModel->load(
            object: $indexingAttribute,
            value: $indexingAttributeId,
            field: IndexingAttributeResourceModel::ID_FIELD_NAME,
        );
        if (!$indexingAttribute->getId()) {
            throw NoSuchEntityException::singleField(
                fieldName: IndexingAttributeResourceModel::ID_FIELD_NAME,
                fieldValue: $indexingAttributeId,
            );
        }

        return $indexingAttribute;
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return IndexingAttributeSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): IndexingAttributeSearchResultsInterface
    {
        /** @var IndexingAttributeSearchResults $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria(searchCriteria: $searchCriteria);

        $collection = $this->indexingAttributeCollectionFactory->create();

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
     * @return IndexingAttributeInterface
     */
    public function create(): IndexingAttributeInterface
    {
        return $this->indexingEntityFactory->create();
    }

    /**
     * @param IndexingAttributeInterface $indexingAttribute
     *
     * @return IndexingAttributeInterface
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function save(IndexingAttributeInterface $indexingAttribute): IndexingAttributeInterface
    {
        if (!$this->indexingAttributeValidator->isValid(value: $indexingAttribute)) {
            $messages = $this->indexingAttributeValidator->hasMessages()
                ? $this->indexingAttributeValidator->getMessages()
                : [];
            throw new CouldNotSaveException(
                phrase: __(
                    'Could not save Indexing Attribute: %1',
                    implode('; ', $messages),
                ),
            );
        }
        try {
            /** @var AbstractModel $indexingAttribute */
            $this->indexingAttributeResourceModel->save(object: $indexingAttribute);
        } catch (AlreadyExistsException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                phrase: __('Could not save Indexing Attribute: %1', $exception->getMessage()),
                cause: $exception,
                code: $exception->getCode(),
            );
        }

        return $this->getById(
            indexingAttributeId: (int)$indexingAttribute->getId(),
        );
    }

    /**
     * @param IndexingAttributeInterface $indexingAttribute
     *
     * @return void
     * @throws CouldNotDeleteException
     * @throws LocalizedException
     */
    public function delete(IndexingAttributeInterface $indexingAttribute): void
    {
        try {
            /** @var AbstractModel $indexingAttribute */
            $this->indexingAttributeResourceModel->delete($indexingAttribute);
        } catch (LocalizedException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            $message = __('Could not delete Indexing Attribute: %1', $exception->getMessage());
            $this->logger->error(
                message: (string)$message,
                context: [
                    'exception' => $exception::class,
                    'method' => __METHOD__,
                    'indexingEntity' => [
                        'entityId' => $indexingAttribute->getId(),
                        'targetId' => $indexingAttribute->getTargetId(),
                        'targetAttributeType' => $indexingAttribute->getTargetAttributeType(),
                        'apiKey' => $indexingAttribute->getApiKey(),
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
     * @param int $indexingAttributeId
     *
     * @return void
     * @throws CouldNotDeleteException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $indexingAttributeId): void
    {
        $this->delete(
            indexingAttribute: $this->getById(indexingAttributeId: $indexingAttributeId),
        );
    }
}
