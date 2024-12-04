<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\CollectionFactory;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\FilterBuilderFactory;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilderFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Api\SortOrderBuilderFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

class IndexingEntityProvider implements IndexingEntityProviderInterface
{
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;
    /**
     * @var IndexingEntityRepositoryInterface
     */
    private readonly IndexingEntityRepositoryInterface $indexingEntityRepository;
    /**
     * @var FilterGroupBuilderFactory
     */
    private readonly FilterGroupBuilderFactory $filterGroupBuilderFactory;
    /**
     * @var FilterBuilderFactory
     */
    private readonly FilterBuilderFactory $filterBuilderFactory;
    /**
     * @var CollectionFactory
     */
    private readonly CollectionFactory $collectionFactory;
    /**
     * @var ResourceConnection
     */
    private readonly ResourceConnection $resourceConnection;
    /**
     * @var SortOrderBuilderFactory
     */
    private readonly SortOrderBuilderFactory $sortOrderBuilderFactory;

    /**
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param IndexingEntityRepositoryInterface $indexingEntityRepository
     * @param FilterGroupBuilderFactory $filterGroupBuilderFactory
     * @param FilterBuilderFactory $filterBuilderFactory
     * @param CollectionFactory $collectionFactory
     * @param ResourceConnection $resourceConnection
     * @param SortOrderBuilderFactory $sortOrderBuilderFactory
     */
    public function __construct(
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        IndexingEntityRepositoryInterface $indexingEntityRepository,
        FilterGroupBuilderFactory $filterGroupBuilderFactory,
        FilterBuilderFactory $filterBuilderFactory,
        CollectionFactory $collectionFactory,
        ResourceConnection $resourceConnection,
        SortOrderBuilderFactory $sortOrderBuilderFactory,
    ) {
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->indexingEntityRepository = $indexingEntityRepository;
        $this->filterGroupBuilderFactory = $filterGroupBuilderFactory;
        $this->filterBuilderFactory = $filterBuilderFactory;
        $this->collectionFactory = $collectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->sortOrderBuilderFactory = $sortOrderBuilderFactory;
    }

