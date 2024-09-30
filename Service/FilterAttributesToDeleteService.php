<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Configuration\Exception\ApiKeyNotFoundException;
use Klevu\Indexing\Exception\StoreApiKeyException;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterAttributesToDeleteServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\MagentoToKlevuAttributeMapperProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sdk\AttributesProviderInterface;
use Klevu\IndexingApi\Service\Provider\StandardAttributesProviderInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface;
use Klevu\PhpSDK\Exception\ApiExceptionInterface;
use Psr\Log\LoggerInterface;

class FilterAttributesToDeleteService implements FilterAttributesToDeleteServiceInterface
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
     * @var AttributesProviderInterface
     */
    private readonly AttributesProviderInterface $attributesProvider;
    /**
     * @var MagentoToKlevuAttributeMapperProviderInterface
     */
    private readonly MagentoToKlevuAttributeMapperProviderInterface $magentoToKlevuAttributeMapperProvider;
    /**
     * @var string[]|null
     */
    private ?array $standardAttributes = null;
    /**
     * @var string[]
     */
    private array $klevuAttributes = [];

    /**
     * @param IndexingAttributeProviderInterface $indexingAttributeProvider
     * @param StandardAttributesProviderInterface $standardAttributesProvider
     * @param LoggerInterface $logger
     * @param AttributesProviderInterface $attributesProvider
     * @param MagentoToKlevuAttributeMapperProviderInterface $magentoToKlevuAttributeMapperProvider
     */
    public function __construct(
        IndexingAttributeProviderInterface $indexingAttributeProvider,
        StandardAttributesProviderInterface $standardAttributesProvider,
        LoggerInterface $logger,
        AttributesProviderInterface $attributesProvider,
        MagentoToKlevuAttributeMapperProviderInterface $magentoToKlevuAttributeMapperProvider,
    ) {
        $this->indexingAttributeProvider = $indexingAttributeProvider;
        $this->standardAttributesProvider = $standardAttributesProvider;
        $this->logger = $logger;
        $this->attributesProvider = $attributesProvider;
        $this->magentoToKlevuAttributeMapperProvider = $magentoToKlevuAttributeMapperProvider;
    }

    /**
     *
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
            $indexingAttributes = $this->getIndexingAttributes(
                type: $type,
                apiKey: $apiKey,
                attributeIds: $attributeIds,
            );
            try {
                $return[] = $this->getIndexingAttributesNoLongerIndexable(
                    type: $type,
                    magentoAttributes: $magentoAttributes,
                    indexingAttributes: $indexingAttributes,
                    apiKey: $apiKey,
                );
                $return[] = $this->getIndexingAttributesAttributeTypeChanged(
                    type: $type,
                    magentoAttributes: $magentoAttributes,
                    indexingAttributes: $indexingAttributes,
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
            $return[] = $this->getIndexingAttributesNoLongerExists(
                type: $type,
                magentoAttributes: $magentoAttributes,
                indexingAttributes: $indexingAttributes,
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
     * @param string $type
     * @param MagentoAttributeInterface[] $magentoAttributes
     * @param IndexingAttributeInterface[] $indexingAttributes
     * @param string $apiKey
     *
     * @return int[]
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    private function getIndexingAttributesNoLongerIndexable(
        string $type,
        array $magentoAttributes,
        array $indexingAttributes,
        string $apiKey,
    ): array {
        $magentoAttributes = $this->removeKlevuStandardAttributes(
            allMagentoAttributes: $magentoAttributes,
            apiKey: $apiKey,
        );

        $magentoAttributeIds = array_map(
            callback: static fn (MagentoAttributeInterface $magentoAttribute): string => (
                $magentoAttribute->getAttributeId()
                . '-' . $magentoAttribute->getApiKey()
                . '-' . $type
            ),
            array: array_filter(
                array: $magentoAttributes,
                callback: static fn (MagentoAttributeInterface $magentoAttribute): bool => (
                    !$magentoAttribute->isIndexable()
                ),
            ),
        );
        $klevuAttributes = array_filter(
            array: $indexingAttributes,
            callback: static function (IndexingAttributeInterface $indexingAttribute) use ($magentoAttributeIds): bool {
                $klevuId = $indexingAttribute->getTargetId()
                    . '-' . $indexingAttribute->getApiKey()
                    . '-' . $indexingAttribute->getTargetAttributeType();

                return in_array(needle: $klevuId, haystack: $magentoAttributeIds, strict: true)
                    && $indexingAttribute->getIsIndexable()
                    && $indexingAttribute->getLastAction() !== Actions::NO_ACTION;
            },
        );

        return array_map(
            callback: static fn (IndexingAttributeInterface $indexingAttribute): int => (
                (int)$indexingAttribute->getId()
            ),
            array: $klevuAttributes,
        );
    }

    /**
     * @param string $type
     * @param MagentoAttributeInterface[] $magentoAttributes
     * @param IndexingAttributeInterface[] $indexingAttributes
     *
     * @return int[]
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    private function getIndexingAttributesAttributeTypeChanged(
        string $type,
        array $magentoAttributes,
        array $indexingAttributes,
    ): array {
        $magentoAttributeIds = array_map(
            callback: static fn (MagentoAttributeInterface $magentoAttribute): string => (
                $magentoAttribute->getAttributeId()
                . '-' . $magentoAttribute->getApiKey()
                . '-' . $type
                . '-' . $magentoAttribute->getKlevuAttributeType()?->value
            ),
            array: $magentoAttributes,
        );
        $klevuAttributes = array_filter(
            array: $indexingAttributes,
            callback: function (IndexingAttributeInterface $indexingAttribute) use ($magentoAttributeIds): bool {
                if (!$indexingAttribute->getIsIndexable()) {
                    return false;
                }
                $klevuId = $indexingAttribute->getTargetId()
                    . '-' . $indexingAttribute->getApiKey()
                    . '-' . $indexingAttribute->getTargetAttributeType()
                    . '-' . $this->getAttributeType(
                        $indexingAttribute->getApiKey(),
                        $indexingAttribute->getTargetCode(),
                        $indexingAttribute->getTargetAttributeType(),
                    );

                return !str_ends_with(haystack: $klevuId, needle: '-')
                    && !in_array(
                        needle: $klevuId,
                        haystack: $magentoAttributeIds,
                        strict: true,
                    )
                    && !in_array(
                        needle: $indexingAttribute->getLastAction(),
                        haystack: [Actions::NO_ACTION, Actions::DELETE],
                        strict: true,
                    );
            },
        );

        return array_map(
            callback: static fn (IndexingAttributeInterface $indexingAttribute): int => (
                (int)$indexingAttribute->getId()
            ),
            array: $klevuAttributes,
        );
    }

    /**
     * @param string $type
     * @param MagentoAttributeInterface[] $magentoAttributes
     * @param IndexingAttributeInterface[] $indexingAttributes
     *
     * @return int[]
     */
    private function getIndexingAttributesNoLongerExists(
        string $type,
        array $magentoAttributes,
        array $indexingAttributes,
    ): array {
        $magentoAttributeIds = array_map(
            callback: static fn (MagentoAttributeInterface $magentoAttribute): string => (
                $magentoAttribute->getAttributeId()
                . '-' . $magentoAttribute->getApiKey()
                . '-' . $type
            ),
            array: $magentoAttributes,
        );
        $klevuAttributes = array_filter(
            array: $indexingAttributes,
            callback: static function (IndexingAttributeInterface $indexingAttribute) use ($magentoAttributeIds): bool {
                $klevuId = $indexingAttribute->getTargetId()
                    . '-' . $indexingAttribute->getApiKey()
                    . '-' . $indexingAttribute->getTargetAttributeType();

                return !in_array(needle: $klevuId, haystack: $magentoAttributeIds, strict: true)
                    && $indexingAttribute->getIsIndexable()
                    && $indexingAttribute->getLastAction() !== Actions::NO_ACTION;
            },
        );

        return array_map(
            callback: static fn (IndexingAttributeInterface $indexingAttribute): int => (
                (int)$indexingAttribute->getId()
            ),
            array: array_filter(
                array: $klevuAttributes,
                callback: static fn (IndexingAttributeInterface $attribute) => $attribute->getIsIndexable(),
            ),
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

    /**
     * @param string $apiKey
     * @param string $attributeCode
     * @param string $type
     *
     * @return string
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    private function getAttributeType(string $apiKey, string $attributeCode, string $type): string
    {
        if (!($this->klevuAttributes[$apiKey] ?? null)) {
            try {
                $this->klevuAttributes[$apiKey] = $this->attributesProvider->get(apiKey: $apiKey);
            } catch (StoreApiKeyException $exception) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );

                return '';
            }
        }
        $attributeIterator = $this->klevuAttributes[$apiKey];
        $mapper = $this->magentoToKlevuAttributeMapperProvider->getByType($type);

        $attributes = $attributeIterator->filter(
            callback: static fn (AttributeInterface $attribute): bool => (
                $attributeCode === $mapper->reverseForCode(
                    attributeName: $attribute->getAttributeName(),
                    apiKey: $apiKey,
                )
            ),
        );
        $attribute = $attributes->current();

        return $attribute?->getDatatype() ?? '';
    }
}
