<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Configuration\Service\Provider\StoresProviderInterface;
use Klevu\Indexing\Exception\InvalidAccountCredentialsException;
use Klevu\IndexingApi\Api\Data\SyncResultInterface;
use Klevu\IndexingApi\Service\Action\Sdk\Attribute\ActionInterface;
use Klevu\IndexingApi\Service\AttributeIndexerServiceInterface;
use Klevu\IndexingApi\Service\Provider\Sync\AttributeIndexingRecordProviderInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface as SdkAttributeInterface;
use Klevu\PhpSDK\Model\AccountCredentials;

class AttributeIndexerService implements AttributeIndexerServiceInterface
{
    /**
     * @var AttributeIndexingRecordProviderInterface
     */
    private readonly AttributeIndexingRecordProviderInterface $attributeIndexingRecordProvider;
    /**
     * @var ActionInterface
     */
    private readonly ActionInterface $action;
    /**
     * @var StoresProviderInterface
     */
    private readonly StoresProviderInterface $storesProvider;
    /**
     * @var ScopeProviderInterface
     */
    private readonly ScopeProviderInterface $scopeProvider;

    /**
     * @param AttributeIndexingRecordProviderInterface $attributeIndexingRecordProvider
     * @param ActionInterface $action
     * @param StoresProviderInterface $storesProvider
     * @param ScopeProviderInterface $scopeProvider
     */
    public function __construct(
        AttributeIndexingRecordProviderInterface $attributeIndexingRecordProvider,
        ActionInterface $action,
        StoresProviderInterface $storesProvider,
        ScopeProviderInterface $scopeProvider,
    ) {
        $this->attributeIndexingRecordProvider = $attributeIndexingRecordProvider;
        $this->action = $action;
        $this->storesProvider = $storesProvider;
        $this->scopeProvider = $scopeProvider;
    }

    /**
     * @param AccountCredentials $accountCredentials
     * @param string $attributeType
     *
     * @return SyncResultInterface[]
     * @throws InvalidAccountCredentialsException
     */
    public function execute(
        AccountCredentials $accountCredentials,
        string $attributeType,
    ): array {
        $responses = [];
        try {
            $this->setStoreScope(accountCredentials: $accountCredentials);
        } catch (\LogicException) {
            return $responses;
        }
        /** @var SdkAttributeInterface[] $attributes */
        $attributes = $this->attributeIndexingRecordProvider->get($accountCredentials->jsApiKey);
        foreach ($attributes as $attribute) {
            $responses[$attribute->getAttributeName()] = $this->action->execute(
                accountCredentials: $accountCredentials,
                attribute: $attribute,
                attributeType: $attributeType,
            );
        }

        return $responses;
    }

    /**
     * @param AccountCredentials $accountCredentials
     *
     * @return void
     * @throws \LogicException
     */
    private function setStoreScope(AccountCredentials $accountCredentials): void
    {
        $stores = $this->storesProvider->get($accountCredentials->jsApiKey);
        if (!$stores) {
            throw new \LogicException(
                sprintf(
                    'API key "%s" not integrated with any store.',
                    $accountCredentials->jsApiKey,
                ),
            );
        }
        $store = array_shift($stores);
        $this->scopeProvider->setCurrentScope($store);
    }
}
