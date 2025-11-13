<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\Indexing\Exception\IndexingEntitySaveException;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\AddIndexingEntitiesActionInterface;
use Psr\Log\LoggerInterface;

class AddIndexingEntitiesAction implements AddIndexingEntitiesActionInterface
{
    /**
     * @var IndexingEntityRepositoryInterface
     */
    private readonly IndexingEntityRepositoryInterface $indexingEntityRepository;
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
     * @param LoggerInterface $logger
     * @param int $batchSize
     */
    public function __construct(
        IndexingEntityRepositoryInterface $indexingEntityRepository,
        LoggerInterface $logger,
        int $batchSize = 2500,
    ) {
        $this->indexingEntityRepository = $indexingEntityRepository;
        $this->logger = $logger;
        $this->batchSize = $batchSize;
    }

    /**
     * @param string $type
     * @param \Generator<MagentoEntityInterface> $magentoEntities
     *
     * @return void
     * @throws IndexingEntitySaveException
     */
    public function execute(string $type, \Generator $magentoEntities): void
    {
        try {
            $magentoEntityIds = [];
            foreach ($magentoEntities as $magentoEntity) {
                $magentoEntityIds[] = $magentoEntity->getEntityId();

                $indexingEntity = $this->createIndexingEntity(type: $type, magentoEntity: $magentoEntity);
                $this->indexingEntityRepository->addForBatchSave(indexingEntity: $indexingEntity);
                $this->indexingEntityRepository->saveBatch(
                    minimumBatchSize: $this->batchSize,
                );
                unset($indexingEntity);
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
                    'magentoEntityIds' => $magentoEntityIds,
                ],
            );

            throw new IndexingEntitySaveException(
                phrase: __('Failed to save Indexing Entities for Magento Entities. See log for details.'),
            );
        }
    }

    /**
     * @param string $type
     * @param MagentoEntityInterface $magentoEntity
     *
     * @return IndexingEntityInterface
     */
    private function createIndexingEntity(
        string $type,
        MagentoEntityInterface $magentoEntity,
    ): IndexingEntityInterface {
        $isIndexable = $magentoEntity->isIndexable();
        $indexingEntity = $this->indexingEntityRepository->create();
        $indexingEntity->setTargetEntityType(entityType: $type);
        $indexingEntity->setTargetEntitySubtype(entitySubtype: $magentoEntity->getEntitySubtype());
        $indexingEntity->setTargetId(targetId: $magentoEntity->getEntityId());
        $indexingEntity->setTargetParentId(targetParentId: $magentoEntity->getEntityParentId());
        $indexingEntity->setApiKey(apiKey: $magentoEntity->getApiKey());
        $indexingEntity->setIsIndexable(isIndexable: $isIndexable);
        $indexingEntity->setNextAction(
            nextAction: $isIndexable ? Actions::ADD : Actions::NO_ACTION,
        );
        $indexingEntity->setLastAction(lastAction: Actions::NO_ACTION);

        return $indexingEntity;
    }
}
