<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToAddServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;

class FilterEntitiesToAddService implements FilterEntitiesToAddServiceInterface
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
     * @param string[] $entitySubtypes
     *
     * @return \Generator<MagentoEntityInterface>
     */
    public function execute(
        array $magentoEntities,
        string $type,
        string $apiKey,
        array $entitySubtypes = [],
    ): \Generator {
        $entityIds = array_map(
            callback: static fn (MagentoEntityInterface $magentoEntity): int => $magentoEntity->getEntityId(),
            array: $magentoEntities,
        );
        $klevuEntityIds = $this->getKlevuEntityIds(
            type: $type,
            apiKey: $apiKey,
            entityIds: $entityIds,
            entitySubtypes: $entitySubtypes,
        );
        foreach ($magentoEntities as $magentoEntity) {
            if (
                !$klevuEntityIds
                || !in_array(
                    needle: $magentoEntity->getEntityId() . '-' . ($magentoEntity->getEntityParentId() ?? 0),
                    haystack: $klevuEntityIds,
                    strict: true,
                )
            ) {
                yield $magentoEntity;
            }
        }
    }

    /**
     * @param string $type
     * @param string $apiKey
     * @param int[] $entityIds
     * @param string[] $entitySubtypes
     *
     * @return string[]
     */
    private function getKlevuEntityIds(string $type, string $apiKey, array $entityIds, array $entitySubtypes): array
    {
        $klevuEntities = $this->indexingEntityProvider->get(
            entityType: $type,
            apiKeys: [$apiKey],
            entityIds: $entityIds,
            entitySubtypes: $entitySubtypes,
        );

        return array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                $indexingEntity->getTargetId() . '-' . ($indexingEntity->getTargetParentId() ?? 0)
            ),
            array: $klevuEntities,
        );
    }
}
