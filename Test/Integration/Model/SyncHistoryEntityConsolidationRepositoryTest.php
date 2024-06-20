<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Exception\CouldNotDeleteException;
use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityConsolidationRecord as ConsolidationResourceModel;
use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRepository;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityConsolidationRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
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

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers SyncHistoryEntityConsolidationRepository
 * @method SyncHistoryEntityConsolidationRepositoryInterface instantiateTestObject(?array $arguments = null)
 * @method SyncHistoryEntityConsolidationRepositoryInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SyncHistoryEntityConsolidationRepositoryTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
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
        $this->implementationFqcn = SyncHistoryEntityConsolidationRepository::class;
        $this->interfaceFqcn = SyncHistoryEntityConsolidationRepositoryInterface::class;
    }

    public function testCreate_ReturnsRepository(): void
    {
        $repository = $this->instantiateTestObject();
        $result = $repository->create();

        $this->assertInstanceOf(
            expected: SyncHistoryEntityConsolidationRecordInterface::class,
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
        $origRecord = $this->createSyncHistoryConsolidationRecord();

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
        $this->assertSame(
            expected: 'klevu-js-api-key',
            actual: $record->getApiKey(),
        );
        $this->assertSame(
            expected: $origRecord->getApiKey(),
            actual: $record->getApiKey(),
        );
        $this->assertSame(
            expected: date('Y-m-d'),
            actual: $record->getDate(),
        );
        $this->assertSame(
            expected: $origRecord->getDate(),
            actual: $record->getDate(),
        );
        $this->assertSame(
            expected: $origRecord->getHistory(),
            actual: $record->getHistory(),
        );
    }

    public function testSave_SavesNewHistoryRecord(): void
    {
        $repository = $this->instantiateTestObject();
        $record = $repository->create();
        $record->setTargetEntityType(entityType: 'KLEVU_PRODUCT');
        $record->setTargetId(targetId: 1);
        $record->setTargetParentId(targetParentId: 2);
        $record->setApiKey(apiKey: 'klevu-test-api-key');
        $record->setHistory(
            history: json_encode(
                value: [
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::ADD,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => true,
                        SyncHistoryEntityRecord::MESSAGE => 'Success',
                    ],
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => false,
                        SyncHistoryEntityRecord::MESSAGE => 'Rejected',
                    ],
                ],
                flags: JSON_THROW_ON_ERROR,
            ),
        );
        $record->setDate(date: date('Y-m-d H:i:s'));

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
        $record->setHistory(
            history: json_encode(
                value: [
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::ADD,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => true,
                        SyncHistoryEntityRecord::MESSAGE => 'Success',
                    ],
                ],
                flags: JSON_THROW_ON_ERROR,
            ),
        );
        $record->setDate(date: date('Y-m-d H:i:s'));
        $savedRecord = $repository->save($record);

        $this->assertSame(
            expected: $record->getHistory(),
            actual: $savedRecord->getHistory(),
        );

        $record->setHistory(
            history: json_encode(
                value: [
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => false,
                        SyncHistoryEntityRecord::MESSAGE => 'Rejected',
                    ],
                ],
                flags: JSON_THROW_ON_ERROR,
            ),
        );
        $updatedRecord = $repository->save($record);

        $this->assertSame(
            expected: $record->getHistory(),
            actual: $updatedRecord->getHistory(),
        );
        $this->assertNotSame(
            expected: $savedRecord->getHistory(),
            actual: $updatedRecord->getHistory(),
        );
    }

    public function testSave_ThrowsException_ForInvalidData(): void
    {
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessageMatches('#Could not save Consolidated Sync History Record: .*#');

        $repository = $this->instantiateTestObject();
        $record = $repository->create();
        $record->setTargetEntityType(entityType: 'KLEVU_PRODUCT');
        $record->setTargetId(targetId: 1);
        $record->setTargetParentId(targetParentId: 2);
        $record->setApiKey(apiKey: 'klevu-test-api-key');
        $record->setHistory(
            history: json_encode(
                value: [
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::ADD,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => true,
                        SyncHistoryEntityRecord::MESSAGE => 'Success',
                    ],
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => false,
                        SyncHistoryEntityRecord::MESSAGE => 'Rejected',
                    ],
                ],
                flags: JSON_THROW_ON_ERROR,
            ),
        );
        $record->setDate(date: date('Y-m-d H:i:s'));

        $record->setData('target_id', 'not an integer');

        $repository->save($record);
    }

    public function testSave_ThrowsAlreadyExistsException(): void
    {
        $mockMessage = 'Entity Already Exists';
        $this->expectException(AlreadyExistsException::class);
        $this->expectExceptionMessage($mockMessage);

        $exception = new AlreadyExistsException(__($mockMessage));
        $mockResourceModel = $this->getMockBuilder(ConsolidationResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('save')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'consolidationResourceModel' => $mockResourceModel,
        ]);
        $record = $this->createSyncHistoryConsolidationRecord();
        $repository->save($record);
    }

    public function testSave_HandlesOtherExceptions(): void
    {
        $mockMessage = 'Some core exception message.';
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage(
            sprintf('Could not save Sync History Consolidation Record: %s', $mockMessage),
        );

        $exception = new \Exception($mockMessage);
        $mockResourceModel = $this->getMockBuilder(ConsolidationResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('save')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'consolidationResourceModel' => $mockResourceModel,
        ]);
        $record = $this->createSyncHistoryConsolidationRecord();
        $repository->save($record);
    }

    public function testDelete_RemovesSyncHistoryRecord(): void
    {
        $record = $this->createSyncHistoryConsolidationRecord();
        $entityId = $record->getId();
        $this->assertNotNull(actual: $entityId);

        $repository = $this->instantiateTestObject();
        $repository->getById((int)$entityId);
        $repository->delete(syncHistoryEntityConsolidationRecord: $record);

        // if record has been deleted, getById will throw an exception
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(sprintf('No such entity with entity_id = %s', $entityId));
        $repository->getById((int)$entityId);
    }

    public function testDelete_ThrowsLocalizedException(): void
    {
        $record = $this->createSyncHistoryConsolidationRecord();

        $mockMessage = 'A localized exception message';
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($mockMessage);

        $exception = new LocalizedException(__($mockMessage));
        $mockResourceModel = $this->getMockBuilder(ConsolidationResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('delete')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'consolidationResourceModel' => $mockResourceModel,
        ]);
        $repository->delete($record);
    }

    public function testDelete_HandlesOtherExceptions(): void
    {
        $record = $this->createSyncHistoryConsolidationRecord();

        $mockMessage = 'An exception message';
        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage(
            sprintf('Could not delete Sync History Consolidation Record: %s', $mockMessage),
        );

        $exception = new \Exception($mockMessage);
        $mockResourceModel = $this->getMockBuilder(ConsolidationResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('delete')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'consolidationResourceModel' => $mockResourceModel,
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
        $record = $this->createSyncHistoryConsolidationRecord();

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

        $this->createSyncHistoryConsolidationRecord([
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 2,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);
        $this->createSyncHistoryConsolidationRecord([
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);
        $this->createSyncHistoryConsolidationRecord([
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 5,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);
        $this->createSyncHistoryConsolidationRecord([
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 2,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);
        $this->createSyncHistoryConsolidationRecord([
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityRecord::API_KEY => $apiKey,
        ]);
        $this->createSyncHistoryConsolidationRecord([
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 4,
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
            field: SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE,
            value: 'KLEVU_PRODUCT',
        );
        $searchCriteriaBuilder->addFilter(
            field: SyncHistoryEntityConsolidationRecord::API_KEY,
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
        $targetIds = array_map(
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $syncHistoryEntityRecord): int => (
                $syncHistoryEntityRecord->getTargetId()
            ),
            array: $items,
        );
        $this->assertContains(3, $targetIds);
        $this->assertContains(4, $targetIds);
    }

    /**
     * @param mixed[] $data
     *
     * @return SyncHistoryEntityConsolidationRecordInterface
     * @throws AlreadyExistsException
     * @throws \JsonException
     */
    private function createSyncHistoryConsolidationRecord(
        array $data = [],
    ): SyncHistoryEntityConsolidationRecordInterface {
        /** @var SyncHistoryEntityConsolidationRecordInterface&AbstractModel $record */
        $record = $this->instantiateModel();
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
            history: json_encode(
                value: $data[SyncHistoryEntityConsolidationRecord::HISTORY]
                ?? [
                    [
                        SyncHistoryEntityRecord::ACTION => Actions::ADD,
                        SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                        SyncHistoryEntityRecord::IS_SUCCESS => true,
                        SyncHistoryEntityRecord::MESSAGE => 'Success',
                    ],
                ],
                flags: JSON_THROW_ON_ERROR,
            ),
        );
        $record->setDate(
            date: $data[SyncHistoryEntityConsolidationRecord::DATE] ?? date(format: 'Y-m-d'),
        );

        $resourceModel = $this->instantiateResourceModel();
        $resourceModel->save(object: $record);

        return $record;
    }

    /**
     * @return SyncHistoryEntityConsolidationRecordInterface
     */
    private function instantiateModel(): SyncHistoryEntityConsolidationRecordInterface
    {
        // use create to prevent caching between tests
        // is more performant than @magentoAppIsolation
        return $this->objectManager->create(
            type: SyncHistoryEntityConsolidationRecordInterface::class,
        );
    }

    /**
     * @return ConsolidationResourceModel
     */
    private function instantiateResourceModel(): ConsolidationResourceModel
    {
        return $this->objectManager->get(
            type: ConsolidationResourceModel::class,
        );
    }
}
