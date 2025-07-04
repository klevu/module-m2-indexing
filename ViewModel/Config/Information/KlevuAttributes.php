<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\ViewModel\Config\Information;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sdk\AttributesProviderInterface;
use Klevu\IndexingApi\ViewModel\Config\Information\KlevuAttributesInterface;
use Klevu\PhpSDK\Model\Indexing\AttributeIterator;
use Magento\Framework\Phrase;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class KlevuAttributes implements KlevuAttributesInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;
    /**
     * @var AttributesProviderInterface
     */
    private readonly AttributesProviderInterface $attributesProvider;

    /**
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param ApiKeysProviderInterface $apiKeysProvider
     * @param AttributesProviderInterface $attributesProvider
     */
    public function __construct(
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ApiKeysProviderInterface $apiKeysProvider,
        AttributesProviderInterface $attributesProvider,
    ) {
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->apiKeysProvider = $apiKeysProvider;
        $this->attributesProvider = $attributesProvider;
    }

    /**
     * @return string[]
     */
    public function getChildBlocks(): array
    {
        return [];
    }

    /**
     * @return Phrase[][]
     */
    public function getMessages(): array
    {
        return [];
    }

    /**
     * @return string
     */
    public function getStyles(): string
    {
        return '';
    }

    /**
     * @return array<string, ?AttributeIterator>
     */
    public function getAttributesByApiKey(): array
    {
        $apiKeys = $this->apiKeysProvider->get(
            storeIds: array_map(
                callback: static fn ($store): int => (int)$store->getId(),
                array: $this->storeManager->getStores(),
            ),
        );

        $return = [];
        foreach ($apiKeys as $apiKey) {
            try {
                $return[$apiKey] = $this->attributesProvider->get(
                    apiKey: $apiKey,
                );
            } catch (\Throwable $exception) {
                $return[$apiKey] = [];
                $this->logger->error(
                    message: 'Failed to get attributes for API key: {apiKey}',
                    context: [
                        'exception' => $exception,
                        'error' => $exception->getMessage(),
                        'apiKey' => $apiKey,
                    ],
                );
            }
        }

        return $return;
    }
}
