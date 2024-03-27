<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;

class IndexingAttributeProvider implements IndexingAttributeProviderInterface
{
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;
    /**
     * @var IndexingAttributeRepositoryInterface
     */
    private IndexingAttributeRepositoryInterface $indexingAttributeRepository;

    /**
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param IndexingAttributeRepositoryInterface $indexingAttributeRepository
     */
    public function __construct(
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        IndexingAttributeRepositoryInterface $indexingAttributeRepository,
    ) {
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->indexingAttributeRepository = $indexingAttributeRepository;
    }

    /**
     *
     * @param string|null $attributeType
     * @param string|null $apiKey
     * @param int[]|null $attributeIds
     * @param Actions|null $nextAction
     * @param bool|null $isIndexable
     *
     * @return IndexingAttributeInterface[]
     */
    public function get(
        ?string $attributeType = null,
        ?string $apiKey = null,
        ?array $attributeIds = [],
        ?Actions $nextAction = null,
        ?bool $isIndexable = null,
    ): array {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        if ($attributeType) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingAttribute::TARGET_ATTRIBUTE_TYPE,
                value: $attributeType,
            );
        }
        if ($apiKey) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingAttribute::API_KEY,
                value: $apiKey,
            );
        }
        if ($attributeIds) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingAttribute::TARGET_ID,
                value: $attributeIds,
                conditionType: 'in',
            );
        }
        if ($nextAction) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingAttribute::NEXT_ACTION,
                value: $nextAction->value,
            );
        }
        if (null !== $isIndexable) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingAttribute::IS_INDEXABLE,
                value: $isIndexable,
            );
        }
        $searchCriteria = $searchCriteriaBuilder->create();
        $indexingAttributeSearchResults = $this->indexingAttributeRepository->getList($searchCriteria);

        return $indexingAttributeSearchResults->getItems();
    }
}
