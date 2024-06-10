<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Validator;

use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Framework\Validator\AbstractValidator;

class StaticAttributeValidator extends AbstractValidator implements ValidatorInterface
{
    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid(mixed $value): bool
    {
        $this->_clearMessages();

        return $this->validateType($value)
            && $this->isAttributeIdSet($value)
            && $this->isAttributeIdNumeric($value)
            && $this->isAttributeCodeSet($value)
            && $this->isAttributeCodeString($value);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function validateType(mixed $value): bool
    {
        if (is_array($value)) {
            return true;
        }
        $this->_addMessages([
            __(
                'Invalid type provided. Expected array, received %1.',
                get_debug_type($value),
            )->render(),
        ]);

        return false;
    }

    /**
     * @param mixed[] $value
     *
     * @return bool
     */
    private function isAttributeIdSet(array $value): bool
    {
        if ($value['attribute_id'] ?? null) {
            return true;
        }
        $this->_addMessages([
            __(
                '"attribute_id" is a required field for static attributes',
            )->render(),
        ]);

        return false;
    }

    /**
     * @param mixed[] $value
     *
     * @return bool
     */
    private function isAttributeIdNumeric(array $value): bool
    {
        if (is_numeric($value['attribute_id'])) {
            return true;
        }
        $this->_addMessages([
            __(
                '"attribute_id" must be an integer, received %1',
                get_debug_type($value['attribute_id']),
            )->render(),
        ]);

        return false;
    }

    /**
     * @param mixed[] $value
     *
     * @return bool
     */
    private function isAttributeCodeSet(array $value): bool
    {
        if ($value['attribute_code'] ?? null) {
            return true;
        }
        $this->_addMessages([
            __(
                '"attribute_code" is a required field for static attributes',
            )->render(),
        ]);

        return false;
    }

    /**
     * @param mixed[] $value
     *
     * @return bool
     */
    private function isAttributeCodeString(array $value): bool
    {
        if (is_string($value['attribute_code'])) {
            return true;
        }
        $this->_addMessages([
            __(
                '"attribute_code" must be an string, received %1',
                get_debug_type($value['attribute_code']),
            )->render(),
        ]);

        return false;
    }
}
