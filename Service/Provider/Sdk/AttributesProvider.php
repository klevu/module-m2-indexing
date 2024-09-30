<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider\Sdk;

use Klevu\Configuration\Exception\ApiKeyNotFoundException;
use Klevu\Configuration\Service\Provider\AuthKeyProviderInterface;
use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Configuration\Service\Provider\StoresProviderInterface;
use Klevu\Indexing\Exception\StoreApiKeyException;
use Klevu\IndexingApi\Service\Action\Cache\CacheAttributesActionInterface;
use Klevu\IndexingApi\Service\Provider\Cache\CachedAttributesProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sdk\AttributesProviderInterface;
use Klevu\PhpSDK\Api\Service\Indexing\AttributesServiceInterface;
use Klevu\PhpSDK\Exception\ApiExceptionInterface;
use Klevu\PhpSDK\Model\AccountCredentials;
use Klevu\PhpSDK\Model\AccountCredentialsFactory;
use Klevu\PhpSDK\Model\Indexing\AttributeIterator;

class AttributesProvider implements AttributesProviderInterface
{
    /**
     * @var AttributesServiceInterface
     */
    private readonly AttributesServiceInterface $attributesService;
    /**
     * @var AuthKeyProviderInterface
     */
    private readonly AuthKeyProviderInterface $authKeyProvider;
    /**
     * @var CachedAttributesProviderInterface
     */
    private readonly CachedAttributesProviderInterface $cachedAttributesProvider;
    /**
     * @var CacheAttributesActionInterface
     */
    private readonly CacheAttributesActionInterface $cacheAttributesAction;
    /**
     * @var AccountCredentialsFactory
     */
    private readonly AccountCredentialsFactory $accountCredentialsFactory;
    /**
     * @var StoresProviderInterface
     */
    private readonly StoresProviderInterface $storesProvider;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;

    /**
     * @param AttributesServiceInterface $attributesService
     * @param AuthKeyProviderInterface $authKeyProvider
     * @param CachedAttributesProviderInterface $cachedAttributesProvider
     * @param CacheAttributesActionInterface $cacheAttributesAction
     * @param AccountCredentialsFactory $accountCredentialsFactory
     * @param StoresProviderInterface $storesProvider
     * @param ScopeProviderInterface $scopeProvider
     */
    public function __construct(
        AttributesServiceInterface $attributesService,
        AuthKeyProviderInterface $authKeyProvider,
        CachedAttributesProviderInterface $cachedAttributesProvider,
        CacheAttributesActionInterface $cacheAttributesAction,
        AccountCredentialsFactory $accountCredentialsFactory,
        StoresProviderInterface $storesProvider,
        ScopeProviderInterface $scopeProvider,
    ) {
        $this->attributesService = $attributesService;
        $this->authKeyProvider = $authKeyProvider;
        $this->cachedAttributesProvider = $cachedAttributesProvider;
        $this->cacheAttributesAction = $cacheAttributesAction;
        $this->accountCredentialsFactory = $accountCredentialsFactory;
        $this->storesProvider = $storesProvider;
        $this->scopeProvider = $scopeProvider;
    }

    /**
     * @param string $apiKey
     *
     * @return AttributeIterator
     * @throws ApiKeyNotFoundException
     * @throws ApiExceptionInterface
     * @throws StoreApiKeyException
     */
    public function get(string $apiKey): AttributeIterator
    {
        $return = $this->cachedAttributesProvider->get(apiKey: $apiKey);
        if (null === $return) {
            $this->setStoreScope(apiKey: $apiKey);
            $return = $this->attributesService->get(
                accountCredentials: $this->getAccountCredentials(apiKey: $apiKey),
            );
            $this->cacheAttributesAction->execute(
                attributeIterator: $return,
                apiKey: $apiKey,
            );
        }

        return $return;
    }

    /**
     * @param string $apiKey
     *
     * @return AccountCredentials
     * @throws ApiKeyNotFoundException
     */
    private function getAccountCredentials(string $apiKey): AccountCredentials
    {
        return $this->accountCredentialsFactory->create(
            data: [
                'jsApiKey' => $apiKey,
                'restAuthKey' => $this->authKeyProvider->getForApiKey(apiKey: $apiKey),
            ],
        );
    }

    /**
     * @param string $apiKey
     *
     * @return void
     * @throws StoreApiKeyException
     */
    private function setStoreScope(string $apiKey): void
    {
        $stores = $this->storesProvider->get($apiKey);
        if (!$stores) {
            throw new StoreApiKeyException(
                __(
                    'API key "%1" not integrated with any store.',
                    $apiKey,
                ),
            );
        }
        $store = array_shift($stores);
        $this->scopeProvider->setCurrentScope($store);
    }
}
