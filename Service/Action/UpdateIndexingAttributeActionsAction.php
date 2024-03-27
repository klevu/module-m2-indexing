<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\UpdateIndexingAttributeActionsActionInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class UpdateIndexingAttributeActionsAction implements UpdateIndexingAttributeActionsActionInterface
{
    /**
     * @var IndexingAttributeRepositoryInterface
     */
    private readonly IndexingAttributeRepositoryInterface $indexingAttributeRepository;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var Actions
     */
    private Actions $lastAction;
    /**
     * @var string
     */
    private readonly string $targetAttributeType;

    /**
     * @param IndexingAttributeRepositoryInterface $indexingAttributeRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param LoggerInterface $logger
     * @param string $lastAction
     * @param string $targetAttributeType
     */
    public function __construct(
        IndexingAttributeRepositoryInterface $indexingAttributeRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        LoggerInterface $logger,
        string $lastAction,
        string $targetAttributeType,
    ) {
        $this->indexingAttributeRepository = $indexingAttributeRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->logger = $logger;
        $this->targetAttributeType = $targetAttributeType;
        $this->setLastAction($lastAction);
    }

    /**
     * @param string $apiKey
     * @param int|null $targetId
     * @param string|null $targetCode
     *
     * @return void
     */
    public function execute(string $apiKey, ?int $targetId = null, ?string $targetCode = null): void
    {
        if (null === $targetId && null === $targetCode) {
            throw new \InvalidArgumentException(
                'Either TargetId or TargetCode is required to update and indexing attribute.',
            );
        }
        $searchCriteria = $this->getSearchCriteria($apiKey, $targetId, $targetCode);

        $result = $this->indexingAttributeRepository->getList($searchCriteria);
        $indexingAttributes = $result->getItems();
        foreach ($indexingAttributes as $indexingAttribute) {
            $indexingAttribute->setLastAction(lastAction: $this->lastAction);
            $indexingAttribute->setLastActionTimestamp(lastActionTimestamp: date('Y-m-d H:i:s'));
            if ($indexingAttribute->getNextAction() === $this->lastAction) {
                $indexingAttribute->setNextAction(nextAction: Actions::NO_ACTION);
            }
            if ($this->lastAction === Actions::DELETE) {
                $indexingAttribute->setIsIndexable(isIndexable: false);
            }
            try {
                $this->indexingAttributeRepository->save(indexingAttribute: $indexingAttribute);
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
     * @param string $lastAction
     *
     * @return void
     */
    private function setLastAction(string $lastAction): void
    {
        $this->lastAction = Actions::from($lastAction);
    }

    /**
     * @param string $apiKey
     * @param int|null $targetId
     * @param string|null $targetCode
     *
     * @return SearchCriteria
     */
    private function getSearchCriteria(string $apiKey, ?int $targetId, ?string $targetCode): SearchCriteria
    {
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: IndexingAttribute::API_KEY,
            value: $apiKey,
        );
        if ($targetId) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingAttribute::TARGET_ID,
                value: $targetId,
            );
        }
        if ($targetCode) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingAttribute::TARGET_CODE,
                value: $targetCode,
            );
        }
        $searchCriteriaBuilder->addFilter(
            field: IndexingAttribute::TARGET_ATTRIBUTE_TYPE,
            value: $this->targetAttributeType,
        );
        $searchCriteriaBuilder->addFilter(
            field: IndexingAttribute::IS_INDEXABLE,
            value: true,
        );

        return $searchCriteriaBuilder->create();
    }
}
