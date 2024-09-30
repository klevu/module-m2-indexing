<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\IndexingApi\Service\Provider\AttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\Discovery\AttributeCollectionInterface;
use Magento\Eav\Api\Data\AttributeInterface;

class AttributeProvider implements AttributeProviderInterface
{
    /**
     * @var AttributeCollectionInterface
     */
    private readonly AttributeCollectionInterface $attributeCollection;

    /**
     * @param AttributeCollectionInterface $attributeCollection
     */
    public function __construct(
        AttributeCollectionInterface $attributeCollection,
    ) {
        $this->attributeCollection = $attributeCollection;
    }

    /**
     * @param int[]|null $attributeIds
     *
     * @return \Generator<AttributeInterface>
     */
    public function get(?array $attributeIds = []): \Generator
    {
        $collection = $this->attributeCollection->get($attributeIds);
        /** @var AttributeInterface $attribute */
        foreach ($collection as $attribute) {
            yield $attribute;
        }
    }
}
