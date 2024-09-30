<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Configuration\Exception\ApiKeyNotFoundException;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterAttributesToSetToIndexableServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\StandardAttributesProviderInterface;
use Klevu\PhpSDK\Exception\ApiExceptionInterface;
use Psr\Log\LoggerInterface;

class FilterAttributesToSetToIndexableService implements FilterAttributesToSetToIndexableServiceInterface
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
     * @var string[]|null
     */
    private ?array $standardAttributes = null;

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
     * @param string $type
     * @param int[]|null $attributeIds
     *
     * @return int[]
     */
    public function execute(array $magentoAttributesByApiKey, string $type, ?array $attributeIds = []): array
    {
        $return = [];
        foreach ($magentoAttributesByApiKey as $apiKey => $magentoAttributes) {
            $magentoAttributeIds = [];
            try {
                $magentoAttributes = $this->removeKlevuStandardAttributes(
                    allMagentoAttributes: $magentoAttributes,
                    apiKey: $apiKey,
                );
                $magentoAttributeIds = array_map(
                    callback: static fn (MagentoAttributeInterface $magentoAttribute) => (
                        $magentoAttribute->getAttributeId()
                        . '-' . $magentoAttribute->getApiKey()
                        . '-' . $type
                    ),
                    array: array_filter(
                        array: $magentoAttributes,
                        callback: static fn (MagentoAttributeInterface $magentoAttribute) => (
                        $magentoAttribute->isIndexable()
                        ),
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
            $klevuAttributes = array_filter(
                array: $this->getIndexingAttributes($type, $apiKey, $attributeIds),
                callback: static function (IndexingAttributeInterface $indexingAttribute) use (
                    $magentoAttributeIds,
                ): bool {
                    $klevuId = $indexingAttribute->getTargetId()
                        . '-' . $indexingAttribute->getApiKey()
                        . '-' . $indexingAttribute->getTargetAttributeType();

                    return in_array(needle: $klevuId, haystack: $magentoAttributeIds, strict: true)
                        && (
                            !$indexingAttribute->getIsIndexable()
                            || $indexingAttribute->getNextAction() === Actions::DELETE
                        );
                },
            );
            $return[] = array_map(
                callback: static fn (IndexingAttributeInterface $indexingAttribute) => (
                    (int)$indexingAttribute->getId()
                ),
                array: $klevuAttributes,
            );
        }

        return array_filter(array_values(array_merge(...$return)));
    }

    /**
     * @param string $type
     * @param string $apiKey
     * @param int[] $attributeIds
     *
     * @return IndexingAttributeInterface[]
     */
    private function getIndexingAttributes(string $type, string $apiKey, array $attributeIds): array
    {
        return $this->indexingAttributeProvider->get(
            attributeType: $type,
            apiKey: $apiKey,
            attributeIds: $attributeIds,
        );
    }

    /**
     * Remove standard attributes, they can not be deleted from Klevu
     *
     * @param MagentoAttributeInterface[] $allMagentoAttributes
     * @param string $apiKey
     *
     * @return MagentoAttributeInterface[]
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    private function removeKlevuStandardAttributes(array $allMagentoAttributes, string $apiKey): array
    {
        return array_filter(
            array: $allMagentoAttributes,
            callback: fn (MagentoAttributeInterface $magentoAttribute): bool => (
                !in_array(
                    needle: $magentoAttribute->getKlevuAttributeName(),
                    haystack: $this->getStandardAttributeCodes(apiKey: $apiKey),
                    strict: true,
                )
            ),
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
        if (null === $this->standardAttributes) {
            $this->standardAttributes = $this->standardAttributesProvider->getAttributeCodes(
                apiKey: $apiKey,
                includeAliases: true,
            );
        }

        return $this->standardAttributes;
    }
}
