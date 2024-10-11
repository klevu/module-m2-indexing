<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider\Cache;

use Klevu\IndexingApi\Service\Provider\Cache\AttributesCacheKeyProviderInterface;
use Klevu\IndexingApi\Service\Provider\Cache\CachedAttributesProviderInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface;
use Klevu\PhpSDK\Model\Indexing\AttributeFactory;
use Klevu\PhpSDK\Model\Indexing\AttributeIterator;
use Klevu\PhpSDK\Model\Indexing\AttributeIteratorFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;

class CachedAttributesProvider implements CachedAttributesProviderInterface
{
    /**
     * @var CacheInterface
     */
    private readonly CacheInterface $cache;
    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;
    /**
     * @var AttributesCacheKeyProviderInterface
     */
    private readonly AttributesCacheKeyProviderInterface $attributesCacheKeyProvider;
    /**
     * @var AttributeIteratorFactory
     */
    private readonly AttributeIteratorFactory $attributeIteratorFactory;
    /**
     * @var AttributeFactory
     */
    private AttributeFactory $attributeFactory;

    /**
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param AttributesCacheKeyProviderInterface $attributesCacheKeyProvider
     * @param AttributeIteratorFactory $attributeIteratorFactory
     * @param AttributeFactory $attributeFactory
     */
    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer,
        AttributesCacheKeyProviderInterface $attributesCacheKeyProvider,
        AttributeIteratorFactory $attributeIteratorFactory,
        AttributeFactory $attributeFactory,
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->attributesCacheKeyProvider = $attributesCacheKeyProvider;
        $this->attributeIteratorFactory = $attributeIteratorFactory;
        $this->attributeFactory = $attributeFactory;
    }

    /**
     * @param string $apiKey
     *
     * @return AttributeIterator|null
     */
    public function get(string $apiKey): ?AttributeIterator
    {
        $cacheKey = $this->attributesCacheKeyProvider->get(apiKey: $apiKey);
        $cachedData = $this->cache->load(identifier: $cacheKey);
        if (!$cachedData) {
            return null;
        }
        $attributesData = $this->serializer->unserialize(string: $cachedData);
        /** @var AttributeInterface[] $attributes */
        $attributes = [];
        foreach ($attributesData as $attributeData) {
            $attributes[] = $this->attributeFactory->create($attributeData);
        }

        return $this->attributeIteratorFactory->create([
            'data' => $this->addCategoryPathAttribute($attributes),
        ]);
    }

    /**
     * Temp work around until the GET /attributes endpoint returns categoryPath
     *
     * @param AttributeInterface[] $attributes
     *
     * @return AttributeInterface[]
     */
    private function addCategoryPathAttribute(array $attributes): array
    {
        $categoryPathAttributes = array_filter(
            array: $attributes,
            callback: static fn (AttributeInterface $attribute): bool => (
                $attribute->getAttributeName() === 'categoryPath'
            ),
        );
        if (!count($categoryPathAttributes)) {
            $attributes[] = $this->attributeFactory->create([
                'abbreviate' => false,
                'alias' => [],
                'attributeName' => 'categoryPath',
                'datatype' => 'STRING',
                'filterable' => false,
                'immutable' => true,
                'label' => [
                    'default' => 'Category Path',
                ],
                'rangeable' => false,
                'returnable' => false,
                'searchable' => false,
            ]);
        }

        return $attributes;
    }
}
