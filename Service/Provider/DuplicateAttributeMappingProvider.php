<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Model\ResourceModel\IndexingAttribute\CollectionFactory as IndexingAttributeCollectionFactory;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\StandardAttribute;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingApi\Service\Provider\DuplicateAttributeMappingProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Magento\Framework\DB\Select;

class DuplicateAttributeMappingProvider implements DuplicateAttributeMappingProviderInterface
{
    /**
     * @var IndexingAttributeProviderInterface
     */
    private readonly IndexingAttributeProviderInterface $indexingAttributeProvider;
    /**
     * @var IndexingAttributeCollectionFactory
     */
    private readonly IndexingAttributeCollectionFactory $indexingAttributeCollectionFactory;
    /**
     * @var array<string, MagentoToKlevuAttributeMapperInterface>
     */
    private array $attributeMappers = [];

    /**
     * @param IndexingAttributeProviderInterface $indexingAttributeProvider
     * @param IndexingAttributeCollectionFactory $indexingAttributeCollectionFactory
     * @param array<string, MagentoToKlevuAttributeMapperInterface> $attributeMappers
     */
    public function __construct(
        IndexingAttributeProviderInterface $indexingAttributeProvider,
        IndexingAttributeCollectionFactory $indexingAttributeCollectionFactory,
        array $attributeMappers = [],
    ) {
        $this->indexingAttributeProvider = $indexingAttributeProvider;
        $this->indexingAttributeCollectionFactory = $indexingAttributeCollectionFactory;
        array_walk($attributeMappers, [$this, 'addAttributeMapper']);
    }

    /**
     * @param string $apiKey
     *
     * @return array<string, array<string, int>> attributeType => <attributeName => count>
     */
    public function get(string $apiKey): array
    {
        $attributeTypes = $this->getAllAttributeTypes();

        return array_filter(
            array_combine(
                keys: $attributeTypes,
                values: array_map(
                    callback: fn (string $attributeType): array => $this->getForAttributeType($attributeType, $apiKey),
                    array: $attributeTypes,
                ),
            ),
        );
    }

    /**
     * @param string $attributeType
     * @param string $apiKey
     *
     * @return array<string, int> attributeName => count
     */
    private function getForAttributeType(string $attributeType, string $apiKey): array
    {
        $attributeIndexingRecords = array_merge(
            $this->indexingAttributeProvider->get(
                attributeType: $attributeType,
                apiKey: $apiKey,
                nextAction: Actions::ADD,
                isIndexable: true,
            ),
            $this->indexingAttributeProvider->get(
                attributeType: $attributeType,
                apiKey: $apiKey,
                nextAction: Actions::UPDATE,
                isIndexable: true,
            ),
            $this->indexingAttributeProvider->get(
                attributeType: $attributeType,
                apiKey: $apiKey,
                nextAction: Actions::NO_ACTION,
                isIndexable: true,
            ),
        );

        $attributeCodes = array_merge(
            StandardAttribute::values(),
            array_map(
                callback: static fn (IndexingAttributeInterface $indexingAttribute): string => (
                    $indexingAttribute->getTargetCode()
                ),
                array: $attributeIndexingRecords,
            ),
        );

        $mappedAttributeNames = array_map(
            callback: function (string $targetCode) use ($attributeType): string {
                if (isset($this->attributeMappers[$attributeType])) {
                    try {
                        $targetCode = $this->attributeMappers[$attributeType]->getByCode($targetCode);
                    } catch (AttributeMappingMissingException) {
                        // This is fine
                    }
                }

                return $targetCode;
            },
            array: $attributeCodes,
        );

        return array_filter(
            array: array_count_values($mappedAttributeNames),
            callback: static fn (int $count): bool => ($count > 1),
        );
    }

    /**
     * @return string[]
     */
    private function getAllAttributeTypes(): array
    {
        $indexingAttributeCollection = $this->indexingAttributeCollectionFactory->create();
        $select = $indexingAttributeCollection->getSelect();
        $select->reset(Select::COLUMNS);
        $select->columns([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE,
        ]);
        $select->distinct();

        return array_column(
            $indexingAttributeCollection->getData(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE,
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
