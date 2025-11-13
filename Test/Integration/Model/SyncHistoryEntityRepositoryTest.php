<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Exception\CouldNotDeleteException;
use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityRecord as SyncHistoryEntityRecordResourceModel;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\Indexing\Model\SyncHistoryEntityRepository;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers SyncHistoryEntityRepository
 * @method SyncHistoryEntityRepositoryInterface instantiateTestObject(?array $arguments = null)
 * @method SyncHistoryEntityRepositoryInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SyncHistoryEntityRepositoryTest extends TestCase
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

        $this->objectManager = Bootstrap::getObjectManager();
        $this->implementationFqcn = SyncHistoryEntityRepository::class;
        $this->interfaceFqcn = SyncHistoryEntityRepositoryInterface::class;
    }

    public function testCreate_ReturnsSyncHistoryEntityModel(): void
    {
        $repository = $this->instantiateTestObject();
        $result = $repository->create();

        $this->assertInstanceOf(
            expected: SyncHistoryEntityRecordInterface::class,
            actual: $result,
        );
    }

    public function testGetById_ThrowsException_WhenIdDoesNotExist(): void
    {
        $entityId = 999999999;

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(
            sprintf('No such entity with entity_id = %s', $entityId),
        );

        $repository = $this->instantiateTestObject();
        $repository->getById((int)$entityId);
    }

    public function testGetById_ReturnsModel_WhenIdExists(): void
    {
        $origRecord = $this->createSyncHistoryRecord();

        $repository = $this->instantiateTestObject();
        $record = $repository->getById($origRecord->getId());

        $this->assertSame(
            expected: (int)$origRecord->getId(),
            actual: $record->getId(),
        );

        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $record->getTargetEntityType(),
        );
        $this->assertSame(
            expected: $origRecord->getTargetEntityType(),
            actual: $record->getTargetEntityType(),
        );
        $this->assertSame(
            expected: 1,
            actual: $record->getTargetId(),
        );
        $this->assertSame(
            expected: $origRecord->getTargetId(),
            actual: $record->getTargetId(),
        );
        $this->assertStringContainsString(
            needle: 'klevu-js-api-key-',
            haystack: $record->getApiKey(),
        );
        $this->assertSame(
            expected: $origRecord->getApiKey(),
            actual: $record->getApiKey(),
        );
        $this->assertSame(
            expected: 'Add',
            actual: $record->getAction()->value,
        );
        $this->assertSame(
            expected: $origRecord->getAction(),
            actual: $record->getAction(),
        );
        $this->assertNotNull(
            actual: $origRecord->getActionTimestamp(),
        );
        $this->assertSame(
            expected: $origRecord->getActionTimestamp(),
            actual: $record->getActionTimestamp(),
        );
        $this->assertTrue(
            condition: $origRecord->getIsSuccess(),
            message: 'Is Success',
        );
        $this->assertSame(
            expected: $origRecord->getIsSuccess(),
            actual: $record->getIsSuccess(),
        );
        $this->assertSame(
            expected: 'Sync Successful',
            actual: $record->getMessage(),
        );
        $this->assertSame(
            expected: $origRecord->getMessage(),
            actual: $record->getMessage(),
        );
    }

    public function testSave_SavesNewSyncHistoryRecord(): void
    {
        $repository = $this->instantiateTestObject();
        $record = $repository->create();
        $record->setTargetEntityType(entityType: 'KLEVU_PRODUCT');
        $record->setTargetId(targetId: 1);
        $record->setTargetParentId(targetParentId: 2);
        $record->setApiKey(apiKey: 'klevu-test-api-key');
        $record->setAction(action: Actions::ADD);
        $record->setActionTimestamp(actionTimestamp: date('Y-m-d H:i:s'));
        $record->setIsSuccess(success: true);
        $record->setMessage('Sync Successful');

        $repository->save($record);

        $this->assertNotNull(actual: $record->getId());
    }

    public function testSave_UpdatesExistingRecord(): void
    {
        $repository = $this->instantiateTestObject();
        $record = $repository->create();
        $record->setTargetEntityType(entityType: 'KLEVU_PRODUCT');
        $record->setTargetId(targetId: 1);
        $record->setTargetParentId(targetParentId: 2);
        $record->setApiKey(apiKey: 'klevu-test-api-key');
        $record->setAction(action: Actions::ADD);
        $record->setActionTimestamp(actionTimestamp: date('Y-m-d H:i:s'));
        $record->setIsSuccess(success: false);
        $record->setMessage('There was an error');
        $savedRecord = $repository->save($record);

        $this->assertNotNull(actual: $record->getId());
        $this->assertFalse(
            condition: $savedRecord->getIsSuccess(),
            message: 'Is Success',
        );
        $this->assertSame(
            expected: 'There was an error',
            actual: $savedRecord->getMessage(),
        );

        $record->setIsSuccess(success: true);
        $record->setMessage('Sync Successful');
        $updatedRecord = $repository->save($record);

        $this->assertNotNull(actual: $record->getId());
        $this->assertTrue(
            condition: $updatedRecord->getIsSuccess(),
            message: 'Is Success',
        );
        $this->assertSame(
            expected: 'Sync Successful',
            actual: $updatedRecord->getMessage(),
        );
    }

    public function testSave_ThrowsException_ForInvalidData(): void
    {
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessageMatches('#Could not save Sync History Record: .*#');

        $repository = $this->instantiateTestObject();
        $record = $repository->create();
        $record->setTargetEntityType(entityType: 'KLEVU_PRODUCT');
        $record->setTargetId(targetId: 1);
        $record->setTargetParentId(targetParentId: 2);
        $record->setApiKey(apiKey: 'klevu-test-api-key');
        $record->setAction(action: Actions::ADD);
        $record->setActionTimestamp(actionTimestamp: date('Y-m-d H:i:s'));
        $record->setIsSuccess(success: true);
        $record->setMessage('Save Successful');

        $record->setData('target_id', 'not an integer');

        $repository->save($record);
    }

    public function testSave_ThrowsAlreadyExistsException(): void
    {
        $record = $this->createSyncHistoryRecord();

        $mockMessage = 'Entity Already Exists';
        $this->expectException(AlreadyExistsException::class);
        $this->expectExceptionMessage($mockMessage);

        $exception = new AlreadyExistsException(__($mockMessage));
        $mockResourceModel = $this->getMockBuilder(SyncHistoryEntityRecordResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('save')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'syncHistoryEntityRecordResourceModel' => $mockResourceModel,
        ]);

        $repository->save($record);
    }

    public function testSave_HandlesOtherExceptions(): void
    {
        $record = $this->createSyncHistoryRecord();

        $mockMessage = 'Some core exception message.';
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage(sprintf('Could not save Sync History Record: %s', $mockMessage));

        $exception = new \Exception($mockMessage);
        $mockResourceModel = $this->getMockBuilder(SyncHistoryEntityRecordResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('save')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'syncHistoryEntityRecordResourceModel' => $mockResourceModel,
        ]);

        $repository->save($record);
    }

    public function testDelete_RemovesSyncHistoryRecord(): void
    {
        $record = $this->createSyncHistoryRecord();
        $entityId = $record->getId();
        $this->assertNotNull(actual: $entityId);

        $repository = $this->instantiateTestObject();
        $repository->getById((int)$entityId);
        $repository->delete(syncHistoryEntityRecord: $record);

        // if record has been deleted, getById will throw an exception
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(sprintf('No such entity with entity_id = %s', $entityId));
        $repository->getById((int)$entityId);
    }

    public function testDelete_ThrowsLocalizedException(): void
    {
        $record = $this->createSyncHistoryRecord();

        $mockMessage = 'A localized exception message';
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($mockMessage);

        $exception = new LocalizedException(__($mockMessage));
        $mockResourceModel = $this->getMockBuilder(SyncHistoryEntityRecordResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('delete')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'syncHistoryEntityRecordResourceModel' => $mockResourceModel,
        ]);
        $repository->delete($record);
    }

    public function testDelete_HandlesOtherExceptions(): void
    {
        $record = $this->createSyncHistoryRecord();

        $mockMessage = 'An exception message';
        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage(sprintf('Could not delete Sync History Record: %s', $mockMessage));

        $exception = new \Exception($mockMessage);
        $mockResourceModel = $this->getMockBuilder(SyncHistoryEntityRecordResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('delete')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'syncHistoryEntityRecordResourceModel' => $mockResourceModel,
        ]);
        $repository->delete($record);
    }

    public function testDeleteById_ThrowsException_WhenEntityDoesNotExist(): void
    {
        $entityId = -1;
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(sprintf('No such entity with entity_id = %s', $entityId));

        $repository = $this->instantiateTestObject();
        $repository->deleteById($entityId);
    }

    public function testDeleteById_RemovesSyncHistoryRecord(): void
    {
        $record = $this->createSyncHistoryRecord();
        $entityId = $record->getId();
        $this->assertNotNull(actual: $entityId);

        $repository = $this->instantiateTestObject();
        $repository->deleteById($entityId);

        // if record has been deleted, getById will throw an exception
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(sprintf('No such entity with entity_id = %s', $entityId));
        $repository->getById((int)$entityId);
    }

    public function testGetList_WhenNoResultsFound(): void
    {
        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: 'entity_id',
            value: 0,
            conditionType: 'lt',
        );
        $searchCriteria = $searchCriteriaBuilder->create();

        $repository = $this->instantiateTestObject();
        $searchResult = $repository->getList($searchCriteria);

        $this->assertEquals(0, $searchResult->getTotalCount());
        $this->assertEmpty($searchResult->getItems());
        $this->assertSame($searchCriteria, $searchResult->getSearchCriteria());
    }

    public function testGetList_withResults(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanSyncHistoryEntities($apiKey);

        $this->createSyncHistoryRecord([
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            'target_id' => 2,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);
        $this->createSyncHistoryRecord([
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);
        $this->createSyncHistoryRecord([
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => 5,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);
        $this->createSyncHistoryRecord([
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 2,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);
        $this->createSyncHistoryRecord([
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 3,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);
        $this->createSyncHistoryRecord([
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 4,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);

        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();

        $sortOrderBuilder = $this->objectManager->get(SortOrderBuilder::class);
        $sortOrderBuilder->setField('target_id');
        $sortOrderBuilder->setAscendingDirection();
        $sortOrder = $sortOrderBuilder->create();
        $searchCriteriaBuilder->setSortOrders([$sortOrder]);

        $searchCriteriaBuilder->addFilter(
            field: SyncHistoryEntityRecord::TARGET_ENTITY_TYPE,
            value: 'KLEVU_PRODUCT',
        );
        $searchCriteriaBuilder->addFilter(
            field: SyncHistoryEntityRecord::API_KEY,
            value: $apiKey,
        );
        $searchCriteriaBuilder->setPageSize(2);
        $searchCriteriaBuilder->setCurrentPage(2);
        $searchCriteria = $searchCriteriaBuilder->create();

        $repository = $this->instantiateTestObject();
        $searchResult = $repository->getList($searchCriteria);

        $this->assertSame($searchCriteria, $searchResult->getSearchCriteria());
        // total number of items available
        $this->assertEquals(4, $searchResult->getTotalCount());
        $items = $searchResult->getItems();
        // paginated number of items on this page
        $this->assertCount(expectedCount: 2, haystack: $items);
        // get target ids and ensure we are on page 2
        $targetIds = array_map(static fn (SyncHistoryEntityRecordInterface $syncHistoryEntityRecord): int => (
            $syncHistoryEntityRecord->getTargetId()
        ), $items);
        $this->assertContains(3, $targetIds);
        $this->assertContains(4, $targetIds);
    }

    /**
     * @param mixed[]|null $data
     *
     * @return SyncHistoryEntityRecordInterface
     * @throws AlreadyExistsException
     */
    private function createSyncHistoryRecord(?array $data = []): SyncHistoryEntityRecordInterface
    {
        /** @var SyncHistoryEntityRecordInterface&AbstractModel $record */
        $record = $this->instantiateModel();
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
     * @return SyncHistoryEntityRecordInterface
     */
    private function instantiateModel(): SyncHistoryEntityRecordInterface
    {
        // use create to prevent caching between tests
        // is more performant than @magentoAppIsolation
        return $this->objectManager->create(
            type: SyncHistoryEntityRecordInterface::class,
        );
    }

    /**
     * @return SyncHistoryEntityRecordResourceModel
     */
    private function instantiateResourceModel(): SyncHistoryEntityRecordResourceModel
    {
        return $this->objectManager->get(
            type: SyncHistoryEntityRecordResourceModel::class,
        );
    }

    /**
     * @param string $apiKey
     *
     * @return void
     */
    private function cleanSyncHistoryEntities(string $apiKey): void
    {
        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: SyncHistoryEntityRecord::API_KEY,
            value: $apiKey,
            conditionType: 'like',
        );
        $searchCriteria = $searchCriteriaBuilder->create();
        /** @var SyncHistoryEntityRepositoryInterface $repository */
        $repository = $this->objectManager->get(SyncHistoryEntityRepositoryInterface::class);
        $recordsToDelete = $repository->getList($searchCriteria);
        foreach ($recordsToDelete->getItems() as $record) {
            try {
                $repository->delete(syncHistoryEntityRecord: $record);
            } catch (LocalizedException) {
                // do nothing
            }
        }
    }
}
