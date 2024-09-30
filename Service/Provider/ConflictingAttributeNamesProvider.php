<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingApi\Service\Provider\ConflictingAttributeNamesProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;

class ConflictingAttributeNamesProvider implements ConflictingAttributeNamesProviderInterface
{
    /**
     * @var IndexingAttributeProviderInterface
     */
    private readonly IndexingAttributeProviderInterface $indexingAttributeProvider;
    /**
     * @var array<string, MagentoToKlevuAttributeMapperInterface>
     */
    private array $attributeMappers = [];

    /**
     * @param IndexingAttributeProviderInterface $indexingAttributeProvider
     * @param array<string, MagentoToKlevuAttributeMapperInterface> $attributeMappers
     */
    public function __construct(
        IndexingAttributeProviderInterface $indexingAttributeProvider,
        array $attributeMappers,
    ) {
        $this->indexingAttributeProvider = $indexingAttributeProvider;
        array_walk($attributeMappers, [$this, 'addAttributeMapper']);
    }

    /**
     * @param string $apiKey
     *
     * @return array<string, string[]> attributeName => entityTypes
     */
    public function getForApiKey(string $apiKey): array
    {
        $indexingAttributes = array_merge(
            $this->indexingAttributeProvider->get(
                apiKey: $apiKey,
                nextAction: Actions::ADD,
                isIndexable: true,
            ),
            $this->indexingAttributeProvider->get(
                apiKey: $apiKey,
                nextAction: Actions::UPDATE,
                isIndexable: true,
            ),
            $this->indexingAttributeProvider->get(
                apiKey: $apiKey,
                nextAction: Actions::NO_ACTION,
                isIndexable: true,
            ),
        );

        $mappedAttributes = array_map(
            callback: function (IndexingAttributeInterface $indexingAttribute) use ($apiKey): array {
                $targetAttributeType = $indexingAttribute->getTargetAttributeType();
                $targetCode = $indexingAttribute->getTargetCode();

                if (isset($this->attributeMappers[$targetAttributeType])) {
                    try {
                        $targetCode = $this->attributeMappers[$targetAttributeType]->getByCode(
                            attributeCode: $targetCode,
                            apiKey: $apiKey,
                        );
                    } catch (AttributeMappingMissingException) {
                        // This is fine
                    }
                }

                return [
                    'entityType' => $targetAttributeType,
                    'attributeName' => $targetCode,
                ];
            },
            array: $indexingAttributes,
        );

        $attributeNameToAttributeTypes = [];
        foreach ($mappedAttributes as $mappedAttribute) {
            $attributeNameToAttributeTypes[$mappedAttribute['attributeName']] ??= [];
            if (
                in_array(
                    needle: $mappedAttribute['entityType'],
                    haystack: $attributeNameToAttributeTypes[$mappedAttribute['attributeName']],
                    strict: true,
                )
            ) {
                continue;
            }
            $attributeNameToAttributeTypes[$mappedAttribute['attributeName']][] = $mappedAttribute['entityType'];
        }

        return array_filter(
            array: $attributeNameToAttributeTypes,
            callback: static fn (array $attributeTypes): bool => (count($attributeTypes) > 1),
        );
    }

    /**
     * @param MagentoToKlevuAttributeMapperInterface $attributeMapper
     * @param string $entityType
     *
     * @return void
     */
    private function addAttributeMapper(
        MagentoToKlevuAttributeMapperInterface $attributeMapper,
        string $entityType,
    ): void {
        $this->attributeMappers[$entityType] = $attributeMapper;
    }
}
