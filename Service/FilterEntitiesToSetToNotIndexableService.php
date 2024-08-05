<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterEntitiesToSetToNotIndexableServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;

class FilterEntitiesToSetToNotIndexableService implements FilterEntitiesToSetToNotIndexableServiceInterface
{
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;

    /**
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     */
    public function __construct(IndexingEntityProviderInterface $indexingEntityProvider)
    {
        $this->indexingEntityProvider = $indexingEntityProvider;
    }

    /**
     * @param MagentoEntityInterface[][] $magentoEntitiesByApiKey
     * @param string $type
     * @param int[]|null $entityIds
     *
     * @return int[]
     */
    public function execute(array $magentoEntitiesByApiKey, string $type, ?array $entityIds = []): array
    {
        $return = [];
        foreach ($magentoEntitiesByApiKey as $apiKey => $magentoEntities) {
            $indexingEntities = $this->getIndexingEntities($type, $apiKey, $entityIds);
            $return[] = $this->getKlevuEntitiesNoLongerIndexable($type, $magentoEntities, $indexingEntities);
            $return[] = $this->getKlevuEntitiesNoLongerExist($type, $magentoEntities, $indexingEntities);
        }

        return array_filter(array_values(array_merge(...$return)));
    }

    /**
     * @param string $type
     * @param string $apiKey
     * @param int[] $entityIds
     *
     * @return IndexingEntityInterface[]
     */
    private function getIndexingEntities(string $type, string $apiKey, array $entityIds): array
    {
        return $this->indexingEntityProvider->get(
            entityType: $type,
            apiKey: $apiKey,
            entityIds: $entityIds,
        );
    }

    /**
     * @param string $type
     * @param MagentoEntityInterface[] $magentoEntities
     * @param IndexingEntityInterface[] $indexingEntities
     *
     * @return int[]
     */
    private function getKlevuEntitiesNoLongerIndexable(
        string $type,
        array $magentoEntities,
        array $indexingEntities,
    ): array {
        $magentoEntityIds = array_map(
            callback: static fn (MagentoEntityInterface $magentoEntity) => (
                $magentoEntity->getEntityId()
                . '-' . ($magentoEntity->getEntityParentId() ?: 0)
                . '-' . $magentoEntity->getApiKey()
                . '-' . $type
            ),
            array: array_filter(
                array: $magentoEntities,
                callback: static fn (MagentoEntityInterface $magentoEntity) => (!$magentoEntity->isIndexable()),
            ),
        );
        $klevuEntities = array_filter(
            array: $indexingEntities,
            callback: static function (IndexingEntityInterface $indexingEntity) use ($magentoEntityIds): bool {
                $klevuId = $indexingEntity->getTargetId()
                    . '-' . ($indexingEntity->getTargetParentId() ?: 0)
                    . '-' . $indexingEntity->getApiKey()
                    . '-' . $indexingEntity->getTargetEntityType();

                return in_array(needle: $klevuId, haystack: $magentoEntityIds, strict: true)
                    && $indexingEntity->getIsIndexable()
                    && in_array($indexingEntity->getLastAction(), [Actions::NO_ACTION, Actions::DELETE], true);
            },
        );

        return array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getId()
            ),
            array: $klevuEntities,
        );
    }

    /**
     * @param string $type
     * @param MagentoEntityInterface[] $magentoEntities
     * @param IndexingEntityInterface[] $indexingEntities
     *
     * @return int[]
     */
    private function getKlevuEntitiesNoLongerExist(
        string $type,
        array $magentoEntities,
        array $indexingEntities,
    ): array {
        $magentoEntityIds = array_map(
            callback: static fn (MagentoEntityInterface $magentoEntity): string => (
                $magentoEntity->getEntityId()
                . '-' . ($magentoEntity->getEntityParentId() ?: 0)
                . '-' . $magentoEntity->getApiKey()
                . '-' . $type
            ),
            array: $magentoEntities,
        );
        $klevuEntities = array_filter(
            array: $indexingEntities,
            callback: static function (IndexingEntityInterface $indexingEntity) use ($magentoEntityIds): bool {
                $klevuId = $indexingEntity->getTargetId()
                    . '-' . ($indexingEntity->getTargetParentId() ?: 0)
                    . '-' . $indexingEntity->getApiKey()
                    . '-' . $indexingEntity->getTargetEntityType();

                return !in_array(needle: $klevuId, haystack: $magentoEntityIds, strict: true)
                    && $indexingEntity->getIsIndexable()
                    && in_array($indexingEntity->getLastAction(), [Actions::NO_ACTION, Actions::DELETE], true);
            },
        );

        return array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => (
                (int)$indexingEntity->getId()
            ),
            array: $klevuEntities,
        );
    }
}
