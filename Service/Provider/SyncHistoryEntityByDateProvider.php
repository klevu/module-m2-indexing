<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityRepositoryInterface;
use Klevu\IndexingApi\Service\Provider\SyncHistoryEntityByDateProviderInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;

class SyncHistoryEntityByDateProvider implements SyncHistoryEntityByDateProviderInterface
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
     * @var SyncHistoryEntityRepositoryInterface
     */
    private readonly SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository;
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
     * @param SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param string $dateComparator
     */
    public function __construct(
        SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        string $dateComparator = self::LIKE,
    ) {
        $this->syncHistoryEntityRepository = $syncHistoryEntityRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->dateComparator = $dateComparator;
    }

    /**
     * @param string $date
     *
     * @return array<string, SyncHistoryEntityRecordInterface[]>
     */
    public function get(string $date): array
    {
        $return = [];
        $records = $this->getSyncHistoryItems(date: $date);
        foreach ($records as $record) {
            $entityType = $record->getTargetEntityType();
            $return[$entityType] ??= [];
            $return[$entityType][] = $record;
        }

        return $return;
    }

    /**
     * @return SyncHistoryEntityRecordInterface[]
     */
    private function getSyncHistoryItems(string $date): array
    {
        $searchResults = $this->syncHistoryEntityRepository->getList(
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
        $this->filterBuilder->setField(field:SyncHistoryEntityRecord::ACTION_TIMESTAMP);
        $this->filterBuilder->setConditionType(conditionType: $this->dateComparator);
        $this->filterBuilder->setValue(
            value: $this->prepareDate(date: $date),
        );
        $dateFilter = $this->filterBuilder->create();
        $this->searchCriteriaBuilder->addFilter(filter: $dateFilter);

        $this->searchCriteriaBuilder->addSortOrder(
            field: SyncHistoryEntityRecord::ACTION_TIMESTAMP,
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
