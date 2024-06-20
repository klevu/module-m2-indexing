<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\ResourceModel;

use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord as ConsolidationRecordModel;
use Klevu\Indexing\Traits\CastSyncHistoryConsolidationPropertiesToCorrectType;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Serialize\SerializerInterface;

class SyncHistoryEntityConsolidationRecord extends AbstractDb
{
    use CastSyncHistoryConsolidationPropertiesToCorrectType;

    public const TABLE = 'klevu_indexing_entity_sync_history_consolidation';
    public const ID_FIELD_NAME = 'entity_id';

    /**
     * @param SerializerInterface $serializer
     * @param Context $context
     * @param null $connectionName
     */
    public function __construct(
        SerializerInterface $serializer,
        Context $context,
        $connectionName = null,
    ) {
        parent::__construct($context, $connectionName);

        $this->serializer = $serializer; // @phpstan-ignore-line
    }

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
     * @param AbstractModel|SyncHistoryEntityConsolidationRecordInterface $object
     *
     * @return SyncHistoryEntityConsolidationRecord
     * @throws \InvalidArgumentException
     */
    protected function _afterLoad( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|SyncHistoryEntityConsolidationRecordInterface $object,
    ) {
        if ($object->getId()) {
            $this->castPropertiesToCorrectType($object);
        }

        return $this;
    }

    /**
     * @param AbstractModel|SyncHistoryEntityConsolidationRecordInterface $object
     *
     * @return SyncHistoryEntityConsolidationRecord
     * @throws \InvalidArgumentException
     */
    protected function _beforeSave( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|SyncHistoryEntityConsolidationRecordInterface $object,
    ) {
        $history = $object->getData(ConsolidationRecordModel::HISTORY);
        $this->serializer->unserialize($history);
        $object->setData(
            key: ConsolidationRecordModel::HISTORY,
            value: $history,
        );

        return parent::_beforeSave($object);
    }

    /**
     * @param AbstractModel|SyncHistoryEntityConsolidationRecordInterface $object
     *
     * @return SyncHistoryEntityConsolidationRecord
     * @throws \InvalidArgumentException
     */
    protected function _afterSave( // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
        AbstractModel|SyncHistoryEntityConsolidationRecordInterface $object,
    ) {
        parent::_afterSave($object);

        if ($object->getId()) {
            $this->castPropertiesToCorrectType($object);
        }

        return $this;
    }
}
