<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Validator;

use Klevu\Indexing\Validator\StaticAttributeValidator;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers StaticAttributeValidator
 * @method ValidatorInterface instantiateTestObject(?array $arguments = null)
 * @method ValidatorInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class StaticAttributeValidatorTest extends TestCase
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

        $this->implementationFqcn = StaticAttributeValidator::class;
        $this->interfaceFqcn = ValidatorInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith [1]
     *           [12.34]
     *           ["string"]
     *           [true]
     *           [null]
     */
    public function testIsValid_ReturnsFalse_InvalidDataType(mixed $invalidDataType): void
    {
        $validator = $this->instantiateTestObject();
        $result = $validator->isValid($invalidDataType);
        $messages = $validator->getMessages();

        $this->assertFalse(condition: $result);
        $this->assertContains(
            needle: sprintf('Invalid type provided. Expected array, received %s.', get_debug_type($invalidDataType)),
            haystack: $messages,
        );
    }

    public function testIsValid_ReturnsFalse_AttributeIdMissing(): void
    {
        $validator = $this->instantiateTestObject();
        $result = $validator->isValid([
            'attribute_code' => 'some_attribute',
        ]);
        $messages = $validator->getMessages();

        $this->assertFalse(condition: $result);
        $this->assertContains(
            needle: '"attribute_id" is a required field for static attributes',
            haystack: $messages,
        );
    }

    /**
     * @testWith ["string"]
     *           [[1]]
     *           [true]
     */
    public function testIsValid_ReturnsFalse_AttributeIdInvalid(mixed $invalidAttributeId): void
    {
        $validator = $this->instantiateTestObject();
        $result = $validator->isValid([
            'attribute_id' => $invalidAttributeId,
            'attribute_code' => 'some_attribute',
        ]);
        $messages = $validator->getMessages();

        $this->assertFalse(condition: $result);
        $this->assertContains(
            needle: sprintf('"attribute_id" must be an integer, received %s', get_debug_type($invalidAttributeId)),
            haystack: $messages,
        );
    }

    public function testIsValid_ReturnsFalse_AttributeCodeMissing(): void
    {
        $validator = $this->instantiateTestObject();
        $result = $validator->isValid([
            'attribute_id' => 1234,
        ]);
        $messages = $validator->getMessages();

        $this->assertFalse(condition: $result);
        $this->assertContains(
            needle: '"attribute_code" is a required field for static attributes',
            haystack: $messages,
        );
    }

    /**
     * @testWith [123]
     *           [12.34]
     *           [[1]]
     *           [true]
     */
    public function testIsValid_ReturnsFalse_AttributeCodeInvalid(mixed $invalidAttributeCode): void
    {
        $validator = $this->instantiateTestObject();
        $result = $validator->isValid([
            'attribute_id' => 1234,
            'attribute_code' => $invalidAttributeCode,
        ]);
        $messages = $validator->getMessages();

        $this->assertFalse(condition: $result);
        $this->assertContains(
            needle: sprintf('"attribute_code" must be an string, received %s', get_debug_type($invalidAttributeCode)),
            haystack: $messages,
        );
    }
}
