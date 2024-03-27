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
     * @param IndexingEntityRepositoryInterface $indexingEntityRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        IndexingEntityRepositoryInterface $indexingEntityRepository,
        LoggerInterface $logger,
    ) {
        $this->indexingEntityRepository = $indexingEntityRepository;
        $this->logger = $logger;
    }

    /**
     * @param string $type
     * @param MagentoEntityInterface[] $magentoEntities
     *
     * @return void
     * @throws IndexingEntitySaveException
     */
    public function execute(string $type, array $magentoEntities): void
    {
        $failed = [];

        foreach ($magentoEntities as $magentoEntity) {
            try {
                $indexingEntity = $this->createIndexingEntity(type: $type, magentoEntity: $magentoEntity);
                $this->indexingEntityRepository->save(indexingEntity: $indexingEntity);
            } catch (\Exception $exception) {
                $failed[] = $magentoEntity->getEntityId();
                $this->logger->error(
                    message: 'Method: {method} - Entity ID: {entity_id} - Error: {exception}',
                    context: [
                        'method' => __METHOD__,
                        'entity_id' => $magentoEntity->getEntityId(),
                        'exception' => $exception->getMessage(),
                    ],
                );
            }
        }
        if ($failed) {
            throw new IndexingEntitySaveException(
                phrase: __(
                    'Failed to save Indexing Entities for Magento Entity IDs (%1). See log for details.',
                    implode(', ', $failed),
                ),
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
