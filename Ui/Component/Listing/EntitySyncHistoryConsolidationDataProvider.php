<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Ui\Component\Listing;

use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityConsolidationRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\DateTimeFormatterInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

class EntitySyncHistoryConsolidationDataProvider extends DataProvider
{
    /**
     * @var SyncHistoryEntityConsolidationRepositoryInterface
     */
    private readonly SyncHistoryEntityConsolidationRepositoryInterface $syncHistoryEntityConsolidationRepository;
    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;
    /**
     * @var DateTimeFormatterInterface
     */
    private readonly DateTimeFormatterInterface $dateTimeFormatter;
    /**
     * @var string
     */
    private readonly string $entityType;
    /**
     * @var int
     */
    private readonly int $dateFormat;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param SyncHistoryEntityConsolidationRepositoryInterface $syncHistoryEntityConsolidationRepository
     * @param SerializerInterface $serializer
     * @param DateTimeFormatterInterface $dateTimeFormatter
     * @param string $entityType
     * @param int $dateFormat
     * @param mixed[] $meta
     * @param mixed[] $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        SyncHistoryEntityConsolidationRepositoryInterface $syncHistoryEntityConsolidationRepository,
        SerializerInterface $serializer,
        DateTimeFormatterInterface $dateTimeFormatter,
        string $entityType,
        int $dateFormat = \IntlDateFormatter::MEDIUM,
        array $meta = [],
        array $data = [],
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

        $this->syncHistoryEntityConsolidationRepository = $syncHistoryEntityConsolidationRepository;
        $this->serializer = $serializer;
        $this->dateTimeFormatter = $dateTimeFormatter;
        $this->entityType = $entityType;
        $this->dateFormat = $dateFormat;
    }

    /**
     * @return mixed[]
     * @throws \InvalidArgumentException
     */
    public function getData(): array
    {
        $items = [];
        foreach ($this->getItems() as $syncConsolidationRecord) {
            $items[] = $this->formatRecord($syncConsolidationRecord);
        }

        return [
            'items' => $items,
            'totalRecords' => count($items),
        ];
    }

    /**
     * @return array<SyncHistoryEntityConsolidationRecordInterface&DataObject>
     */
    private function getItems(): array
    {
        $targetId = $this->request->getParam('target_id');
        if (!$targetId) {
            return [];
        }
        $syncRecords = $this->syncHistoryEntityConsolidationRepository->getList(
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
        $this->filterBuilder->setField(SyncHistoryEntityConsolidationRecord::TARGET_ID);
        $this->filterBuilder->setValue((string)$targetId);
        $this->filterBuilder->setConditionType('eq');
        $idFilter = $this->filterBuilder->create();
        $this->searchCriteriaBuilder->addFilter(filter: $idFilter);

        $this->filterBuilder->setField(SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE);
        $this->filterBuilder->setValue($this->entityType);
        $this->filterBuilder->setConditionType('eq');
        $typeFilter = $this->filterBuilder->create();
        $this->searchCriteriaBuilder->addFilter(filter: $typeFilter);

        return $this->searchCriteriaBuilder->create();
    }

    /**
     * @param DataObject&SyncHistoryEntityConsolidationRecordInterface $syncRecord
     *
     * @return mixed[]
     * @throws \InvalidArgumentException
     */
    private function formatRecord(SyncHistoryEntityConsolidationRecordInterface $syncRecord): array
    {
        /** @var mixed[] $return */
        $return = $syncRecord->toArray();
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $return[SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID] = $return[SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID] ?: null;
        $return[SyncHistoryEntityConsolidationRecord::HISTORY] = $this->formatHistory(
            history: $return[SyncHistoryEntityConsolidationRecord::HISTORY] ?? '',
        );
        $return[SyncHistoryEntityConsolidationRecord::DATE] = $this->formatDate(
            date: $return[SyncHistoryEntityConsolidationRecord::DATE] ?? '',
            showTime: false,
        );

        return $return;
    }

    /**
     * @param string $history
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    private function formatHistory(string $history): string
    {
        $return = '';
        $records = $this->unserializeHistoryRecords(history: $history);
        foreach ($records as $record) {
            $row = '';
            foreach ($record as $key => $value) {
                if ($key === SyncHistoryEntityRecord::ACTION_TIMESTAMP) {
                    $value = $this->formatDate(date: $value, showTime: true);
                }
                if (is_bool($value)) {
                    $value = $value
                        ? __('Success')->render()
                        : __('Failed')->render();
                }
                $row .= $value . ' - ';
            }
            $return .= trim(string: $row, characters: ' -');
            $return .= '<br/>';
        }

        return $return;
    }

    /**
     * @param string $history
     *
     * @return mixed[]
     * @throws \InvalidArgumentException
     */
    private function unserializeHistoryRecords(string $history): array
    {
        return $this->serializer->unserialize(string: $history) ?? [];
    }

    /**
     * @param string $date
     * @param bool $showTime
     *
     * @return string
     */
    private function formatDate(string $date, bool $showTime = true): string
    {
        return $this->dateTimeFormatter->formatObject(
            object: new \DateTime($date),
            format: [
                $this->dateFormat,
                $showTime ? \IntlDateFormatter::MEDIUM : \IntlDateFormatter::NONE,
            ],
        );
    }
}
