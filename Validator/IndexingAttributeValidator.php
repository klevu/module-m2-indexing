<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Validator;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Framework\Validator\AbstractValidator;

class IndexingAttributeValidator extends AbstractValidator implements ValidatorInterface
{
    /**
     * @var string[]
     */
    private array $fieldTypes = [
        IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'string',
        IndexingAttribute::TARGET_ID => 'int',
        IndexingAttribute::API_KEY => 'string',
        IndexingAttribute::LOCK_TIMESTAMP => 'int|string|null',
        IndexingAttribute::LAST_ACTION => Actions::class,
        IndexingAttribute::LAST_ACTION_TIMESTAMP => 'int|string|null',
        IndexingAttribute::NEXT_ACTION => Actions::class,
        IndexingAttribute::IS_INDEXABLE => 'bool',
    ];
    /**
     * @var int[]
     */
    private array $maxFieldLengths = [
        IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 63,
        IndexingAttribute::API_KEY => 31,
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
        if ($value instanceof IndexingAttributeInterface) {
            return true;
        }
        $this->_addMessages([
            __(
                'Invalid type provided. Expected %1, received %2.',
                IndexingAttributeInterface::class,
                get_debug_type($value),
            )->render(),
        ]);

        return false;
    }

    /**
     * @param IndexingAttributeInterface $indexingAttribute
     *
     * @return bool
     */
    private function validateValuesCorrectType(IndexingAttributeInterface $indexingAttribute): bool
    {
        $return = true;
        foreach ($this->fieldTypes as $field => $allowedTypes) {
            $allowedTypesArray = explode('|', $allowedTypes);
            $dataType = get_debug_type(
                $indexingAttribute->getData($field), //@phpstan-ignore-line
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
     * @param IndexingAttributeInterface $indexingAttribute
     *
     * @return bool
     */
    private function validateVarCharMaxLength(IndexingAttributeInterface $indexingAttribute): bool
    {
        $return = true;

        foreach ($this->maxFieldLengths as $field => $maxFieldLength) {
            $contentLength = strlen(
                $indexingAttribute->getData($field), //@phpstan-ignore-line
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
