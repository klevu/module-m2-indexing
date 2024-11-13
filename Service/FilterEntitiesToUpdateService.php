<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToUpdateServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;

class FilterEntitiesToUpdateService implements FilterEntitiesToUpdateServiceInterface
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
     * @param string $type
     * @param int[] $entityIds
     * @param string $apiKey
     * @param string[]|null $entitySubtypes
     *
     * @return int[]
     */
    public function execute(
        string $type,
        array $entityIds,
        string $apiKey,
        ?array $entitySubtypes = [],
    ): array {
        $entityIdsByApiKey = $this->getIndexingEntities($type, $apiKey, $entityIds, $entitySubtypes);

        return array_filter(
            array_unique($entityIdsByApiKey),
        );
    }

    /**
     * @param string $type
     * @param string $apiKey
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return int[]
     */
    private function getIndexingEntities(string $type, string $apiKey, array $entityIds, array $entitySubtypes): array
    {
        $klevuEntities = $this->indexingEntityProvider->get(
            entityType: $type,
            apiKeys: [$apiKey],
            entityIds: $entityIds,
            entitySubtypes: $entitySubtypes,
        );

        return array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => $indexingEntity->getId(),
            array: array_filter(
                array: $klevuEntities,
                callback: static fn (IndexingEntityInterface $indexingEntity): bool => (
                    $indexingEntity->getIsIndexable()
                ),
            ),
        );
    }
}
