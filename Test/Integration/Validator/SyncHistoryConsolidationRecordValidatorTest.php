<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Validator;

use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\Indexing\Validator\SyncHistoryConsolidationRecordValidator;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SyncHistoryConsolidationRecordValidatorTest extends TestCase
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
        $this->implementationFqcn = SyncHistoryConsolidationRecordValidator::class;
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
                SyncHistoryEntityConsolidationRecordInterface::class,
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
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'string',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 'int',
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 'int|null',
            SyncHistoryEntityConsolidationRecord::API_KEY => 'string',
            SyncHistoryEntityConsolidationRecord::HISTORY => 'string',
            SyncHistoryEntityConsolidationRecord::DATE => 'int|string',
        ];
        $data = [
            'target_entity_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'api_key' => 'klevu-js-api-key',
            'history' => json_encode(
                [
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::ADD,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => true,
                        SyncHistoryEntityRecord::MESSAGE => 'Success',
                    ],
                ],
            ),
            'date' => date('Y-m-d'),
        ];
        unset($data[$field]);

        $syncHistoryRecord = $this->objectManager->get(SyncHistoryEntityConsolidationRecordInterface::class);
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
            ['history'],
            ['date'],
        ];
    }

    /**
     * @dataProvider dataProvider_testIsValid_ReturnsFalse_InvalidData
     */
    public function testIsValid_ReturnsFalse_InvalidData(string $field, mixed $invalidData): void
    {
        $fieldTypes = [
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'string',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 'int',
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 'int|null',
            SyncHistoryEntityConsolidationRecord::API_KEY => 'string',
            SyncHistoryEntityConsolidationRecord::HISTORY => 'string',
            SyncHistoryEntityConsolidationRecord::DATE => 'int|string',
        ];
        $data = [
            'target_entity_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'target_parent_id' => 2,
            'api_key' => 'klevu-js-api-key-' . random_int(1, 999999999),
            'history' => json_encode(
                [
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => false,
                        SyncHistoryEntityRecord::MESSAGE => 'Rejected',
                    ],
                ],
            ),
            'date' => date('Y-m-d'),
        ];
        $data[$field] = $invalidData;

        $syncHistoryRecord = $this->objectManager->get(SyncHistoryEntityConsolidationRecordInterface::class);
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
            ['history', ['string']],
            ['date', false],
        ];
    }

    /**
     * @dataProvider dataProvider_testIsValid_ReturnsFalse_VarCharExceedsMaxLength
     */
    public function testIsValid_ReturnsFalse_VarCharExceedsMaxLength(string $field, mixed $invalidData): void
    {
        $maxFieldLengths = [
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 63,
            SyncHistoryEntityConsolidationRecord::API_KEY => 31,
        ];

        $data = [
            'target_entity_type' => 'KLEVU_PRODUCT',
            'target_id' => 1,
            'target_parent_id' => 2,
            'api_key' => 'klevu-js-api-key',
            'history' => json_encode(
                [
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::DELETE,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => true,
                        SyncHistoryEntityRecord::MESSAGE => 'Success',
                    ],
                ],
            ),
            'date' => date('Y-m-d'),
        ];
        $data[$field] = $invalidData;

        $syncHistoryRecord = $this->objectManager->get(SyncHistoryEntityConsolidationRecordInterface::class);
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
            'api_key' => 'klevu-js-api-key',
            'history' => json_encode(
                [
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::DELETE,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => true,
                        SyncHistoryEntityRecord::MESSAGE => 'Success',
                    ],
                ],
            ),
            'date' => date('Y-m-d'),
        ];
        $indexingEntity = $this->objectManager->get(SyncHistoryEntityConsolidationRecordInterface::class);
        $indexingEntity->setData($data);

        $validator = $this->instantiateTestObject();
        $this->assertTrue(condition: $validator->isValid($indexingEntity), message: 'Is Valid');
        $this->assertFalse(condition: $validator->hasMessages(), message: 'Has Messages');
    }
}
