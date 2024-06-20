<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Validator;

use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\Indexing\Validator\SyncHistoryRecordValidator;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SyncHistoryRecordValidatorTest extends TestCase
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
        $this->implementationFqcn = SyncHistoryRecordValidator::class;
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
                SyncHistoryEntityRecordInterface::class,
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
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'string',
            SyncHistoryEntityRecord::TARGET_ID => 'int',
            SyncHistoryEntityRecord::TARGET_PARENT_ID => 'int|null',
            SyncHistoryEntityRecord::API_KEY => 'string',
            SyncHistoryEntityRecord::ACTION => Actions::class,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => 'int|string',
            SyncHistoryEntityRecord::IS_SUCCESS => 'bool',
            SyncHistoryEntityRecord::MESSAGE => 'string|null',
        ];
        $data = [
            'target_entity_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'api_key' => 'klevu-js-api-key-' . random_int(1, 999999999),
            'action' => Actions::NO_ACTION,
            'action_timestamp' => date('Y-m-d H:i:s'),
            'is_success' => true,
        ];
        unset($data[$field]);

        $syncHistoryRecord = $this->objectManager->get(SyncHistoryEntityRecordInterface::class);
        $syncHistoryRecord->setData($data);

        $validator = $this->instantiateTestObject();
        $this->assertFalse($validator->isValid($syncHistoryRecord));

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
            ['target_entity_type'],
            ['target_id'],
            ['api_key'],
            ['action'],
            ['action_timestamp'],
            ['is_success'],
        ];
    }

    /**
     * @dataProvider dataProvider_testIsValid_ReturnsFalse_InvalidData
     */
    public function testIsValid_ReturnsFalse_InvalidData(string $field, mixed $invalidData): void
    {
        $fieldTypes = [
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'string',
            SyncHistoryEntityRecord::TARGET_ID => 'int',
            SyncHistoryEntityRecord::TARGET_PARENT_ID => 'int|null',
            SyncHistoryEntityRecord::API_KEY => 'string',
            SyncHistoryEntityRecord::ACTION => Actions::class,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => 'int|string',
            SyncHistoryEntityRecord::IS_SUCCESS => 'bool',
            SyncHistoryEntityRecord::MESSAGE => 'string|null',
        ];
        $data = [
            'target_entity_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'target_parent_id' => 2,
            'api_key' => 'klevu-js-api-key-' . random_int(1, 999999999),
            'action' => Actions::UPDATE,
            'action_timestamp' => date('Y-m-d h"i"s'),
            'is_success' => true,
            'message' => 'Sync Successful',
        ];
        $data[$field] = $invalidData;

        $syncHistoryRecord = $this->objectManager->get(SyncHistoryEntityRecordInterface::class);
        $syncHistoryRecord->setData($data);

        $validator = $this->instantiateTestObject();
        $this->assertFalse($validator->isValid($syncHistoryRecord));

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
            ['target_entity_type', 1],
            ['target_id', 'string'],
            ['target_parent_id', 'string'],
            ['api_key', 1],
            ['action', 'Add'],
            ['action_timestamp', false],
            ['is_success', 'string'],
            ['message', new DataObject()],
        ];
    }

    /**
     * @dataProvider dataProvider_testIsValid_ReturnsFalse_VarCharExceedsMaxLength
     */
    public function testIsValid_ReturnsFalse_VarCharExceedsMaxLength(string $field, mixed $invalidData): void
    {
        $maxFieldLengths = [
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 63,
            SyncHistoryEntityRecord::API_KEY => 31,
        ];

        $data = [
            'target_entity_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'target_parent_id' => 2,
            'api_key' => 'klevu-js-api-key-' . random_int(1, 999999999),
            'action' => Actions::UPDATE,
            'action_timestamp' => date('Y-m-d h"i"s'),
            'is_success' => true,
            'message' => 'Sync Successful',
        ];
        $data[$field] = $invalidData;

        $syncHistoryRecord = $this->objectManager->get(SyncHistoryEntityRecordInterface::class);
        $syncHistoryRecord->setData($data);

        $validator = $this->instantiateTestObject();
        $this->assertFalse($validator->isValid($syncHistoryRecord));

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
            ['target_entity_type', 'the maximum number of characters allowed for target_entity_type is 63 characters'],
            ['api_key', 'max api_key length is 31 characters'],
        ];
    }

    public function testIsValid_ReturnsTrue_CorrectData(): void
    {
        $data = [
            'target_entity_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'target_parent_id' => 2,
            'api_key' => 'klevu-js-api-key-' . random_int(1, 999999999),
            'action' => Actions::UPDATE,
            'action_timestamp' => date('Y-m-d h"i"s'),
            'is_success' => true,
            'message' => 'Sync Successful',
        ];
        $indexingEntity = $this->objectManager->get(SyncHistoryEntityRecordInterface::class);
        $indexingEntity->setData($data);

        $validator = $this->instantiateTestObject();
        $this->assertTrue(condition: $validator->isValid($indexingEntity), message: 'Is Valid');
        $this->assertFalse(condition: $validator->hasMessages(), message: 'Has Messages');
    }
}
