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
     * @param string $type
     * @param int[] $entityIds
     * @param string[] $apiKeys
     *
     * @return int[]
     */
    public function execute(string $type, array $entityIds, array $apiKeys): array
    {
        $entityIdsByApiKey = [];
        foreach ($apiKeys as $apiKey) {
            $entityIdsByApiKey[$apiKey] = $this->getIndexingEntities($type, $apiKey, $entityIds);
        }

        return array_filter(
            array_unique(
                array_merge(
                    ...array_values($entityIdsByApiKey),
                ),
            ),
        );
    }

    /**
     * @param string $type
     * @param string $apiKey
     * @param int[] $entityIds
     *
     * @return int[]
     */
    private function getIndexingEntities(string $type, string $apiKey, array $entityIds): array
    {
        $klevuEntities = $this->indexingEntityProvider->get(
            entityType: $type,
            apiKey: $apiKey,
            entityIds: $entityIds,
        );

        return array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => ($indexingEntity->getId()),
            array: array_filter(
                array: $klevuEntities,
                callback: static fn (IndexingEntityInterface $indexingEntity): bool => (
                    $indexingEntity->getIsIndexable()
                ),
            ),
        );
    }
}
