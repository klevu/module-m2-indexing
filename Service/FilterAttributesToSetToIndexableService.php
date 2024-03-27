<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\StandardAttribute;
use Klevu\IndexingApi\Service\FilterAttributesToSetToIndexableServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;

class FilterAttributesToSetToIndexableService implements FilterAttributesToSetToIndexableServiceInterface
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
     * @param int[]|null $attributeIds
     *
     * @return int[]
     */
    public function execute(array $magentoAttributesByApiKey, string $type, ?array $attributeIds = []): array
    {
        $return = [];
        foreach ($magentoAttributesByApiKey as $apiKey => $magentoAttributes) {
            $magentoAttributes = $this->removeKlevuStandardAttributes($magentoAttributes);
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
            $klevuAttributes = array_filter(
                array: $this->getIndexingAttributes($type, $apiKey, $attributeIds),
                callback: static function (IndexingAttributeInterface $indexingAttribute) use (
                    $magentoAttributeIds,
                ): bool {
                    $klevuId = $indexingAttribute->getTargetId()
                        . '-' . $indexingAttribute->getApiKey()
                        . '-' . $indexingAttribute->getTargetAttributeType();

                    return in_array(needle: $klevuId, haystack: $magentoAttributeIds, strict: true)
                        && !$indexingAttribute->getIsIndexable();
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
