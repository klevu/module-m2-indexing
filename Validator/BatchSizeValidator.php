<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Validator;

use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Framework\Validator\AbstractValidator;

class BatchSizeValidator extends AbstractValidator implements ValidatorInterface
{
    private const MINIMUM_ALLOWED_VALUE = 1;
    private const MAXIMUM_ALLOWED_VALUE = 9999999;

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid(mixed $value): bool
    {
        $this->_clearMessages();

        return $this->validateType($value)
            && $this->validateInRange($value);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    private function validateType(mixed $value): bool
    {
        if (null === $value || is_int($value)) {
            return true;
        }
        $this->_addMessages([
            __(
                'Invalid type provided. Expected integer, received %1',
                get_debug_type($value),
            )->render(),
        ]);

        return false;
    }

    /**
     * @param int|null $value
     *
     * @return bool
     */
    private function validateInRange(?int $value): bool
    {
        if (null === $value) {
            return true;
        }
        if (self::MINIMUM_ALLOWED_VALUE <= $value && $value <= self::MAXIMUM_ALLOWED_VALUE) {
            return true;
        }
        $this->_addMessages([
            __(
                'Invalid value provided. Value outside allowed range %1 < %2, received %3.',
                self::MINIMUM_ALLOWED_VALUE,
                self::MAXIMUM_ALLOWED_VALUE,
                $value,
            )->render(),
        ]);

        return false;
    }
}
