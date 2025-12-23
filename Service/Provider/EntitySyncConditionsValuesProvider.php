<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\IndexingApi\Service\Provider\EntitySyncConditionsValuesProviderInterface;

class EntitySyncConditionsValuesProvider implements EntitySyncConditionsValuesProviderInterface
{
    /**
     * @var array<string, EntitySyncConditionsValuesProviderInterface>
     */
    private array $entitySyncConditionsValuesProviders = [];

    /**
     * @param array<string, EntitySyncConditionsValuesProviderInterface> $entitySyncConditionsValuesProviders
     */
    public function __construct(
        array $entitySyncConditionsValuesProviders = [],
    ) {
        array_walk(
            array: $entitySyncConditionsValuesProviders,
            callback: [$this, 'addEntitySyncConditionsValuesProvider'],
        );
    }

    /**
     * @param string $targetEntityType
     * @param int $targetEntityId
     *
     * @return array|\Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterface[]
     */
    public function get(
        string $targetEntityType,
        int $targetEntityId,
    ): array {
        $entitySyncConditionsValuesProvider = $this->entitySyncConditionsValuesProviders[$targetEntityType] ?? null;
        if (null === $entitySyncConditionsValuesProvider) {
            throw new \InvalidArgumentException(
                message: sprintf(
                    'No EntitySyncConditionsValuesProvider found for entity type "%s"', 
                    $targetEntityType,
                ),
            );
        }

        return $entitySyncConditionsValuesProvider->get(
            targetEntityType: $targetEntityType,
            targetEntityId: $targetEntityId,
        );
    }

    /**
     * @param EntitySyncConditionsValuesProviderInterface $provider
     * @param string $entityType
     *
     * @return void
     */
    private function addEntitySyncConditionsValuesProvider(
        EntitySyncConditionsValuesProviderInterface $provider,
        string $entityType,
    ): void {
        $this->entitySyncConditionsValuesProviders[$entityType] = $provider;
    }
}
