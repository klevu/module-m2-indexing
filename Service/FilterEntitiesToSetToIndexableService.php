<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToSetToIndexableServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;

class FilterEntitiesToSetToIndexableService implements FilterEntitiesToSetToIndexableServiceInterface
{
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;

    /**
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     */
    public function __construct(
        IndexingEntityProviderInterface $indexingEntityProvider,
    ) {
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
            $magentoEntityIds = array_map(
                callback: static fn (MagentoEntityInterface $magentoEntity) => (
                    $magentoEntity->getEntityId()
                    . '-' . ($magentoEntity->getEntityParentId() ?: 0)
                    . '-' . $magentoEntity->getApiKey()
                    . '-' . $type
                ),
                array: array_filter(
                    array: $magentoEntities,
                    callback: static fn (MagentoEntityInterface $magentoEntity) => ($magentoEntity->isIndexable()),
                ),
            );

            $klevuEntities = array_filter(
                array: $this->getIndexingEntities($type, $apiKey, $entityIds),
                callback: static function (IndexingEntityInterface $indexingEntity) use ($magentoEntityIds): bool {
                    $klevuId = $indexingEntity->getTargetId()
                        . '-' . ($indexingEntity->getTargetParentId() ?: 0)
                        . '-' . $indexingEntity->getApiKey()
                        . '-' . $indexingEntity->getTargetEntityType();

                    return in_array(needle: $klevuId, haystack: $magentoEntityIds, strict: true)
                        && !$indexingEntity->getIsIndexable();
                },
            );

            $return[] = array_map(
                callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getId()
                ),
                array: $klevuEntities,
            );
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
}
