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
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingApi\Service\Provider\DuplicateAttributeMappingProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\StandardAttributesProviderInterface;
use Klevu\PhpSDK\Exception\ApiExceptionInterface;
use Magento\Framework\DB\Select;
use Psr\Log\LoggerInterface;

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
     * @var StandardAttributesProviderInterface
     */
    private readonly StandardAttributesProviderInterface $standardAttributesProvider;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var array<string, MagentoToKlevuAttributeMapperInterface>
     */
    private array $attributeMappers = [];

    /**
     * @param IndexingAttributeProviderInterface $indexingAttributeProvider
     * @param IndexingAttributeCollectionFactory $indexingAttributeCollectionFactory
     * @param StandardAttributesProviderInterface $standardAttributesProvider
     * @param LoggerInterface $logger
     * @param array<string, MagentoToKlevuAttributeMapperInterface> $attributeMappers
     */
    public function __construct(
        IndexingAttributeProviderInterface $indexingAttributeProvider,
        IndexingAttributeCollectionFactory $indexingAttributeCollectionFactory,
        StandardAttributesProviderInterface $standardAttributesProvider,
        LoggerInterface $logger,
        array $attributeMappers = [],
    ) {
        $this->indexingAttributeProvider = $indexingAttributeProvider;
        $this->indexingAttributeCollectionFactory = $indexingAttributeCollectionFactory;
        $this->standardAttributesProvider = $standardAttributesProvider;
        $this->logger = $logger;
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

        $standardAttributes = $this->getStandardAttributeCodes($apiKey);
        $attributeCodes = array_merge(
            $standardAttributes,
            array_map(
                callback: static fn (IndexingAttributeInterface $indexingAttribute): string => (
                    $indexingAttribute->getTargetCode()
                ),
                array: $attributeIndexingRecords,
            ),
        );

        $mappedAttributeNames = array_map(
            callback: function (string $targetCode) use ($attributeType, $apiKey): string {
                if (isset($this->attributeMappers[$attributeType])) {
                    try {
                        $targetCode = $this->attributeMappers[$attributeType]->getByCode(
                            attributeCode: $targetCode,
                            apiKey: $apiKey,
                        );
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

    /**
     * @param string $apiKey
     *
     * @return string[]
     */
    private function getStandardAttributeCodes(string $apiKey): array
    {
        $return = [];
        try {
            $return = $this->standardAttributesProvider->getAttributeCodes(
                apiKey: $apiKey,
                includeAliases: true,
            );
        } catch (ApiExceptionInterface $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}, ApiKey: {apiKey}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                    'apiKey' => $apiKey,
                ],
            );
        }

        return $return;
    }
}
