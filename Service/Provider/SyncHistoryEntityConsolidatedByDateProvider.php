<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityConsolidationRepositoryInterface;
use Klevu\IndexingApi\Service\Provider\SyncHistoryEntityConsolidatedByDateProviderInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;

class SyncHistoryEntityConsolidatedByDateProvider implements SyncHistoryEntityConsolidatedByDateProviderInterface
{
    public const EQUALS = 'eq';
    public const NOT_EQUALS = 'neq';
    public const GREATER_THAN = 'gt';
    public const GREATER_THAN_OR_EQUALS = 'gte';
    public const LESS_THAN = 'lt';
    public const LESS_THAN_OR_EQUALS = 'lte';
    public const LIKE = 'like';
    public const NOT_LIKE = 'nlike';

    /**
     * @var SyncHistoryEntityConsolidationRepositoryInterface
     */
    private readonly SyncHistoryEntityConsolidationRepositoryInterface $consolidationRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    private readonly SearchCriteriaBuilder $searchCriteriaBuilder;
    /**
     * @var FilterBuilder
     */
    private readonly FilterBuilder $filterBuilder;
    /**
     * @var string
     */
    private readonly string $dateComparator;

    /**
     * @param SyncHistoryEntityConsolidationRepositoryInterface $consolidationRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param string $dateComparator
     */
    public function __construct(
        SyncHistoryEntityConsolidationRepositoryInterface $consolidationRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        string $dateComparator = self::LIKE,
    ) {
        $this->consolidationRepository = $consolidationRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->dateComparator = $dateComparator;
    }

    /**
     * @param string $date
     *
     * @return SyncHistoryEntityConsolidationRecordInterface[]
     */
    public function get(string $date): array
    {
        $searchResults = $this->consolidationRepository->getList(
            searchCriteria: $this->generateSearchCriteria(date: $date),
        );

        return $searchResults->getItems();
    }

    /**
     * @param string $date
     *
     * @return SearchCriteriaInterface
     */
    private function generateSearchCriteria(string $date): SearchCriteriaInterface
    {
        $this->filterBuilder->setField(field:SyncHistoryEntityConsolidationRecord::DATE);
        $this->filterBuilder->setConditionType(conditionType: $this->dateComparator);
        $this->filterBuilder->setValue(
            value: $this->prepareDate(date: $date),
        );
        $dateFilter = $this->filterBuilder->create();
        $this->searchCriteriaBuilder->addFilter(filter: $dateFilter);

        $this->searchCriteriaBuilder->addSortOrder(
            field: SyncHistoryEntityConsolidationRecord::DATE,
            direction: SortOrder::SORT_DESC,
        );

        return $this->searchCriteriaBuilder->create();
    }

    /**
     * @param string $date
     *
     * @return string
     */
    private function prepareDate(string $date): string
    {
        if (in_array($this->dateComparator, [static::LIKE, static::NOT_LIKE], true)) {
            $date = '%' . $date . '%';
        }

        return $date;
    }
}
