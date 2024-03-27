<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\StandardAttribute;
use Klevu\IndexingApi\Service\FilterAttributesToAddServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;

class FilterAttributesToAddService implements FilterAttributesToAddServiceInterface
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

            $return[$apiKey] = array_filter(
                array: $magentoAttributes,
                callback: static fn (MagentoAttributeInterface $magentoAttribute) => (
                    // Remove standard attributes, they already exist in klevu we don't need to create them again
                    !in_array(needle: $magentoAttribute->getKlevuAttributeName(),
                        haystack: StandardAttribute::values(),
                        strict: true,
                    )
                    && !in_array(
                        needle: (int)$magentoAttribute->getAttributeId(),
                        haystack: $klevuAttributeIds,
                        strict: true,
                    )
                ),
            );
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
}
