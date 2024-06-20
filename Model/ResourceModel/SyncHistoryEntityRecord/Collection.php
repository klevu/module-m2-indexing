<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityRecord;

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder

use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityRecord as SyncHistoryEntityRecordResourceModel;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\Indexing\Traits\CastIndexingEntityHistoryPropertiesToCorrectType;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    use CastIndexingEntityHistoryPropertiesToCorrectType;

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            model: SyncHistoryEntityRecord::class,
            resourceModel: SyncHistoryEntityRecordResourceModel::class,
        );
    }

    /**
     * @return SyncHistoryEntityRecordInterface[]
     */
    public function getItems(): array
    {
        /** @var SyncHistoryEntityRecordInterface[] $items */
        $items = parent::getItems();

        return $items;
    }

    /**
     * Ensure returned Record data is the correct type, by default all fields returned as strings
     *
     * @return $this|Collection
     */
    protected function _afterLoad() // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint, PSR2.Methods.MethodDeclaration.Underscore, Generic.Files.LineLength.TooLong
    {
        parent::_afterLoad();

        /** @var SyncHistoryEntityRecord $item */
        foreach ($this->getItems() as $item) {
            $this->castPropertiesToCorrectType($item);
        }
        $this->_eventManager->dispatch(
            'klevu_indexing_entity_history_collection_load_after',
            ['collection' => $this],
        );

        return $this;
    }
}
