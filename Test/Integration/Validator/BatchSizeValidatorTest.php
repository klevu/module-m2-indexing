<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Validator;

use Klevu\Indexing\Validator\BatchSizeValidator;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers BatchSizeValidator::class
 * @method ValidatorInterface instantiateTestObject(?array $arguments = null)
 * @method ValidatorInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class BatchSizeValidatorTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = BatchSizeValidator::class;
        $this->interfaceFqcn = ValidatorInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith [1]
     *           [null]
     *           [9999999]
     */
    public function testIsValid_ReturnsTrue_ForValidValue(mixed $validValue): void
    {
        $validator = $this->instantiateTestObject();
        $result = $validator->isValid($validValue);

        $this->assertTrue($result);
    }

    /**
     * @testWith [true]
     *           [1.23]
     *           ["1"]
     *           ["string"]
     */
    public function testIsValid_ReturnsFalse_ForInvalidType(mixed $invalidType): void
    {
        $validator = $this->instantiateTestObject();
        $result = $validator->isValid($invalidType);

        $this->assertFalse($result);
    }

    /**
     * @testWith [-1]
     *           [0]
     *           [-138947]
     *           [999999999999999999]
     */
    public function testIsValid_ReturnsFalse_ForIntegerOutSideRange(int $invalidValue): void
    {
        $validator = $this->instantiateTestObject();
        $result = $validator->isValid($invalidValue);

        $this->assertFalse($result);
    }
}
