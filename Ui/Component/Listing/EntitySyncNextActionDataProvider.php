<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Ui\Component\Listing;

use Klevu\Indexing\Exception\InvalidIndexingEntityException;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

class EntitySyncNextActionDataProvider extends DataProvider
{
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;
    /**
     * @var string
     */
    private readonly string $entityType;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param string $entityType
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
        IndexingEntityProviderInterface $indexingEntityProvider,
        string $entityType,
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

        $this->indexingEntityProvider = $indexingEntityProvider;
        $this->entityType = $entityType;
        $this->prepareUpdateUrl();
    }

    /**
     * @return mixed[]
     * @throws InvalidIndexingEntityException
     */
    public function getData(): array
    {
        $items = [];
        foreach ($this->getItems() as $indexingEntity) {
            $items[] = $this->formatRecord(indexingEntity: $indexingEntity);
        }

        return [
            'items' => $items,
            'totalRecords' => count($items),
        ];
    }

    /**
     * @return array<IndexingEntityInterface>
     */
    private function getItems(): array
    {
        $targetId = $this->request->getParam('target_id');
        if (!$targetId) {
            return [];
        }
        $sorting = $this->request->getParam('sorting');

        return $this->indexingEntityProvider->get(
            entityType: $this->entityType,
            entityIds: [$targetId],
            sorting: $sorting,
        );
    }

    /**
     * @param IndexingEntityInterface $indexingEntity
     *
     * @return mixed[]
     * @throws InvalidIndexingEntityException
     */
    private function formatRecord(IndexingEntityInterface $indexingEntity): array
    {
        $return = $indexingEntity->toArray();
        if (null === ($return[IndexingEntity::IS_INDEXABLE] ?? null)) {
            throw new InvalidIndexingEntityException(
                phrase: __('Indexing Entity must have "%1" set', IndexingEntity::IS_INDEXABLE),
            );
        }
        $return[IndexingEntity::IS_INDEXABLE] = (int)$return[IndexingEntity::IS_INDEXABLE];
        $return[IndexingEntity::REQUIRES_UPDATE] = (int)$return[IndexingEntity::REQUIRES_UPDATE];

        return $return;
    }
}
