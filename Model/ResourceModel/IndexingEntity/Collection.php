<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\ResourceModel\IndexingEntity;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity as IndexingEntityResourceModel;
use Klevu\Indexing\Traits\CastIndexingEntityPropertiesToCorrectType;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    use CastIndexingEntityPropertiesToCorrectType;

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            model: IndexingEntity::class,
            resourceModel: IndexingEntityResourceModel::class,
        );
    }

    /**
     * Ensure returned IndexingEntity data is the correct type, by default all fields returned as strings
     *
     * @return $this|Collection
     */
    protected function _afterLoad() // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint, PSR2.Methods.MethodDeclaration.Underscore, Generic.Files.LineLength.TooLong
    {
        parent::_afterLoad();

        /** @var IndexingEntity $item */
        foreach ($this->getItems() as $item) {
            $this->castPropertiesToCorrectType($item);
        }
        $this->_eventManager->dispatch(
            'klevu_indexing_entity_collection_load_after',
            ['collection' => $this],
        );

        return $this;
    }
}
