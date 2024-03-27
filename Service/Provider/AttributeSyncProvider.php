<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\IndexingApi\Service\Provider\AttributesToSyncProviderInterface;
use Klevu\IndexingApi\Service\Provider\AttributeSyncProviderInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface;

class AttributeSyncProvider implements AttributeSyncProviderInterface
{
    /**
     * @var AttributesToSyncProviderInterface[]
     */
    private readonly array $attributeProviders;

    /**
     * @param AttributesToSyncProviderInterface[] $attributeProviders
     */
    public function __construct(array $attributeProviders = [])
    {
        $this->attributeProviders = $attributeProviders;
    }

    /**
     * @param string|null $attributeTypeFilter
     *
     * @return AttributeInterface[]
     */
    public function get(?string $attributeTypeFilter = null): array
    {
        $return = [];
        foreach ($this->attributeProviders as $attributeType => $attributeProvider) {
            if ($attributeTypeFilter && $attributeTypeFilter !== $attributeType) {
                continue;
            }
            $return[] = $attributeProvider->get();
        }

        return array_merge([], ...$return);
    }
}
