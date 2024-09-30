<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action\Cache;

use Klevu\Indexing\Cache\Attributes as AttributesCache;
use Klevu\IndexingApi\Service\Action\Cache\CacheAttributesActionInterface;
use Klevu\IndexingApi\Service\Provider\Cache\AttributesCacheKeyProviderInterface;
use Klevu\PhpSDK\Model\Indexing\AttributeIterator;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class CacheAttributesAction implements CacheAttributesActionInterface
{
    public const CACHE_LIFETIME = 86400; // 24 hours

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
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     * @param AttributesCacheKeyProviderInterface $attributesCacheKeyProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        CacheInterface $cache,
        SerializerInterface $serializer,
        AttributesCacheKeyProviderInterface $attributesCacheKeyProvider,
        LoggerInterface $logger,
    ) {
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->attributesCacheKeyProvider = $attributesCacheKeyProvider;
        $this->logger = $logger;
    }

    /**
     * @param AttributeIterator $attributeIterator
     * @param string $apiKey
     *
     * @return void
     */
    public function execute(AttributeIterator $attributeIterator, string $apiKey): void
    {
        $cacheId = $this->attributesCacheKeyProvider->get(apiKey: $apiKey);
        $attributes = $attributeIterator->toArray();

        $attributesData = [];
        foreach ($attributes as $attribute) {
            $attributesData[] = $attribute->toArray();
        }

        $data = $this->serializer->serialize(data: $attributesData);

        $this->cache->save(
            data: $data,
            identifier: $cacheId,
            tags: [AttributesCache::CACHE_TAG],
            lifeTime: self::CACHE_LIFETIME,
        );

        $this->logger->info(
            message: 'Method: {method}, Info: {message}',
            context: [
                'method' => __METHOD__,
                'message' => __(
                    'Attributes SDK GET call cached for API key: %1',
                    $apiKey,
                ),
            ],
        );
    }
}
