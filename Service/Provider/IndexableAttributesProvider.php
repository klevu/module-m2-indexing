<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexableAttributesProviderInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\FilterBuilderFactory;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilderFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterface;

class IndexableAttributesProvider implements IndexableAttributesProviderInterface
{
    /**
     * @var AttributeRepositoryInterface
     */
    private readonly AttributeRepositoryInterface $attributeRepository;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;
    /**
     * @var FilterBuilderFactory
     */
    private readonly FilterBuilderFactory $filterBuilderFactory;
    /**
     * @var FilterGroupBuilderFactory
     */
    private readonly FilterGroupBuilderFactory $filterGroupBuilderFactory;
    /**
     * @var DefaultIndexingAttributesProviderInterface
     */
    private readonly DefaultIndexingAttributesProviderInterface $defaultIndexingAttributesProvider;

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param FilterBuilderFactory $filterBuilderFactory
     * @param FilterGroupBuilderFactory $filterGroupBuilderFactory
     * @param DefaultIndexingAttributesProviderInterface $defaultIndexingAttributesProvider
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        FilterBuilderFactory $filterBuilderFactory,
        FilterGroupBuilderFactory $filterGroupBuilderFactory,
        DefaultIndexingAttributesProviderInterface $defaultIndexingAttributesProvider,
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->filterBuilderFactory = $filterBuilderFactory;
        $this->filterGroupBuilderFactory = $filterGroupBuilderFactory;
        $this->defaultIndexingAttributesProvider = $defaultIndexingAttributesProvider;
    }

    /**
     * @param string $apiKey
     *
     * @return string[]
     */
    public function getAttributeCodes(string $apiKey): array
    {
        return array_map(
            callback: static fn (AttributeInterface $attribute): string => ($attribute->getAttributeCode()),
            array: $this->get(apiKey: $apiKey),
        );
    }

    /**
     * @param string $apiKey
     *
     * @return AttributeInterface[]
     */
    public function get(string $apiKey): array
    {
        $searchResults = $this->attributeRepository->getList(
            entityTypeCode: ProductAttributeInterface::ENTITY_TYPE_CODE,
            searchCriteria: $this->getSearchCriteria(apiKey: $apiKey),
        );

        return $searchResults->getItems();
    }

    /**
     * @param string $apiKey
     *
     * @return SearchCriteriaInterface
     */
    private function getSearchCriteria(string $apiKey): SearchCriteriaInterface
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = $this->filterBuilderFactory->create();
        /** @var FilterGroupBuilder $filterGroupBuilder */
        $filterGroupBuilder = $this->filterGroupBuilderFactory->create();
        $filterGroupBuilder->addFilter(
            filter: $this->getFilterForCoreAttributes(filterBuilder: $filterBuilder, apiKey: $apiKey),
        );
        $filterGroupBuilder->addFilter(
            filter: $this->getFilterForIndexableCustomAttributes(filterBuilder: $filterBuilder),
        );
        /** @var FilterGroup $filterGroup */
        $filterGroup = $filterGroupBuilder->create();
        $searchCriteriaBuilder->setFilterGroups(filterGroups: [$filterGroup]);

        return $searchCriteriaBuilder->create();
    }

    /**
     * @param FilterBuilder $filterBuilder
     * @param string $apiKey
     *
     * @return Filter
     */
    private function getFilterForCoreAttributes(FilterBuilder $filterBuilder, string $apiKey): Filter
    {
        $filterBuilder->setField(field: AttributeInterface::ATTRIBUTE_CODE);
        $filterBuilder->setValue(value: $this->getStandardKlevuAttributes(apiKey: $apiKey));
        $filterBuilder->setConditionType(conditionType: 'in');

        return $filterBuilder->create();
    }

    /**
     * @param FilterBuilder $filterBuilder
     *
     * @return Filter
     */
    private function getFilterForIndexableCustomAttributes(FilterBuilder $filterBuilder): Filter
    {
        $filterBuilder->setField(field: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE);
        $filterBuilder->setValue(value: (string)IndexType::NO_INDEX->value);
        $filterBuilder->setConditionType(conditionType: 'neq');

        return $filterBuilder->create();
    }

    /**
     * @param string $apiKey
     *
     * @return string[]
     */
    private function getStandardKlevuAttributes(string $apiKey): array
    {
        $standardAttributes = array_filter(
            array: $this->defaultIndexingAttributesProvider->get(apiKey: $apiKey),
            callback: static fn (IndexType $indexType): bool => (
                $indexType->value === IndexType::NO_INDEX->value
            ),
        );

        return array_keys($standardAttributes);
    }
}
