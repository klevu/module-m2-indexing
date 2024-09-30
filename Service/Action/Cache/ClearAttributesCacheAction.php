<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action\Cache;

use Klevu\Indexing\Cache\Attributes as AttributesCache;
use Klevu\IndexingApi\Service\Action\Cache\ClearAttributesCacheActionInterface;
use Klevu\IndexingApi\Service\Provider\Cache\AttributesCacheKeyProviderInterface;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeList;
use Magento\Framework\App\CacheInterface;
use Psr\Log\LoggerInterface;

class ClearAttributesCacheAction implements ClearAttributesCacheActionInterface
{
    /**
     * @var StateInterface
     */
    private readonly StateInterface $cacheState;
    /**
     * @var TypeList
     */
    private readonly TypeList $cacheTypeList;
    /**
     * @var CacheInterface
     */
    private readonly CacheInterface $cache;
    /**
     * @var AttributesCacheKeyProviderInterface
     */
    private readonly AttributesCacheKeyProviderInterface $attributesCacheKeyProvider;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param StateInterface $cacheState
     * @param TypeList $cacheTypeList
     * @param CacheInterface $cache
     * @param AttributesCacheKeyProviderInterface $attributesCacheKeyProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        StateInterface $cacheState,
        TypeList $cacheTypeList,
        CacheInterface $cache,
        AttributesCacheKeyProviderInterface $attributesCacheKeyProvider,
        LoggerInterface $logger,
    ) {
        $this->cacheState = $cacheState;
        $this->cacheTypeList = $cacheTypeList;
        $this->cache = $cache;
        $this->attributesCacheKeyProvider = $attributesCacheKeyProvider;
        $this->logger = $logger;
    }

    /**
     * @param string[] $apiKeys
     *
     * @return void
     */
    public function execute(array $apiKeys = []): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }
        $apiKeys
            ? $this->clearCachedDataForApiKeys($apiKeys)
            : $this->clearCachedData();

        $this->logCacheCleared($apiKeys);
    }

    /**
     * @return bool
     */
    private function isCacheEnabled(): bool
    {
        return $this->cacheState->isEnabled(
            cacheType: AttributesCache::TYPE_IDENTIFIER,
        );
    }

    /**
     * @param string[] $apiKeys
     *
     * @return void
     */
    private function clearCachedDataForApiKeys(array $apiKeys): void
    {
        foreach ($apiKeys as $apiKey) {
            $this->cache->remove(
                identifier: $this->attributesCacheKeyProvider->get(apiKey: $apiKey),
            );
        }
    }

    /**
     * @return void
     */
    private function clearCachedData(): void
    {
        $this->cacheTypeList->cleanType(
            typeCode: AttributesCache::TYPE_IDENTIFIER,
        );
    }

    /**
     * @param string[] $apiKeys
     *
     * @return void
     */
    private function logCacheCleared(array $apiKeys = []): void
    {
        $message = $apiKeys
            ? __(
                'Attributes cached cleared for API Keys: %1',
                implode(', ', $apiKeys),
            )->render()
            : __('Attributes cached cleared for all API Keys')->render();

        $this->logger->info(
            message: 'Method: {method}, Info: {message}',
            context: [
                'method' => __METHOD__,
                'message' => $message,
            ],
        );
    }
}
