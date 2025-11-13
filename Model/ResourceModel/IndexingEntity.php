<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\ResourceModel;

use Klevu\Indexing\Model\IndexingEntity as IndexingEntityModel;
use Klevu\Indexing\Traits\CastIndexingEntityPropertiesToCorrectType;
use Klevu\IndexingApi\Api\BatchSaveAwareResourceInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class IndexingEntity extends AbstractDb implements BatchSaveAwareResourceInterface
{
    use CastIndexingEntityPropertiesToCorrectType;

    public const TABLE = 'klevu_indexing_entity';
    public const ID_FIELD_NAME = 'entity_id';
    private const BATCH_SAVE_SIZE = 1000;

    /**
     * @return void
     */
    protected function _construct(): void // phpcs:ignore SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder, Generic.Files.LineLength.TooLong
    {
        $this->_init(
            mainTable: static::TABLE,
            idFieldName: static::ID_FIELD_NAME,
        );
    }

    /**
     * @param IndexingEntityInterface[] $objects
     *
     * @return void
     * @throws LocalizedException
     * @throws \Zend_Db_Exception
     */
    public function saveMultiple(array $objects): void // phpcs:ignore SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder, Generic.Files.LineLength.TooLong
    {
        $objects = array_filter(
            array: $objects,
            callback: static fn ($object) => $object->hasDataChanges(),
        );
        if (!$objects) {
            return;
        }

        $connection = $this->getConnection();

        $connection->beginTransaction();
        do {
            $objectsToProcess = array_splice($objects, 0, self::BATCH_SAVE_SIZE);
            
            array_walk($objectsToProcess, [$this, '_beforeSave']);
            $batchData = array_map(
                callback: fn (AbstractModel $object) => $this->_prepareDataForTable(
                    object: $object,
                    table: $this->getMainTable(),
                ),
                array: $objectsToProcess,
            );

            if ($connection instanceof Mysql) {
                $connection->insertArray(
                    table: $this->getMainTable(),
                    columns: array_keys($batchData[0]),
                    data: $batchData,
                    strategy: AdapterInterface::REPLACE,
                );
            } else {
                foreach ($batchData as $data) {
                    $connection->insertOnDuplicate(
                        table: $this->getMainTable(),
                        data: $data,
                        fields: array_keys($data),
                    );
                }
            }
            
            array_walk($objectsToProcess, [$this, '_afterSave']);
        } while ($objects);

        $connection->commit();
    }

    /**
     * @param DataObject $object
     * @param string $table
     *
     * @return array<string, mixed>
     */
    // phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
    protected function _prepareDataForTable(
        DataObject $object,
        $table,
    ): array {
    // phpcs:enable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
        $data = [];
        $connection = $this->getConnection();

        $fields = $connection->describeTable($table);
        foreach (array_keys($fields) as $field) {
            if ($object->hasData($field)) {
                $fieldValue = $object->getData($field);

                switch (true) {
                    case $fieldValue instanceof \Zend_Db_Expr:
                        $data[$field] = $fieldValue;
                        break;

                    case $fieldValue instanceof \BackedEnum:
                        $data[$field] = $fieldValue->value;
                        break;

                    case null === $fieldValue && !empty($fields[$field]['NULLABLE']):
                        $data[$field] = null;
                        break;

                    case null !== $fieldValue:
                        $fieldValue = $this->_prepareTableValueForSave(
                            value: $fieldValue,
                            type: $fields[$field]['DATA_TYPE'],
                        );
                        $data[$field] = $connection->prepareColumnValue(
                            column: $fields[$field],
                            value: $fieldValue,
                        );
                        break;
                }
            }
        }

        return $data;
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
        if ($lastAction instanceof \BackedEnum) {
            $object->setData(
                key: IndexingEntityModel::LAST_ACTION,
                value: $lastAction->value,
            );
        }
        $nextAction = $object->getData(IndexingEntityModel::NEXT_ACTION);
        if ($nextAction instanceof \BackedEnum) {
            $object->setData(
                key: IndexingEntityModel::NEXT_ACTION,
                value: $nextAction->value,
            );
        }

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
