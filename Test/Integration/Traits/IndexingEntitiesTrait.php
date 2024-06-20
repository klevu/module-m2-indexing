<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Traits;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

trait IndexingEntitiesTrait
{
    /**
     * @param mixed[] $data
     *
     * @return IndexingEntityInterface
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createIndexingEntity(array $data): IndexingEntityInterface
    {
        $repository = $this->objectManager->get(IndexingEntityRepositoryInterface::class);
        $indexingEntity = $repository->create();
        $indexingEntity->setTargetId((int)$data[IndexingEntity::TARGET_ID]);
        $indexingEntity->setTargetParentId(
            ($data[IndexingEntity::TARGET_PARENT_ID] ?? null)
                ? (int)$data[IndexingEntity::TARGET_PARENT_ID]
                : null,
        );
        $indexingEntity->setTargetEntityType($data[IndexingEntity::TARGET_ENTITY_TYPE] ?? 'KLEVU_PRODUCT');
        $indexingEntity->setApiKey($data[IndexingEntity::API_KEY] ?? 'klevu-js-api-key');
        $indexingEntity->setNextAction($data[IndexingEntity::NEXT_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastAction($data[IndexingEntity::LAST_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastActionTimestamp($data[IndexingEntity::LAST_ACTION_TIMESTAMP] ?? null);
        $indexingEntity->setLockTimestamp($data[IndexingEntity::LOCK_TIMESTAMP] ?? null);
        $indexingEntity->setIsIndexable($data[IndexingEntity::IS_INDEXABLE] ?? true);

        return $repository->save($indexingEntity);
    }

    /**
     * @param string $apiKey
     *
     * @return void
     */
    private function cleanIndexingEntities(string $apiKey): void
    {
        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: IndexingEntity::API_KEY,
            value: $apiKey,
            conditionType: 'like',
        );
        $searchCriteria = $searchCriteriaBuilder->create();
        /** @var IndexingEntityRepositoryInterface $repository */
        $repository = $this->objectManager->get(IndexingEntityRepositoryInterface::class);
        $indexingEntitiesToDelete = $repository->getList($searchCriteria);
        foreach ($indexingEntitiesToDelete->getItems() as $indexingEntity) {
            try {
                $repository->delete($indexingEntity);
            } catch (LocalizedException) {
                // this is fine, indexingEntity already deleted
            }
        }
    }

    /**
     * @param string $apiKey
     * @param ExtensibleDataInterface|PageInterface $entity
     * @param string|null $type
     *
     * @return IndexingEntityInterface|null
     */
    private function getIndexingEntityForEntity(
        string $apiKey,
        ExtensibleDataInterface|PageInterface $entity,
        ?string $type = null,
    ): ?IndexingEntityInterface {
        $productIndexingEntities = $this->getIndexingEntities($type, $apiKey);
        $productIndexingEntityArray = array_filter(
            array: $productIndexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$entity->getId()
            )
        );

        return array_shift($productIndexingEntityArray);
    }

    /**
     * @param string|null $type
     * @param string|null $apiKey
     *
     * @return IndexingEntityInterface[]
     */
    private function getIndexingEntities(?string $type = null, ?string $apiKey = null): array
    {
        $collection = $this->objectManager->create(Collection::class);
        if ($type) {
            $collection->addFieldToFilter(
                field: IndexingEntity::TARGET_ENTITY_TYPE,
                condition: ['eq' => $type],
            );
        }
        if ($apiKey) {
            $collection->addFieldToFilter(
                field: IndexingEntity::API_KEY,
                condition: ['eq' => $apiKey],
            );
        }

        return $collection->getItems();
    }
}
