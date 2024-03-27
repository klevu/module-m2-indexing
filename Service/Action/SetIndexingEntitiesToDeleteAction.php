<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\Indexing\Exception\IndexingEntitySaveException;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToDeleteActionInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Psr\Log\LoggerInterface;

class SetIndexingEntitiesToDeleteAction implements SetIndexingEntitiesToDeleteActionInterface
{
    /**
     * @var IndexingEntityRepositoryInterface
     */
    private readonly IndexingEntityRepositoryInterface $indexingEntityRepository;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param IndexingEntityRepositoryInterface $indexingEntityRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        IndexingEntityRepositoryInterface $indexingEntityRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        LoggerInterface $logger,
    ) {
        $this->indexingEntityRepository = $indexingEntityRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->logger = $logger;
    }

    /**
     * @param int[] $entityIds
     *
     * @return void
     * @throws IndexingEntitySaveException
     */
    public function execute(array $entityIds): void
    {
        $failed = [];
        $indexingEntities = $this->getIndexingEntities($entityIds);
        foreach ($indexingEntities as $indexingEntity) {
            try {
                if ($indexingEntity->getLastAction() === ACTIONS::NO_ACTION) {
                    $indexingEntity->setNextAction(nextAction: Actions::NO_ACTION);
                    $indexingEntity->setIsIndexable(false);
                } else {
                    $indexingEntity->setNextAction(nextAction: Actions::DELETE);
                }
                $this->indexingEntityRepository->save(indexingEntity: $indexingEntity);
            } catch (\Exception $exception) {
                $failed[] = $indexingEntity->getId();
                $this->logger->error(
                    message: 'Method: {method} - Entity ID: {entity_id} - Error: {exception}',
                    context: [
                        'method' => __METHOD__,
                        'entity_id' => $indexingEntity->getId(),
                        'exception' => $exception->getMessage(),
                    ],
                );
            }
        }
        if ($failed) {
            throw new IndexingEntitySaveException(
                phrase: __(
                    'Indexing entities (%1) failed to save. See log for details.',
                    implode(', ', $failed),
                ),
            );
        }
    }

    /**
     * @param int[] $entityIds
     *
     * @return IndexingEntityInterface[]
     */
    private function getIndexingEntities(array $entityIds): array
    {
        $indexingEntities = [];
        if ($entityIds) {
            $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
            $searchCriteriaBuilder->addFilter(
                field: IndexingEntity::ENTITY_ID,
                value: $entityIds,
                conditionType: 'in',
            );
            $searchCriteria = $searchCriteriaBuilder->create();
            $searchResult = $this->indexingEntityRepository->getList(searchCriteria: $searchCriteria);
            $indexingEntities = $searchResult->getItems();
        }

        return $indexingEntities;
    }
}