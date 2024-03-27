<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\StandardAttribute;
use Klevu\IndexingApi\Service\FilterAttributesToDeleteServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;

class FilterAttributesToDeleteService implements FilterAttributesToDeleteServiceInterface
{
    /**
     * @var IndexingAttributeProviderInterface
     */
    private readonly IndexingAttributeProviderInterface $indexingAttributeProvider;

    /**
     * @param IndexingAttributeProviderInterface $indexingAttributeProvider
     */
    public function __construct(IndexingAttributeProviderInterface $indexingAttributeProvider)
    {
        $this->indexingAttributeProvider = $indexingAttributeProvider;
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
            $indexingAttributes = $this->getIndexingAttributes($type, $apiKey, $attributeIds);
            $return[] = $this->getIndexingAttributesNoLongerIndexable($type, $magentoAttributes, $indexingAttributes);
            $return[] = $this->getIndexingAttributesNoLongerExists($type, $magentoAttributes, $indexingAttributes);
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
     *
     * @return int[]
     */
    private function getIndexingAttributesNoLongerIndexable(
        string $type,
        array $magentoAttributes,
        array $indexingAttributes,
    ): array {
        $magentoAttributes = $this->removeKlevuStandardAttributes($magentoAttributes);

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
                    && $indexingAttribute->getIsIndexable();
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

                return !in_array(needle: $klevuId, haystack: $magentoAttributeIds, strict: true);
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
     * @param MagentoAttributeInterface[] $allMagentoAttributes
     *
     * @return MagentoAttributeInterface[]
     */
    private function removeKlevuStandardAttributes(array $allMagentoAttributes): array
    {
        return array_filter(
            array: $allMagentoAttributes,
            callback: static fn (MagentoAttributeInterface $magentoAttribute): bool => (
                // Remove standard attributes, they can not be deleted from Klevu
                !in_array(
                    needle: $magentoAttribute->getKlevuAttributeName(),
                    haystack: StandardAttribute::values(),
                    strict: true,
                )
            ),
        );
    }
}
