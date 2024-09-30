<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Configuration\Exception\ApiKeyNotFoundException;
use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\Indexing\Exception\StoreApiKeyException;
use Klevu\IndexingApi\Model\Source\StandardAttribute;
use Klevu\IndexingApi\Service\Provider\Sdk\AttributesProviderInterface;
use Klevu\IndexingApi\Service\Provider\StandardAttributesProviderInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface;
use Klevu\PhpSDK\Exception\ApiExceptionInterface;
use Klevu\PhpSDK\Model\Indexing\AttributeIterator;
use Klevu\PhpSDK\Model\IteratorInterface;
use Psr\Log\LoggerInterface;

class StandardAttributesProvider implements StandardAttributesProviderInterface
{
    /**
     * @var AttributesProviderInterface
     */
    private readonly AttributesProviderInterface $attributesProvider;
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param AttributesProviderInterface $attributesProvider
     * @param ApiKeysProviderInterface $apiKeysProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        AttributesProviderInterface $attributesProvider,
        ApiKeysProviderInterface $apiKeysProvider,
        LoggerInterface $logger,
    ) {
        $this->attributesProvider = $attributesProvider;
        $this->apiKeysProvider = $apiKeysProvider;
        $this->logger = $logger;
    }

    /**
     * @param string $apiKey
     *
     * @return AttributeIterator
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     * @throws StoreApiKeyException
     */
    public function get(string $apiKey): IteratorInterface
    {
        $attributes = $this->attributesProvider->get(apiKey: $apiKey);

        return $attributes->filter(
            callback: static fn (AttributeInterface $attribute): bool => $attribute->isImmutable(),
        );
    }

    /**
     * @param string|null $apiKey
     * @param bool $includeAliases
     *
     * @return string[]
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    public function getAttributeCodes(?string $apiKey = null, bool $includeAliases = false): array
    {
        if (null === $apiKey) {
            return StandardAttribute::values();
        }
        try {
            $attributes = $this->get(apiKey: $apiKey);
        } catch (StoreApiKeyException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );

            return StandardAttribute::values();
        }
        $attributeCodes = [];
        foreach ($attributes as $attribute) {
            $attributeCodes[] = $attribute->getAttributeName();
            if ($includeAliases) {
                foreach ($attribute->getAliases() as $alias) {
                    $attributeCodes[] = $alias;
                }
            }
        }

        return $attributeCodes;
    }

    /**
     * @param bool $includeAliases
     *
     * @return string[]
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    public function getAttributeCodesForAllApiKeys(bool $includeAliases = false): array
    {
        $apiKeys = $this->apiKeysProvider->get(storeIds: []);
        $attributes = $apiKeys
            ? []
            : [$this->getAttributeCodes(apiKey: null, includeAliases: $includeAliases)];
        foreach ($apiKeys as $apiKey) {
            $attributes[] = $this->getAttributeCodes(apiKey: $apiKey, includeAliases: $includeAliases);
        }

        return array_unique(
            array_merge(
                ...$attributes,
            ),
        );
    }
}
