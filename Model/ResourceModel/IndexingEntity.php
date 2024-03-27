<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\ResourceModel;

use Klevu\Indexing\Model\IndexingEntity as IndexingEntityModel;
use Klevu\Indexing\Traits\CastIndexingEntityPropertiesToCorrectType;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class IndexingEntity extends AbstractDb
{
    use CastIndexingEntityPropertiesToCorrectType;

    public const TABLE = 'klevu_indexing_entity';
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
     * @param AbstractModel|IndexingEntityInterface $object
     *
     * @return IndexingEntity
     */
    protected function _afterLoad( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|IndexingEntityInterface $object,
    ) {
        if ($object->getId()) {
            $this->castPropertiesToCorrectType($object);
        }

        return $this;
    }

    /**
     * @param AbstractModel|IndexingEntityInterface $object
     *
     * @return IndexingEntity
     */
    protected function _beforeSave( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|IndexingEntityInterface $object,
    ) {
        $lastAction = $object->getData(IndexingEntityModel::LAST_ACTION);
        $object->setData(
            key: IndexingEntityModel::LAST_ACTION,
            value: $lastAction->value,
        );
        $nextAction = $object->getData(IndexingEntityModel::NEXT_ACTION);
        $object->setData(
            key: IndexingEntityModel::NEXT_ACTION,
            value: $nextAction->value,
        );

        return parent::_beforeSave($object);
    }

    /**
     * @param AbstractModel|IndexingEntityInterface $object
     *
     * @return IndexingEntity
     */
    protected function _afterSave( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|IndexingEntityInterface $object,
    ) {
        parent::_afterSave($object);

        if ($object->getId()) {
            $this->castPropertiesToCorrectType($object);
        }

        return $this;
    }
}
