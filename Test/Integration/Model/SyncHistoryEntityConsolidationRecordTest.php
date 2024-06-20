<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityConsolidationRecord as SyncHistoryEntityConsolidationRecordResourceModel; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityConsolidationRecord\Collection;
use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers SyncHistoryEntityConsolidationRecord::class
 * @method SyncHistoryEntityConsolidationRecordInterface instantiateTestObject(?array $arguments = null)
 * @method SyncHistoryEntityConsolidationRecordInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SyncHistoryEntityConsolidationRecordTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

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

        $this->implementationFqcn = SyncHistoryEntityConsolidationRecord::class;
        $this->interfaceFqcn = SyncHistoryEntityConsolidationRecordInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testCanSaveAndLoad(): void
    {
        $record = $this->createRecord();
        /** @var AbstractModel|SyncHistoryEntityConsolidationRecordInterface $recordToLoad */
        $recordToLoad = $this->instantiateTestObject();
        $resourceModel = $this->instantiateResourceModel();
        $resourceModel->load(
            object: $recordToLoad,
            value: $record->getId(),
        );

        $this->assertSame(
            expected: (int)$record->getId(),
            actual: $recordToLoad->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $recordToLoad->getTargetEntityType(),
        );
        $this->assertSame(
            expected: $record->getTargetEntityType(),
            actual: $recordToLoad->getTargetEntityType(),
        );
        $this->assertSame(
            expected: 1,
            actual: $recordToLoad->getTargetId(),
        );
        $this->assertSame(
            expected: $record->getTargetId(),
            actual: $recordToLoad->getTargetId(),
        );
        $this->assertSame(
            expected: 'klevu-js-api-key',
            actual: $recordToLoad->getApiKey(),
        );
        $this->assertSame(
            expected: $record->getApiKey(),
            actual: $recordToLoad->getApiKey(),
        );
        $this->assertSame(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            expected: '[{"action":"Add","action_timestamp":"2024-05-14 12:02:11","is_success":true,"message":"Batch Accepted Successfully"}]',
            actual: $recordToLoad->getHistory(),
        );
        $this->assertSame(
            expected: $record->getHistory(),
            actual: $recordToLoad->getHistory(),
        );
        $this->assertNotNull(
            actual: $record->getDate(),
        );
        $this->assertSame(
            expected: $record->getDate(),
            actual: $recordToLoad->getDate(),
        );
    }

    public function testCanLoadMultipleRecords(): void
    {
        $recordA = $this->createRecord();
        $recordB = $this->createRecord([
            'target_entity_type' => 'KLEVU_CATEGORY',
            'target_id' => 2,
            'target_parent_id' => 3,
            'history' => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(
                        format: 'Y-m-d H:i:s',
                        timestamp: time() - 60 * 60 * 24,
                    ),
                    SyncHistoryEntityRecord::IS_SUCCESS => false,
                    SyncHistoryEntityRecord::MESSAGE => 'Batch Rejected',
                ],
            ],
            'date' => date(format: 'Y-m-d', timestamp: time() - 60 * 60 * 24),
        ]);

        $collection = $this->instantiateCollection();
        $items = $collection->getItems();
        $this->assertContains(
            needle: (int)$recordA->getId(),
            haystack: array_keys($items),
        );
        $this->assertContains(
            needle: (int)$recordB->getId(),
            haystack: array_keys($items),
        );
    }

    public function testSave_ThrowsException_WhenHistoryIsNotValidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $record = $this->createRecord();
        $resourceModel = $this->instantiateResourceModel();

        $record->setData(
            key: SyncHistoryEntityConsolidationRecord::HISTORY,
            value: '[{"action":"Add","action_timestamp":"2024-05-14 12:02:11","is_success":true,"message]',
        );
        $resourceModel->save($record);
    }

    /**
     * @param mixed[]|null $data
     *
     * @return SyncHistoryEntityConsolidationRecordInterface&AbstractModel
     * @throws AlreadyExistsException
     * @throws \JsonException
     */
    private function createRecord(?array $data = []): SyncHistoryEntityConsolidationRecordInterface
    {
        $history = $data[SyncHistoryEntityConsolidationRecord::HISTORY] ?? [
            [
                SyncHistoryEntityRecord::ACTION => Actions::ADD,
                SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2024-05-14 12:02:11',
                SyncHistoryEntityRecord::IS_SUCCESS => true,
                SyncHistoryEntityRecord::MESSAGE => 'Batch Accepted Successfully',
            ],
        ];

        /** @var SyncHistoryEntityConsolidationRecordInterface&AbstractModel $record */
        $record = $this->instantiateTestObject([]);
        $record->setTargetEntityType(
            entityType: $data[SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE] ?? 'KLEVU_PRODUCT',
        );
        $record->setTargetId(
            targetId: $data[SyncHistoryEntityConsolidationRecord::TARGET_ID] ?? 1,
        );
        $record->setTargetParentId(
            targetParentId: $data[SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID] ?? null,
        );
        $record->setApiKey(
            apiKey: $data[SyncHistoryEntityConsolidationRecord::API_KEY] ?? 'klevu-js-api-key',
        );
        $record->setHistory(
            history: json_encode($history, JSON_THROW_ON_ERROR),
        );
        $record->setDate(
            date: $data[SyncHistoryEntityConsolidationRecord::DATE] ?? '2024-05-14',
        );

        $resourceModel = $this->instantiateResourceModel();
        $resourceModel->save(object: $record);

        return $record;
    }

    /**
     * @return SyncHistoryEntityConsolidationRecordResourceModel
     */
    private function instantiateResourceModel(): SyncHistoryEntityConsolidationRecordResourceModel
    {
        return $this->objectManager->get(type: SyncHistoryEntityConsolidationRecordResourceModel::class);
    }

    /**
     * @return Collection
     */
    private function instantiateCollection(): Collection
    {
        return $this->objectManager->get(Collection::class);
    }
}
