<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\ResourceModel\IndexingAttribute;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Model\ResourceModel\IndexingAttribute as IndexingAttributeResourceModel;
use Klevu\Indexing\Traits\CastIndexingAttributePropertiesToCorrectType;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    use CastIndexingAttributePropertiesToCorrectType;

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            model: IndexingAttribute::class,
            resourceModel: IndexingAttributeResourceModel::class,
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

        /** @var IndexingAttribute $item */
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
