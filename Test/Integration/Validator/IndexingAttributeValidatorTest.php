<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Validator;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Validator\IndexingAttributeValidator;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Validator\IndexingAttributeValidator::class
 * @method IndexingAttributeValidator instantiateTestObject(?array $arguments = null)
 * @method IndexingAttributeValidator instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingAttributeValidatorTest extends TestCase
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

        $this->objectManager = Bootstrap::getObjectManager();
        $this->implementationFqcn = IndexingAttributeValidator::class;
        $this->interfaceFqcn = ValidatorInterface::class;
    }

    /**
     * @dataProvider dataProvider_testIsValid_ReturnsFalse_ForIncorrectType
     */
    public function testIsValid_ReturnsFalse_ForIncorrectType(mixed $incorrectType): void
    {
        $validator = $this->instantiateTestObject();
        $this->assertFalse($validator->isValid($incorrectType));
        $this->assertSame(
            expected: sprintf(
                'Invalid type provided. Expected %s, received %s.',
                IndexingAttributeInterface::class,
                get_debug_type($incorrectType),
            ),
            actual: $validator->getMessages()[0] ?? '',
        );
    }

    /**
     * @return mixed[][]
     */
    public function dataProvider_testIsValid_ReturnsFalse_ForIncorrectType(): array
    {
        return [
            [null],
            [false],
            [true],
            [0],
            [123.456],
            ['string'],
            [[0 => 'string', '1' => 1]],
            [new DataObject()],
        ];
    }

    /**
     * @dataProvider dataProvider_testIsValid_ReturnsFalse_MissingRequiredData
     */
    public function testIsValid_ReturnsFalse_MissingRequiredData(string $field): void
    {
        $fieldTypes = [
            'target_attribute_type' => 'string',
            'target_id' => 'int',
            'api_key' => 'string',
            'last_action' => Actions::class,
            'next_action' => Actions::class,
            'is_indexable' => 'bool',
            'lock_timestamp' => 'int|string|null',
            'last_action_timestamp' => 'int|string|null',
        ];
        $data = [
            'target_attribute_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'api_key' => 'klevu-js-api-key-' . random_int(1, 999999999),
            'last_action' => Actions::NO_ACTION,
            'next_action' => Actions::UPDATE,
            'is_indexable' => true,
        ];
        unset($data[$field]);

        $indexingAttribute = $this->objectManager->get(IndexingAttributeInterface::class);
        $indexingAttribute->setData($data);

        $validator = $this->instantiateTestObject();
        $this->assertFalse($validator->isValid($indexingAttribute));

        $this->assertSame(
            expected: sprintf(
                'Incorrect data type provided for %s. Expected %s, received %s.',
                $field,
                $fieldTypes[$field],
                'null',
            ),
            actual: $validator->getMessages()[0] ?? '',
        );
    }

    /**
     * @return mixed[][]
     */
    public function dataProvider_testIsValid_ReturnsFalse_MissingRequiredData(): array
    {
        return [
            ['target_attribute_type'],
            ['target_id'],
            ['api_key'],
            ['last_action'],
            ['next_action'],
            ['is_indexable'],
        ];
    }

    /**
     * @dataProvider dataProvider_testIsValid_ReturnsFalse_InvalidData
     */
    public function testIsValid_ReturnsFalse_InvalidData(string $field, mixed $invalidData): void
    {
        $fieldTypes = [
            'target_attribute_type' => 'string',
            'target_id' => 'int',
            'api_key' => 'string',
            'last_action' => Actions::class,
            'next_action' => Actions::class,
            'is_indexable' => 'bool',
            'lock_timestamp' => 'int|string|null',
            'last_action_timestamp' => 'int|string|null',
        ];

        $data = [
            'target_attribute_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'api_key' => 'klevu-js-api-key-' . random_int(1, 999999999),
            'last_action' => Actions::UPDATE,
            'next_action' => Actions::DELETE,
            'is_indexable' => true,
        ];
        $data[$field] = $invalidData;

        $indexingAttribute = $this->objectManager->get(IndexingAttributeInterface::class);
        $indexingAttribute->setData($data);

        $validator = $this->instantiateTestObject();
        $this->assertFalse($validator->isValid($indexingAttribute));

        $this->assertSame(
            expected: sprintf(
                'Incorrect data type provided for %s. Expected %s, received %s.',
                $field,
                $fieldTypes[$field],
                get_debug_type($invalidData),
            ),
            actual: $validator->getMessages()[0] ?? '',
        );
    }

    /**
     * @return mixed[][]
     */
    public function dataProvider_testIsValid_ReturnsFalse_InvalidData(): array
    {
        return [
            ['target_attribute_type', 1],
            ['target_id', 'string'],
            ['api_key', 1],
            ['last_action', ''],
            ['next_action', 'Add'],
            ['is_indexable', 'string'],
            ['lock_timestamp', true],
            ['last_action_timestamp', false],
        ];
    }

    /**
     * @dataProvider dataProvider_testIsValid_ReturnsFalse_VarCharExceedsMaxLength
     */
    public function testIsValid_ReturnsFalse_VarCharExceedsMaxLength(string $field, mixed $invalidData): void
    {
        $maxFieldLengths = [
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 63,
            IndexingAttribute::API_KEY => 31,
        ];

        $data = [
            'target_attribute_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'api_key' => 'klevu-js-api-key-' . random_int(1, 999999999),
            'next_action' => Actions::NO_ACTION,
            'last_action' => Actions::UPDATE,
            'is_indexable' => true,
        ];
        $data[$field] = $invalidData;

        $indexingAttribute = $this->objectManager->get(IndexingAttributeInterface::class);
        $indexingAttribute->setData($data);

        $validator = $this->instantiateTestObject();
        $this->assertFalse($validator->isValid($indexingAttribute));

        $this->assertSame(
            expected: sprintf(
                'Invalid data provided for %s. Expected max string length %s, received %s.',
                $field,
                $maxFieldLengths[$field],
                strlen($data[$field]),
            ),
            actual: $validator->getMessages()[0] ?? '',
        );
    }

    /**
     * @return mixed[][]
     */
    public function dataProvider_testIsValid_ReturnsFalse_VarCharExceedsMaxLength(): array
    {
        return [
            // phpcs:ignore Generic.Files.LineLength.TooLong
            ['target_attribute_type', 'the maximum number of characters allowed for target_attribute_type is 63 characters'],
            ['api_key', 'max api_key length is 31 characters'],
        ];
    }

    public function testIsValid_ReturnsTrue_CorrectData(): void
    {
        $data = [
            'target_attribute_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'api_key' => 'klevu-js-api-key-' . random_int(1, 999999999),
            'next_action' => Actions::DELETE,
            'lock_timestamp' => date('Y-m-d H:i:s'),
            'last_action' => Actions::ADD,
            'last_action_timestamp' => date('Y-m-d H:i:s', time() - 3600),
            'is_indexable' => true,
        ];
        $indexingAttribute = $this->objectManager->get(IndexingAttributeInterface::class);
        $indexingAttribute->setData($data);

        $validator = $this->instantiateTestObject();
        $this->assertTrue(condition: $validator->isValid($indexingAttribute), message: 'Is Valid');
        $this->assertFalse(condition: $validator->hasMessages(), message: 'Has Messages');
    }
}