    /**
     * Note: as sortOrder is required for pagination to work correctly,
     *  if $pageSize is provided then $sorting is ignored and collection is sorted by IndexingEntity::ENTITY_ID
     *
     * @param string|null $entityType
     * @param string[]|null $apiKeys
     * @param int[]|null $entityIds
     * @param Actions|null $nextAction
     * @param bool|null $isIndexable
     * @param array<string, string>|null $sorting [SortOrder::DIRECTION => SortOrder::SORT_ASC, SortOrder::FIELD => '']
     * @param int|null $pageSize
     * @param int|null $startFrom
     * @param string[]|null $entitySubtypes
     *
     * @return IndexingEntityInterface[]
     */
    public function get(
        ?string $entityType = null,
        ?array $apiKeys = [],
        ?array $entityIds = [],
        ?Actions $nextAction = null,
        ?bool $isIndexable = null,
        ?array $sorting = [],
        ?int $pageSize = null,
        ?int $startFrom = 1,
        ?array $entitySubtypes = [],
    ): array {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
        if ($entityIds) {
            /** @var FilterBuilder $filterBuilder */
            $filterBuilder = $this->filterBuilderFactory->create();
            $filterBuilder->setField(IndexingEntity::TARGET_ID);
            $filterBuilder->setValue($entityIds);
            $filterBuilder->setConditionType('in');
            $filter1 = $filterBuilder->create();

            /** @var FilterGroupBuilder $filterGroupBuilder */
            $filterGroupBuilder = $this->filterGroupBuilderFactory->create();
            $filterGroupBuilder->addFilter($filter1);

            /** @var FilterGroup $filterOr */
            $filterOr = $filterGroupBuilder->create();

            $searchCriteriaBuilder->setFilterGroups([$filterOr]);
        }
        if ($entitySubtypes) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingEntity::TARGET_ENTITY_SUBTYPE,
                value: $entitySubtypes,
                conditionType: 'in',
            );
        }
        if ($entityType) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingEntity::TARGET_ENTITY_TYPE,
                value: $entityType,
            );
        }
        if ($apiKeys) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingEntity::API_KEY,
                value: $apiKeys,
                conditionType: 'in',
            );
        }
        if ($nextAction) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingEntity::NEXT_ACTION,
                value: $nextAction->value,
            );
        }
        if (null !== $isIndexable) {
            $searchCriteriaBuilder->addFilter(
                field: IndexingEntity::IS_INDEXABLE,
                value: $isIndexable,
            );
        }
        if (null !== $pageSize) {
            $searchCriteriaBuilder->setPageSize($pageSize);
            $searchCriteriaBuilder->addFilter(
                field: IndexingEntity::ENTITY_ID,
                value: $startFrom,
                conditionType: 'gteq',
            );
            /** @var SortOrderBuilder $sortOrderBuilder */
            $sortOrderBuilder = $this->sortOrderBuilderFactory->create();
            $sortOrderBuilder->setField(IndexingEntity::ENTITY_ID);
            $sortOrderBuilder->setDirection(SortOrder::SORT_ASC);
            $searchCriteriaBuilder->addSortOrder(sortOrder: $sortOrderBuilder->create());
        } elseif (($sorting[SortOrder::FIELD] ?? null) && ($sorting[SortOrder::DIRECTION] ?? null)) {
            /** @var SortOrderBuilder $sortOrderBuilder */
            $sortOrderBuilder = $this->sortOrderBuilderFactory->create();
            $sortOrderBuilder->setField($sorting[SortOrder::FIELD]);
            $sortOrderBuilder->setDirection(strtoupper($sorting[SortOrder::DIRECTION]));
            $searchCriteriaBuilder->addSortOrder(sortOrder: $sortOrderBuilder->create());
        }
        $searchCriteria = $searchCriteriaBuilder->create();
        $klevuEntitySearchResult = $this->indexingEntityRepository->getList($searchCriteria);

        return $klevuEntitySearchResult->getItems();
    }

    /**
     * @param string|null $entityType
     * @param string|null $apiKey
     * @param int[][]|null $pairs
     *
     * @return Collection
     */
    public function getForTargetParentPairs(
        ?string $entityType = null,
        ?string $apiKey = null,
        ?array $pairs = [],
    ): Collection {
        // Can't use repository and searchCriteria here due to nature of required query structure.
        // Required: (a=b AND c=d) OR (e=f AND g=h).
        // only "(a=b OR c=d) AND (e=f OR g=h)" and "a=b AND c=d AND e=f AND g=h" are possible with searchCriteria.

        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $select = $collection->getSelect();
        $select->reset(Select::COLUMNS);
        $collection->addFieldToSelect('*');
        if ($pairs) {
            $this->addTargetAndParentFieldsToFilter($pairs, $collection);
        }
        if ($apiKey) {
            $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => $apiKey]);
        }
        if ($entityType) {
            $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => $entityType]);
        }

        return $collection;
    }

    /**
     * @param string|null $entityType
     * @param string|null $apiKey
     * @param Actions|null $nextAction
     * @param bool|null $isIndexable
     *
     * @return int
     */
    public function count(
        ?string $entityType = null,
        ?string $apiKey = null,
        ?Actions $nextAction = null,
        ?bool $isIndexable = null,
    ): int {
        return $this->indexingEntityRepository->count(
            entityType: $entityType,
            apiKey: $apiKey,
            nextAction: $nextAction,
            isIndexable: $isIndexable,
        );
    }

    /**
     * @param string $apiKey
     *
     * @return string[]
     */
    public function getTypes(string $apiKey): array
    {
        return $this->indexingEntityRepository->getUniqueEntityTypes(apiKey: $apiKey);
    }

    /**
     * @param int[][] $pairs
     * @param Collection $collection
     *
     * @return void
     */
    private function addTargetAndParentFieldsToFilter(array $pairs, Collection $collection): void
    {
        $connection = $this->resourceConnection->getConnection();
        $conditions = [];
        foreach ($pairs as $pair) {
            $conditions[] = $this->buildConditionForEntityPairs($pair, $connection);
        }
        $select = $collection->getSelect();
        $select->where( // We have already escaped input
            cond: implode(
                separator: ' OR ',
                array: $conditions, // phpcs:ignore Security.Drupal7.DynQueries.D7DynQueriesDirectVar
            ),
        );
    }

    /**
     * @param int[] $pair
     * @param AdapterInterface $connection
     *
     * @return string
     */
    private function buildConditionForEntityPairs(array $pair, AdapterInterface $connection): string
    {
        $condition = $connection->quoteInto(
            text: '(`target_id` = ? AND ',
            value: $pair[IndexingEntity::TARGET_ID],
        );
        $condition .= (($pair[IndexingEntity::TARGET_PARENT_ID] ?? null)
            ? $connection->quoteInto(
                text: '`target_parent_id` = ?)',
                value: $pair[IndexingEntity::TARGET_PARENT_ID],
            )
            : '`target_parent_id` IS NULL)');

        return $condition;
    }
}
