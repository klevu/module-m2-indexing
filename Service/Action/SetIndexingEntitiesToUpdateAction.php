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
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToUpdateActionInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Psr\Log\LoggerInterface;

class SetIndexingEntitiesToUpdateAction implements SetIndexingEntitiesToUpdateActionInterface
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
     * @var int
     */
    private readonly int $batchSize;

    /**
     * @param IndexingEntityRepositoryInterface $indexingEntityRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param LoggerInterface $logger
     * @param int $batchSize
     */
    public function __construct(
        IndexingEntityRepositoryInterface $indexingEntityRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        LoggerInterface $logger,
        int $batchSize = 2500,
    ) {
        $this->indexingEntityRepository = $indexingEntityRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->logger = $logger;
        $this->batchSize = $batchSize;
    }

    /**
     * @param int[] $entityIds
     *
     * @return void
     * @throws IndexingEntitySaveException
     */
    public function execute(array $entityIds): void
    {
        $indexingEntities = $this->getIndexingEntities($entityIds);
        try {
            $indexingEntityIds = [];
            foreach ($indexingEntities as $indexingEntity) {
                if (
                    !$indexingEntity->getIsIndexable()
                    || $indexingEntity->getNextAction() === Actions::ADD
                ) {
                    continue;
                }

                $indexingEntityIds[] = $indexingEntity->getId();
                $indexingEntity->setNextAction(nextAction: Actions::UPDATE);

                $this->indexingEntityRepository->addForBatchSave(
                    indexingEntity: $indexingEntity,
                );
                $this->indexingEntityRepository->saveBatch(
                    minimumBatchSize: $this->batchSize,
                );
            }

            $this->indexingEntityRepository->saveBatch(
                minimumBatchSize: 1,
            );
        } catch (\Exception $exception) {
            $this->logger->error(
                message: 'Method: {method} - Error: {exception}',
                context: [
                    'method' => __METHOD__,
                    'exception' => $exception->getMessage(),
                    'indexingEntityIds' => $indexingEntityIds,
                ],
            );

            throw new IndexingEntitySaveException(
                phrase: __('Indexing entities failed to save. See log for details.'),
            );
        }

        foreach ($indexingEntities as $indexingEntity) {
            if (method_exists($indexingEntity, 'clearInstance')) {
                $indexingEntity->clearInstance();
            }
        }
        unset($indexingEntities);
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
