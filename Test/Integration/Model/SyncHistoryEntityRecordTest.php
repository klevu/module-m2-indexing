<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityRecord as SyncHistoryEntityRecordResourceModel;
use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityRecord\Collection;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
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
 * @covers SyncHistoryEntityRecord::class
 * @method SyncHistoryEntityRecordInterface instantiateTestObject(?array $arguments = null)
 * @method SyncHistoryEntityRecordInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SyncHistoryEntityRecordTest extends TestCase
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

        $this->implementationFqcn = SyncHistoryEntityRecord::class;
        $this->interfaceFqcn = SyncHistoryEntityRecordInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testCanSaveAndLoad(): void
    {
        $record = $this->createRecord();
        /** @var AbstractModel|SyncHistoryEntityRecordInterface $recordToLoad */
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
        $this->assertStringContainsString(
            needle: 'klevu-js-api-key-',
            haystack: $recordToLoad->getApiKey(),
        );
        $this->assertSame(
            expected: $record->getApiKey(),
            actual: $recordToLoad->getApiKey(),
        );
        $this->assertSame(
            expected: 'Add',
            actual: $recordToLoad->getAction()->value,
        );
        $this->assertSame(
            expected: $record->getAction(),
            actual: $recordToLoad->getAction(),
        );
        $this->assertNotNull(
            actual: $record->getActionTimestamp(),
        );
        $this->assertSame(
            expected: $record->getActionTimestamp(),
            actual: $recordToLoad->getActionTimestamp(),
        );
        $this->assertTrue(
            condition: $record->getIsSuccess(),
            message: 'Is Success',
        );
        $this->assertSame(
            expected: $record->getIsSuccess(),
            actual: $recordToLoad->getIsSuccess(),
        );
        $this->assertSame(
            expected: 'Sync Successful',
            actual: $recordToLoad->getMessage(),
        );
        $this->assertSame(
            expected: $record->getMessage(),
            actual: $recordToLoad->getMessage(),
        );
    }

    public function testCanLoadMultipleRecords(): void
    {
        $recordA = $this->createRecord();
        $recordB = $this->createRecord([
            'target_entity_type' => 'KLEVU_CATEGORY',
            'target_id' => 2,
            'target_parent_id' => 3,
            'action' => Actions::UPDATE,
            'action_timestamp' => date(format: 'Y-m-d H:i:s', timestamp: time()),
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

    /**
     * @param mixed[]|null $data
     *
     * @return SyncHistoryEntityRecordInterface
     * @throws AlreadyExistsException
     */
    private function createRecord(?array $data = []): SyncHistoryEntityRecordInterface
    {
        /** @var SyncHistoryEntityRecordInterface&AbstractModel $record */
        $record = $this->instantiateTestObject([]);
        $record->setTargetEntityType(entityType: $data[SyncHistoryEntityRecord::TARGET_ENTITY_TYPE] ?? 'KLEVU_PRODUCT');
        $record->setTargetId(targetId: $data[SyncHistoryEntityRecord::TARGET_ID] ?? 1);
        $record->setTargetParentId(targetParentId: $data[SyncHistoryEntityRecord::TARGET_PARENT_ID] ?? null);
        $record->setApiKey(
            apiKey: $data[SyncHistoryEntityRecord::API_KEY] ?? 'klevu-js-api-key-' . random_int(min: 0, max: 999999999),
        );
        $record->setAction(action: $data[SyncHistoryEntityRecord::ACTION] ?? Actions::ADD);
        $record->setActionTimestamp(
            actionTimestamp: $data[SyncHistoryEntityRecord::ACTION_TIMESTAMP] ?? date(format: 'Y-m-d H:i:s'),
        );
        $record->setIsSuccess(success: $data[SyncHistoryEntityRecord::IS_SUCCESS] ?? true);
        $record->setMessage(message: $data[SyncHistoryEntityRecord::MESSAGE] ?? 'Sync Successful');

        $resourceModel = $this->instantiateResourceModel();
        $resourceModel->save(object: $record);

        return $record;
    }

    /**
     * @return SyncHistoryEntityRecordResourceModel
     */
    private function instantiateResourceModel(): SyncHistoryEntityRecordResourceModel
    {
        return $this->objectManager->get(type: SyncHistoryEntityRecordResourceModel::class);
    }

    /**
     * @return Collection
     */
    private function instantiateCollection(): Collection
    {
        return $this->objectManager->get(Collection::class);
    }
}
