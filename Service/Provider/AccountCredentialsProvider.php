<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Configuration\Service\IsStoreIntegratedServiceInterface;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\Configuration\Service\Provider\AuthKeyProviderInterface;
use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\IndexingApi\Service\Provider\AccountCredentialsProviderInterface;
use Klevu\PhpSDK\Model\AccountCredentials;
use Klevu\PhpSDK\Model\AccountCredentialsFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AccountCredentialsProvider implements AccountCredentialsProviderInterface
{
    /**
     * @var AuthKeyProviderInterface
     */
    private readonly AuthKeyProviderInterface $authKeyProvider;
    /**
     * @var ApiKeyProviderInterface
     */
    private readonly ApiKeyProviderInterface $apiKeyProvider;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;
    /**
     * @var IsStoreIntegratedServiceInterface
     */
    private readonly IsStoreIntegratedServiceInterface $isStoreIntegratedService;
    /**
     * @var AccountCredentialsFactory
     */
    private readonly AccountCredentialsFactory $accountCredentialsFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param AuthKeyProviderInterface $authKeyProvider
     * @param ApiKeyProviderInterface $apiKeyProvider
     * @param StoreManagerInterface $storeManager
     * @param ScopeProviderInterface $scopeProvider
     * @param IsStoreIntegratedServiceInterface $isStoreIntegratedService
     * @param AccountCredentialsFactory $accountCredentialsFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        AuthKeyProviderInterface $authKeyProvider,
        ApiKeyProviderInterface $apiKeyProvider,
        StoreManagerInterface $storeManager,
        ScopeProviderInterface $scopeProvider,
        IsStoreIntegratedServiceInterface $isStoreIntegratedService,
        AccountCredentialsFactory $accountCredentialsFactory,
        LoggerInterface $logger,
    ) {
        $this->authKeyProvider = $authKeyProvider;
        $this->apiKeyProvider = $apiKeyProvider;
        $this->storeManager = $storeManager;
        $this->scopeProvider = $scopeProvider;
        $this->isStoreIntegratedService = $isStoreIntegratedService;
        $this->accountCredentialsFactory = $accountCredentialsFactory;
        $this->logger = $logger;
    }

    /**
     * @param StoreInterface|null $store
     *
     * @return AccountCredentials[]
     */
    public function get(?StoreInterface $store = null): array
    {
        $return = [];
        $currentScope = $this->scopeProvider->getCurrentScope();
        $stores = $store
            ? [$store]
            : $this->storeManager->getStores();
        foreach ($stores as $currentStore) {
            $this->scopeProvider->setCurrentScope(scope: $currentStore);
            if (!$this->isStoreIntegratedService->execute()) {
                continue;
            }
            $return = $this->generateAccountCredentials(
                scopeProvider: $this->scopeProvider,
                return: $return,
            );
        }
        if ($currentScope->getScopeObject()) {
            $this->scopeProvider->setCurrentScope(scope: $currentScope->getScopeObject());
        } else {
            $this->scopeProvider->unsetCurrentScope();
        }

        return $return;
    }

    /**
     * @param ScopeProviderInterface $scopeProvider
     * @param AccountCredentials[] $return
     *
     * @return AccountCredentials[]
     */
    private function generateAccountCredentials(
        ScopeProviderInterface $scopeProvider,
        array $return,
    ): array {
        try {
            $jsApiKey = $this->apiKeyProvider->get(scope: $scopeProvider->getCurrentScope());
            if (array_key_exists(key: $jsApiKey, array: $return)) {
                return $return;
            }
            $return[$jsApiKey] = $this->accountCredentialsFactory->create(data: [
                'jsApiKey' => $jsApiKey,
                'restAuthKey' => $this->authKeyProvider->get(scope: $scopeProvider->getCurrentScope()),
            ]);
        } catch (NoSuchEntityException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $return;
    }
}
