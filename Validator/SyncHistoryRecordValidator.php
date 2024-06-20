<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Validator;

use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Framework\Validator\AbstractValidator;

class SyncHistoryRecordValidator extends AbstractValidator implements ValidatorInterface
{
    /**
     * @var string[]
     */
    private array $fieldTypes = [
        SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'string',
        SyncHistoryEntityRecord::TARGET_ID => 'int',
        SyncHistoryEntityRecord::TARGET_PARENT_ID => 'int|null',
        SyncHistoryEntityRecord::API_KEY => 'string',
        SyncHistoryEntityRecord::ACTION => Actions::class,
        SyncHistoryEntityRecord::ACTION_TIMESTAMP => 'int|string',
        SyncHistoryEntityRecord::IS_SUCCESS => 'bool',
        SyncHistoryEntityRecord::MESSAGE => 'string|null',
    ];
    /**
     * @var int[]
     */
    private array $maxFieldLengths = [
        SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 63,
        SyncHistoryEntityRecord::API_KEY => 31,
    ];

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid(mixed $value): bool
    {
        $this->_clearMessages();

        return $this->validateType($value)
            && $this->validateValuesCorrectType($value)
            && $this->validateVarCharMaxLength($value);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function validateType(mixed $value): bool
    {
        if ($value instanceof SyncHistoryEntityRecordInterface) {
            return true;
        }
        $this->_addMessages([
            __(
                'Invalid type provided. Expected %1, received %2.',
                SyncHistoryEntityRecordInterface::class,
                get_debug_type($value),
            )->render(),
        ]);

        return false;
    }

    /**
     * @param SyncHistoryEntityRecordInterface $syncHistoryEntityRecord
     *
     * @return bool
     */
    private function validateValuesCorrectType(SyncHistoryEntityRecordInterface $syncHistoryEntityRecord): bool
    {
        $return = true;
        foreach ($this->fieldTypes as $field => $allowedTypes) {
            $allowedTypesArray = explode('|', $allowedTypes);
            $dataType = get_debug_type(
                $syncHistoryEntityRecord->getData($field),
            );
            if (!in_array($dataType, $allowedTypesArray, true)) {
                $return = false;
                $this->_addMessages([
                    __(
                        'Incorrect data type provided for %1. Expected %2, received %3.',
                        $field,
                        $allowedTypes,
                        $dataType,
                    )->render(),
                ]);
            }
        }

        return $return;
    }

    /**
     * Ensure the data we are saving will not be truncated by the database
     *
     * @param SyncHistoryEntityRecordInterface $syncHistoryEntityRecord
     *
     * @return bool
     */
    private function validateVarCharMaxLength(SyncHistoryEntityRecordInterface $syncHistoryEntityRecord): bool
    {
        $return = true;

        foreach ($this->maxFieldLengths as $field => $maxFieldLength) {
            $contentLength = strlen(
                $syncHistoryEntityRecord->getData($field),
            );
            if ($contentLength > $maxFieldLength) {
                $return = false;
                $this->_addMessages([
                    __(
                        'Invalid data provided for %1. Expected max string length %2, received %3.',
                        $field,
                        $maxFieldLength,
                        $contentLength,
                    )->render(),
                ]);
            }
        }

        return $return;
    }
}
