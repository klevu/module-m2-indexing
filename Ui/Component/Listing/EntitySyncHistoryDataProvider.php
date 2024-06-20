<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Ui\Component\Listing;

use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

class EntitySyncHistoryDataProvider extends DataProvider
{
    /**
     * @var SyncHistoryEntityRepositoryInterface
     */
    private readonly SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository;
    /**
     * @var DateTimeFactory
     */
    private readonly DateTimeFactory $dateTimeFactory;
    /**
     * @var string
     */
    private readonly string $entityType;
    /**
     * @var string
     */
    private string $dateFormat;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository
     * @param DateTimeFactory $dateTimeFactory
     * @param string $entityType
     * @param mixed[] $meta
     * @param mixed[] $data
     * @param string $dateFormat
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        SyncHistoryEntityRepositoryInterface $syncHistoryEntityRepository,
        DateTimeFactory $dateTimeFactory,
        string $entityType,
        array $meta = [],
        array $data = [],
        string $dateFormat = 'd/m/Y h:i:s',
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data,
        );

        $this->prepareUpdateUrl();
        $this->syncHistoryEntityRepository = $syncHistoryEntityRepository;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->entityType = $entityType;
        $this->dateFormat = $dateFormat;
    }

    /**
     * @return mixed[]
     */
    public function getData(): array
    {
        $items = [];
        foreach ($this->getItems() as $syncRecord) {
            $items[] = $this->formatRecord($syncRecord);
        }

        return [
            'items' => $items,
            'totalRecords' => count($items),
        ];
    }

    /**
     * @return array<SyncHistoryEntityRecordInterface&DataObject>
     */
    private function getItems(): array
    {
        $targetId = $this->request->getParam('target_id');
        if (!$targetId) {
            return [];
        }
        $syncRecords = $this->syncHistoryEntityRepository->getList(
            searchCriteria: $this->generateSearchCriteria(targetId: (int)$targetId),
        );

        return $syncRecords->getItems();
    }

    /**
     * @param int $targetId
     *
     * @return SearchCriteriaInterface
     */
    private function generateSearchCriteria(int $targetId): SearchCriteriaInterface
    {
        $this->filterBuilder->setField(SyncHistoryEntityRecord::TARGET_ID);
        $this->filterBuilder->setValue((string)$targetId);
        $this->filterBuilder->setConditionType('eq');
        $idFilter = $this->filterBuilder->create();
        $this->searchCriteriaBuilder->addFilter(filter: $idFilter);

        $this->filterBuilder->setField(SyncHistoryEntityRecord::TARGET_ENTITY_TYPE);
        $this->filterBuilder->setValue($this->entityType);
        $this->filterBuilder->setConditionType('eq');
        $typeFilter = $this->filterBuilder->create();
        $this->searchCriteriaBuilder->addFilter(filter: $typeFilter);

        return $this->searchCriteriaBuilder->create();
    }

    /**
     * @param SyncHistoryEntityRecordInterface $syncRecord
     *
     * @return mixed[]
     */
    private function formatRecord(SyncHistoryEntityRecordInterface $syncRecord): array
    {
        $return = $syncRecord->toArray();
        $return[SyncHistoryEntityRecord::TARGET_PARENT_ID] = $return[SyncHistoryEntityRecord::TARGET_PARENT_ID] ?: null;
        $return[SyncHistoryEntityRecord::IS_SUCCESS] = (int)$return[SyncHistoryEntityRecord::IS_SUCCESS];
        $return[SyncHistoryEntityRecord::ACTION_TIMESTAMP] = $this->formatDate(
            timestamp: $return[SyncHistoryEntityRecord::ACTION_TIMESTAMP],
        );

        return $return;
    }

    /**
     * @param string $timestamp
     *
     * @return string
     */
    private function formatDate(string $timestamp): string
    {
        /** @var DateTime $dateModel */
        $dateModel = $this->dateTimeFactory->create();

        return $dateModel->gmtDate(
            format: $this->dateFormat,
            input: $timestamp,
        );
    }
}
