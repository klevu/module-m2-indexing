<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Service\FilterAttributesToUpdateServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;

class FilterAttributesToUpdateService implements FilterAttributesToUpdateServiceInterface
{
    /**
     * @var IndexingAttributeProviderInterface
     */
    private readonly IndexingAttributeProviderInterface $indexingAttributeProvider;

    /**
     * @param IndexingAttributeProviderInterface $indexingAttributeProvider
     */
    public function __construct(
        IndexingAttributeProviderInterface $indexingAttributeProvider,
    ) {
        $this->indexingAttributeProvider = $indexingAttributeProvider;
    }

    /**
     * @param string $type
     * @param int[] $attributeIds
     * @param string[] $apiKeys
     *
     * @return int[]
     */
    public function execute(string $type, array $attributeIds, array $apiKeys): array
    {
        $attributeIdsByApiKey = [];
        foreach ($apiKeys as $apiKey) {
            $attributeIdsByApiKey[$apiKey] = $this->getIndexingAttributes($type, $apiKey, $attributeIds);
        }

        return array_filter(
            array_unique(
                array_merge(
                    ...array_values($attributeIdsByApiKey),
                ),
            ),
        );
    }

    /**
     * @param string $type
     * @param string $apiKey
     * @param int[] $attributeIds
     *
     * @return int[]
     */
    private function getIndexingAttributes(string $type, string $apiKey, array $attributeIds): array
    {
        $klevuAttributes = $this->indexingAttributeProvider->get(
            attributeType: $type,
            apiKey: $apiKey,
            attributeIds: $attributeIds,
        );

        return array_map(
            callback: static fn (IndexingAttributeInterface $indexingAttribute): int => ($indexingAttribute->getId()),
            array: array_filter(
                array: $klevuAttributes,
                callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                    $indexingAttribute->getIsIndexable()
                ),
            ),
        );
    }
}
