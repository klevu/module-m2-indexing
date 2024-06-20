<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityConsolidationRecord;

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder

use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityConsolidationRecord as SyncHistoryEntityConsolidationResourceModel; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\Indexing\Traits\CastSyncHistoryConsolidationPropertiesToCorrectType;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    use CastSyncHistoryConsolidationPropertiesToCorrectType;

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            model: SyncHistoryEntityConsolidationRecord::class,
            resourceModel: SyncHistoryEntityConsolidationResourceModel::class,
        );
    }

    /**
     * @return SyncHistoryEntityConsolidationRecordInterface[]
     */
    public function getItems(): array
    {
        /** @var SyncHistoryEntityConsolidationRecordInterface[] $items */
        $items = parent::getItems();

        return $items;
    }

    /**
     * Ensure returned Record data is the correct type, by default all fields returned as strings
     *
     * @return $this|Collection
     * @throws \JsonException
     */
    protected function _afterLoad() // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint, PSR2.Methods.MethodDeclaration.Underscore, Generic.Files.LineLength.TooLong
    {
        parent::_afterLoad();

        foreach ($this->getItems() as $item) {
            $this->castPropertiesToCorrectType($item);
        }
        $this->_eventManager->dispatch(
            'klevu_indexing_entity_history_consolidation_collection_load_after',
            ['collection' => $this],
        );

        return $this;
    }
}
