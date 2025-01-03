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
     *
     * @param MagentoEntityInterface[] $magentoEntities
     * @param string $type
     * @param string $apiKey
     * @param int[]|null $entityIds
     * @param string[]|null $entitySubtypes
     *
     * @return int[]
     */
    public function execute(
        array $magentoEntities,
        string $type,
        string $apiKey,
        ?array $entityIds = [],
        ?array $entitySubtypes = [],
    ): array {
        if (!$entityIds) {
            $entityIds = array_map(
                callback: static fn (MagentoEntityInterface $magentoEntity): int => $magentoEntity->getEntityId(),
                array: $magentoEntities,
            );
        }
        if (!$entityIds) {
            return [];
        }
        $indexingEntities = $this->getIndexingEntities($type, $apiKey, $entityIds, $entitySubtypes);
        $return = $this->getKlevuEntitiesNoLongerIndexable($type, $magentoEntities, $indexingEntities);

        return array_filter($return);
    }

    /**
     * @param string $type
     * @param string $apiKey
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return IndexingEntityInterface[]
     */
    private function getIndexingEntities(string $type, string $apiKey, array $entityIds, array $entitySubtypes): array
    {
        return $this->indexingEntityProvider->get(
            entityType: $type,
            apiKeys: [$apiKey],
            entityIds: $entityIds,
            entitySubtypes: $entitySubtypes,
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
        $klevuEntities = $magentoEntityIds
            ? array_filter(
                array: $indexingEntities,
                callback: static function (IndexingEntityInterface $indexingEntity) use ($magentoEntityIds): bool {
                    $klevuId = $indexingEntity->getTargetId()
                        . '-' . ($indexingEntity->getTargetParentId() ?: 0)
                        . '-' . $indexingEntity->getApiKey()
                        . '-' . $indexingEntity->getTargetEntityType();

                    return in_array(needle: $klevuId, haystack: $magentoEntityIds, strict: true)
                        && $indexingEntity->getIsIndexable()
                        && $indexingEntity->getLastAction() !== Actions::DELETE;
                },
            )
            : [];

        return array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                (int)$indexingEntity->getId()
            ),
            array: $klevuEntities,
        );
    }
}
