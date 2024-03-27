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
     * @param MagentoEntityInterface[][] $magentoEntitiesByApiKey
     * @param string $type
     *
     * @return MagentoEntityInterface[][]
     */
    public function execute(array $magentoEntitiesByApiKey, string $type): array
    {
        $return = [];
        foreach ($magentoEntitiesByApiKey as $apiKey => $magentoEntities) {
            $klevuEntityIds = $this->getKlevuEntityIds($type, $apiKey);

            $return[$apiKey] = array_filter(
                array: $magentoEntities,
                callback: static fn (MagentoEntityInterface $magentoEntity) => (
                    !in_array(
                        needle: $magentoEntity->getEntityId() . '-' . ($magentoEntity->getEntityParentId() ?? 0),
                        haystack: $klevuEntityIds,
                        strict: true,
                    )
                ),
            );
        }

        return $return;
    }

    /**
     * @param string $type
     * @param string $apiKey
     *
     * @return string[]
     */
    private function getKlevuEntityIds(string $type, string $apiKey): array
    {
        $klevuEntities = $this->indexingEntityProvider->get(
            entityType: $type,
            apiKey: $apiKey,
        );

        return array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity) => (
                $indexingEntity->getTargetId() . '-' . ($indexingEntity->getTargetParentId() ?? 0)
            ),
            array: $klevuEntities,
        );
    }
}
