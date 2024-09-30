<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Configuration\Exception\ApiKeyNotFoundException;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\FilterAttributesToAddServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\StandardAttributesProviderInterface;
use Klevu\PhpSDK\Exception\ApiExceptionInterface;
use Psr\Log\LoggerInterface;

class FilterAttributesToAddService implements FilterAttributesToAddServiceInterface
{
    /**
     * @var IndexingAttributeProviderInterface
     */
    private readonly IndexingAttributeProviderInterface $indexingAttributeProvider;
    /**
     * @var StandardAttributesProviderInterface
     */
    private readonly StandardAttributesProviderInterface $standardAttributesProvider;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var string[]
     */
    private array $standardAttributes = [];

    /**
     * @param IndexingAttributeProviderInterface $indexingAttributeProvider
     * @param StandardAttributesProviderInterface $standardAttributesProvider
     * @param LoggerInterface $logger
     */
    public function __construct(
        IndexingAttributeProviderInterface $indexingAttributeProvider,
        StandardAttributesProviderInterface $standardAttributesProvider,
        LoggerInterface $logger,
    ) {
        $this->indexingAttributeProvider = $indexingAttributeProvider;
        $this->standardAttributesProvider = $standardAttributesProvider;
        $this->logger = $logger;
    }

    /**
     * @param MagentoAttributeInterface[][] $magentoAttributesByApiKey
     * @param string $entityType
     *
     * @return MagentoAttributeInterface[][]
     */
    public function execute(array $magentoAttributesByApiKey, string $entityType): array
    {
        $return = [];
        foreach ($magentoAttributesByApiKey as $apiKey => $magentoAttributes) {
            $klevuAttributeIds = $this->getKlevuAttributeIds(entityType: $entityType, apiKey: $apiKey);
            try {
                $return[$apiKey] = array_filter(
                    array: $magentoAttributes,
                    callback: fn (MagentoAttributeInterface $magentoAttribute) => (
                        // Remove standard attributes, they already exist in klevu we can't create them again
                        !in_array(
                            needle: $magentoAttribute->getKlevuAttributeName(),
                            haystack: $this->getStandardAttributeCodes(apiKey: $apiKey),
                            strict: true,
                        )
                        && !in_array(
                            needle: (int)$magentoAttribute->getAttributeId(),
                            haystack: $klevuAttributeIds,
                            strict: true,
                        )
                    ),
                );
            } catch (ApiExceptionInterface | ApiKeyNotFoundException $exception) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }

        return $return;
    }

    /**
     * @param string $entityType
     * @param string $apiKey
     *
     * @return int[]
     */
    private function getKlevuAttributeIds(string $entityType, string $apiKey): array
    {
        $klevuAttributes = $this->indexingAttributeProvider->get(
            attributeType: $entityType,
            apiKey: $apiKey,
        );

        return array_map(
            callback: static fn (IndexingAttributeInterface $indexingAttribute): int => (
                (int)$indexingAttribute->getTargetId()
            ),
            array: $klevuAttributes,
        );
    }

    /**
     * @param string $apiKey
     *
     * @return string[]
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    private function getStandardAttributeCodes(string $apiKey): array
    {
        if (!($this->standardAttributes[$apiKey] ?? null)) {
            $this->standardAttributes[$apiKey] = $this->standardAttributesProvider->getAttributeCodes(
                apiKey: $apiKey,
                includeAliases: true,
            );
        }

        return $this->standardAttributes[$apiKey];
    }
}
