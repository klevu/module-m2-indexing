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
            searchCriteria: $this->getSearchCriteria(entityIds: $entityIds, actionTaken: $lastAction),
        );
        foreach ($result->getItems() as $indexingEntity) {
            $this->updateIndexingEntity(indexingEntity: $indexingEntity, actionTaken: $lastAction);
        }
    }

    /**
     * @param int[] $entityIds
     *
     * @return SearchCriteria
     */
    private function getSearchCriteria(array $entityIds, Actions $actionTaken): SearchCriteria
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
        if ($actionTaken === Actions::DELETE) {
            // see KS-23999
            // When a product type changes in the admin (e.g. simple becomes configurable via Create Configurations)
            // we will have 2 entities with same id in klevu_indexing_entity db table,
            // one to be added (configurable) and one to be deleted (simple)
            // if the action taken was Delete then we exclude products that have never been indexed
            // i.e. are waiting to be added
            $searchCriteriaBuilder->addFilter(
                field: IndexingEntity::LAST_ACTION,
                value: Actions::NO_ACTION->value,
                conditionType: 'neq',
            );
        }
        if ($actionTaken === Actions::ADD) {
            // see KS-23999
            // When a product type changes in the admin (e.g. simple becomes configurable via Create Configurations)
            // we will have 2 entities with same id in klevu_indexing_entity db table,
            // one to be added (configurable) and one to be deleted (simple)
            // if the action taken was Add then we only update products that have next action add,
            // any changes to a product waiting to be added will not change the next action from add
            // (unless it became un-indexable, but then it wouldn't have been included in this action)
            $searchCriteriaBuilder->addFilter(
                field: IndexingEntity::NEXT_ACTION,
                value: Actions::ADD->value,
            );
        }

        return $searchCriteriaBuilder->create();
    }

    /**
     * @param IndexingEntityInterface $indexingEntity
     * @param Actions $actionTaken
     *
     * @return void
     */
    private function updateIndexingEntity(IndexingEntityInterface $indexingEntity, Actions $actionTaken): void
    {
        $indexingEntity->setLastAction(lastAction: $actionTaken);
        $indexingEntity->setLastActionTimestamp(lastActionTimestamp: date('Y-m-d H:i:s'));
        if ($indexingEntity->getNextAction() === $actionTaken) {
            $indexingEntity->setNextAction(nextAction: Actions::NO_ACTION);
        }
        if ($actionTaken === Actions::DELETE) {
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
