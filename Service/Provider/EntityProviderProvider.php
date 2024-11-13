<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderProviderInterface;

class EntityProviderProvider implements EntityProviderProviderInterface
{
    /**
     * @var EntityProviderInterface[]
     */
    private array $entityProviders = [];

    /**
     * @param EntityProviderInterface[] $entityProviders
     */
    public function __construct(array $entityProviders)
    {
        array_walk($entityProviders, [$this, 'addEntityProvider']);
    }

    /**
     * @return EntityProviderInterface[]
     */
    public function get(): array
    {
        return $this->entityProviders;
    }

    /**
     * @param EntityProviderInterface $entityProvider
     * @param string $key
     *
     * @return void
     */
    private function addEntityProvider(EntityProviderInterface $entityProvider, string $key): void
    {
        $this->entityProviders[$key] = $entityProvider;
    }
}
