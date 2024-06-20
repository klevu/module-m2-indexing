<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\ResourceModel;

use Klevu\Indexing\Model\SyncHistoryEntityRecord as RecordModel;
use Klevu\Indexing\Traits\CastIndexingEntityHistoryPropertiesToCorrectType;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SyncHistoryEntityRecord extends AbstractDb
{
    use CastIndexingEntityHistoryPropertiesToCorrectType;

    public const TABLE = 'klevu_indexing_entity_sync_history';
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
     * @param AbstractModel|SyncHistoryEntityRecordInterface $object
     *
     * @return SyncHistoryEntityRecord
     */
    protected function _afterLoad( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|SyncHistoryEntityRecordInterface $object,
    ) {
        if ($object->getId()) {
            $this->castPropertiesToCorrectType($object);
        }

        return $this;
    }

    /**
     * @param AbstractModel|SyncHistoryEntityRecordInterface $object
     *
     * @return SyncHistoryEntityRecord
     */
    protected function _beforeSave( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|SyncHistoryEntityRecordInterface $object,
    ) {
        $lastAction = $object->getData(RecordModel::ACTION);
        $object->setData(
            key: RecordModel::ACTION,
            value: $lastAction->value,
        );

        return parent::_beforeSave($object);
    }

    /**
     * @param AbstractModel|SyncHistoryEntityRecordInterface $object
     *
     * @return SyncHistoryEntityRecord
     */
    protected function _afterSave( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|SyncHistoryEntityRecordInterface $object,
    ) {
        parent::_afterSave($object);

        if ($object->getId()) {
            $this->castPropertiesToCorrectType($object);
        }

        return $this;
    }
}
