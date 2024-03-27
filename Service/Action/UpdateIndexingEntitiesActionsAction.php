<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\UpdateIndexingEntitiesActionsActionInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class UpdateIndexingEntitiesActionsAction implements UpdateIndexingEntitiesActionsActionInterface
{
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;
    /**
     * @var IndexingEntityRepositoryInterface
     */
    private readonly IndexingEntityRepositoryInterface $indexingEntityRepository;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param IndexingEntityRepositoryInterface $indexingEntityRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        IndexingEntityRepositoryInterface $indexingEntityRepository,
        LoggerInterface $logger,
    ) {
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->indexingEntityRepository = $indexingEntityRepository;
        $this->logger = $logger;
    }

    /**
     * @param int[] $entityIds
     * @param Actions $lastAction
     *
     * @return void
     * @throws \ValueError
     */
    public function execute(array $entityIds, Actions $lastAction): void
    {
        $result = $this->indexingEntityRepository->getList(
            searchCriteria: $this->getSearchCriteria($entityIds),
        );
        foreach ($result->getItems() as $indexingEntity) {
            $this->updateIndexingEntity(indexingEntity: $indexingEntity, lastAction: $lastAction);
        }
    }

    /**
     * @param int[] $entityIds
     *
     * @return SearchCriteria
     */
    private function getSearchCriteria(array $entityIds): SearchCriteria
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: IndexingEntity::ENTITY_ID,
            value: $entityIds,
            conditionType: 'in',
        );
        $searchCriteriaBuilder->addFilter(
            field: IndexingEntity::IS_INDEXABLE,
            value: true,
        );

        return $searchCriteriaBuilder->create();
    }

    /**
     * @param IndexingEntityInterface $indexingEntity
     * @param Actions $lastAction
     *
     * @return void
     */
    private function updateIndexingEntity(IndexingEntityInterface $indexingEntity, Actions $lastAction): void
    {
        $indexingEntity->setLastAction(lastAction: $lastAction);
        $indexingEntity->setLastActionTimestamp(lastActionTimestamp: date('Y-m-d H:i:s'));
        if ($indexingEntity->getNextAction() === $lastAction) {
            $indexingEntity->setNextAction(nextAction: Actions::NO_ACTION);
        }
        if ($lastAction === Actions::DELETE) {
            $indexingEntity->setIsIndexable(isIndexable: false);
        }
        try {
            $this->indexingEntityRepository->save(indexingEntity: $indexingEntity);
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
