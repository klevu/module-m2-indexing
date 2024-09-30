<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterAttributesToSetToNotIndexableServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;

class FilterAttributesToSetToNotIndexableService implements FilterAttributesToSetToNotIndexableServiceInterface
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
     * @param MagentoAttributeInterface[][] $magentoAttributesByApiKey
     * @param string $type
     * @param int[] $attributeIds
     *
     * @return int[]
     */
    public function execute(array $magentoAttributesByApiKey, string $type, array $attributeIds = []): array
    {
        $return = [];
        foreach ($magentoAttributesByApiKey as $apiKey => $magentoAttributes) {
            $indexingAttributes = $this->getIndexingAttributes($type, $apiKey, $attributeIds);
            $return[] = $this->getKlevuAttributesNoLongerIndexable($type, $magentoAttributes, $indexingAttributes);
            $return[] = $this->getKlevuAttributesNoLongerExist($type, $magentoAttributes, $indexingAttributes);
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
    private function getKlevuAttributesNoLongerIndexable(
        string $type,
        array $magentoAttributes,
        array $indexingAttributes,
    ): array {
        $magentoAttributeIds = array_map(
            callback: static fn (MagentoAttributeInterface $magentoAttribute) => (
                $magentoAttribute->getAttributeId()
                . '-' . $magentoAttribute->getApiKey()
                . '-' . $type
            ),
            array: array_filter(
                array: $magentoAttributes,
                callback: static fn (MagentoAttributeInterface $magentoAttribute) => (
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
                    && in_array($indexingAttribute->getLastAction(), [Actions::NO_ACTION, Actions::DELETE], true);
            },
        );

        return array_map(
            callback: static fn (IndexingAttributeInterface $indexingAttribute) => (
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
    private function getKlevuAttributesNoLongerExist(
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
                    && in_array($indexingAttribute->getLastAction(), [Actions::NO_ACTION, Actions::DELETE], true);
            },
        );

        return array_map(
            callback: static fn (IndexingAttributeInterface $indexingAttribute): int => (
                (int)$indexingAttribute->getId()
            ),
            array: $klevuAttributes,
        );
    }
}
