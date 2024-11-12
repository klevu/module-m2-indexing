<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
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
        $return = [];
        if (!$entityIds) {
            $entityIds = array_map(
                callback: static fn (MagentoEntityInterface $magentoEntity): int => $magentoEntity->getEntityId(),
                array: $magentoEntities,
            );
        }
        $magentoEntityIds = $this->getIndexableMagentoEntityIds($magentoEntities, $type, $entityIds);
        if (!$magentoEntityIds) {
            return $return;
        }
        $klevuEntities = array_filter(
            array: $this->getIndexingEntities($type, $apiKey, $entityIds, $entitySubtypes),
            callback: static function (IndexingEntityInterface $indexingEntity) use ($magentoEntityIds): bool {
                $klevuId = $indexingEntity->getTargetId()
                    . '-' . ($indexingEntity->getTargetParentId() ?: 0)
                    . '-' . $indexingEntity->getApiKey()
                    . '-' . $indexingEntity->getTargetEntityType();

                return in_array(needle: $klevuId, haystack: $magentoEntityIds, strict: true)
                    && (!$indexingEntity->getIsIndexable()
                        || $indexingEntity->getNextAction() === Actions::DELETE);
            },
        );

        $return = array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => (
                (int)$indexingEntity->getId()
            ),
            array: $klevuEntities,
        );

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
     * @param MagentoEntityInterface[] $magentoEntities
     * @param string $type
     * @param int[] $entityIds
     *
     * @return string[]
     */
    private function getIndexableMagentoEntityIds(array $magentoEntities, string $type, array $entityIds): array
    {
        return array_map(
            callback: static fn (MagentoEntityInterface $magentoEntity): string => (
                $magentoEntity->getEntityId()
                . '-' . ($magentoEntity->getEntityParentId() ?: 0)
                . '-' . $magentoEntity->getApiKey()
                . '-' . $type
            ),
            array: array_filter(
                array: $magentoEntities,
                callback: static fn (MagentoEntityInterface $magentoEntity): bool => (
                    $magentoEntity->isIndexable() && in_array($magentoEntity->getEntityId(), $entityIds, true)
                ),
            ),
        );
    }
}
