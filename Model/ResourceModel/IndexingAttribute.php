<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\ResourceModel;

use Klevu\Indexing\Model\IndexingAttribute as IndexingAttributeModel;
use Klevu\Indexing\Traits\CastIndexingAttributePropertiesToCorrectType;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class IndexingAttribute extends AbstractDb
{
    use CastIndexingAttributePropertiesToCorrectType;

    public const TABLE = 'klevu_indexing_attribute';
    public const ID_FIELD_NAME = 'entity_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            mainTable: static::TABLE,
            idFieldName: static::ID_FIELD_NAME,
        );
    }

    /**
     * @param AbstractModel|IndexingAttributeInterface $object
     *
     * @return IndexingAttribute
     */
    protected function _afterLoad( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|IndexingAttributeInterface $object,
    ) {
        if ($object->getId()) {
            $this->castPropertiesToCorrectType($object);
        }

        return $this;
    }

    /**
     * @param AbstractModel|IndexingAttributeInterface $object
     *
     * @return IndexingAttribute
     */
    protected function _beforeSave( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|IndexingAttributeInterface $object,
    ) {
        $lastAction = $object->getData(IndexingAttributeModel::LAST_ACTION);
        $object->setData(
            key: IndexingAttributeModel::LAST_ACTION,
            value: $lastAction->value,
        );
        $nextAction = $object->getData(IndexingAttributeModel::NEXT_ACTION);
        $object->setData(
            key: IndexingAttributeModel::NEXT_ACTION,
            value: $nextAction->value,
        );

        return parent::_beforeSave($object);
    }

    /**
     * @param AbstractModel|IndexingAttributeInterface $object
     *
     * @return IndexingAttribute
     */
    protected function _afterSave( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|IndexingAttributeInterface $object,
    ) {
        parent::_afterSave($object);

        if ($object->getId()) {
            $this->castPropertiesToCorrectType($object);
        }

        return $this;
    }
}
