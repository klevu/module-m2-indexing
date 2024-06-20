<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Validator;

use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Framework\Validator\AbstractValidator;

class SyncHistoryConsolidationRecordValidator extends AbstractValidator implements ValidatorInterface
{
    /**
     * @var string[]
     */
    private array $fieldTypes = [
        SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'string',
        SyncHistoryEntityConsolidationRecord::TARGET_ID => 'int',
        SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 'int|null',
        SyncHistoryEntityConsolidationRecord::API_KEY => 'string',
        SyncHistoryEntityConsolidationRecord::HISTORY => 'string',
        SyncHistoryEntityConsolidationRecord::DATE => 'int|string',
    ];
    /**
     * @var int[]
     */
    private array $maxFieldLengths = [
        SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 63,
        SyncHistoryEntityConsolidationRecord::API_KEY => 31,
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
        if ($value instanceof SyncHistoryEntityConsolidationRecordInterface) {
            return true;
        }
        $this->_addMessages([
            __(
                'Invalid type provided. Expected %1, received %2.',
                SyncHistoryEntityConsolidationRecordInterface::class,
                get_debug_type($value),
            )->render(),
        ]);

        return false;
    }

    /**
     * @param SyncHistoryEntityConsolidationRecordInterface $syncHistoryEntityConsolidationRecord
     *
     * @return bool
     */
    private function validateValuesCorrectType(
        SyncHistoryEntityConsolidationRecordInterface $syncHistoryEntityConsolidationRecord,
    ): bool {
        $return = true;
        foreach ($this->fieldTypes as $field => $allowedTypes) {
            $allowedTypesArray = explode('|', $allowedTypes);
            $dataType = get_debug_type(
                $syncHistoryEntityConsolidationRecord->getData($field),
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
     * @param SyncHistoryEntityConsolidationRecordInterface $syncHistoryEntityConsolidationRecord
     *
     * @return bool
     */
    private function validateVarCharMaxLength(
        SyncHistoryEntityConsolidationRecordInterface $syncHistoryEntityConsolidationRecord,
    ): bool {
        $return = true;

        foreach ($this->maxFieldLengths as $field => $maxFieldLength) {
            $contentLength = strlen(
                $syncHistoryEntityConsolidationRecord->getData($field),
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
