<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Exception\CouldNotDeleteException;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\IndexingEntityRepository;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity as IndexingEntityResourceModel;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
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
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Model\IndexingEntityRepository::class
 * @method IndexingEntityRepositoryInterface instantiateTestObject(?array $arguments = null)
 * @method IndexingEntityRepositoryInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingEntityRepositoryTest extends TestCase
{
    use IndexingEntitiesTrait;
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
        $this->implementationFqcn = IndexingEntityRepository::class;
        $this->interfaceFqcn = IndexingEntityRepositoryInterface::class;

        $this->cleanIndexingEntities('klevu-js-api-key%');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanIndexingEntities('klevu-js-api-key%');

    }

    public function testCreate_ReturnsIndexingEntityModel(): void
    {
        $repository = $this->instantiateTestObject();
        $indexingEntity = $repository->create();

        $this->assertInstanceOf(
            expected: IndexingEntityInterface::class,
            actual: $indexingEntity,
        );
    }

    public function testGetById_NotExists(): void
    {
        $indexingEntityId = 999999999;

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(
            sprintf('No such entity with entity_id = %s', $indexingEntityId),
        );

        $repository = $this->instantiateTestObject();
        $repository->getById($indexingEntityId);
    }

    public function testGetById_Exists(): void
    {
        $indexingEntity = $this->createIndexingEntity([
            'target_parent_id' => 123,
        ]);

        $repository = $this->instantiateTestObject();
        $loadedIndexingEntity = $repository->getById((int)$indexingEntity->getId());

        $this->assertSame(
            expected: (int)$indexingEntity->getId(),
            actual: $loadedIndexingEntity->getId(),
            message: "getId",
        );
        $this->assertSame(
            expected: (int)$indexingEntity->getId(),
            actual: $loadedIndexingEntity->getData(IndexingEntity::ENTITY_ID),
            message: "getData('entity_id')",
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $loadedIndexingEntity->getTargetEntityType(),
            message: "getTargetEntityType",
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $loadedIndexingEntity->getData(IndexingEntity::TARGET_ENTITY_TYPE),
            message: "getData('target_entity_type')",
        );
        $this->assertNull(
            actual: $loadedIndexingEntity->getTargetEntitySubtype(),
            message: "getTargetEntityType",
        );
        $this->assertNull(
            actual: $loadedIndexingEntity->getData(IndexingEntity::TARGET_ENTITY_SUBTYPE),
            message: "getData('target_entity_subtype')",
        );
        $this->assertSame(
            expected: 1,
            actual: $loadedIndexingEntity->getTargetId(),
            message: "getTargetId",
        );
        $this->assertSame(
            expected: 1,
            actual: $loadedIndexingEntity->getData(IndexingEntity::TARGET_ID),
            message: "getData('target_id')",
        );
        $this->assertSame(
            expected: 123,
            actual: $loadedIndexingEntity->getTargetParentId(),
            message: "getTargetParentId",
        );
        $this->assertSame(
            expected: 123,
            actual: $loadedIndexingEntity->getData(IndexingEntity::TARGET_PARENT_ID),
            message: "getData('target_parent_id')",
        );
        $this->assertStringContainsString(
            needle: 'klevu-js-api-key-',
            haystack: $loadedIndexingEntity->getApiKey(),
            message: "getApiKey",
        );
        $this->assertStringContainsString(
            needle: 'klevu-js-api-key-',
            haystack: $loadedIndexingEntity->getData(IndexingEntity::API_KEY),
            message: "getData('api_key')",
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $loadedIndexingEntity->getNextAction(),
            message: "getNextAction",
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $loadedIndexingEntity->getData(IndexingEntity::NEXT_ACTION),
            message: "getData('next_action')",
        );
        $this->assertNull(
            actual: $loadedIndexingEntity->getLockTimestamp(),
            message: "getLockTimestamp",
        );
        $this->assertNull(
            actual: $loadedIndexingEntity->getData(IndexingEntity::LOCK_TIMESTAMP),
            message: "getData('lock_timestamp')",
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $loadedIndexingEntity->getLastAction(),
            message: "getLastAction",
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $loadedIndexingEntity->getData(IndexingEntity::LAST_ACTION),
            message: "getData('last_action')",
        );
        $this->assertNull(
            actual: $loadedIndexingEntity->getLastActionTimestamp(),
            message: "getLastActionTimestamp",
        );
        $this->assertNull(
            actual: $loadedIndexingEntity->getData(IndexingEntity::LAST_ACTION_TIMESTAMP),
            message: "getData('last_action_timestamp')",
        );
        $this->assertTrue(
            condition: $loadedIndexingEntity->getIsIndexable(),
            message: "getIsIndexable",
        );
        $this->assertTrue(
            condition: $loadedIndexingEntity->getData(IndexingEntity::IS_INDEXABLE),
            message: "getData('is_indexable')",
        );
    }

    public function testSave_Create_Empty(): void
    {
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessageMatches('#Could not save Indexing Entity: .*#');

        $repository = $this->instantiateTestObject();
        $indexingEntity = $repository->create();
        $repository->save($indexingEntity);
    }

    public function testSave_Create(): void
    {
        $repository = $this->instantiateTestObject();
        $indexingEntity = $repository->create();
        $indexingEntity->setTargetId(1);
        $indexingEntity->setTargetParentId(123);
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetEntitySubtype('simple');
        $indexingEntity->setApiKey('klevu-js-api-key-test-1234');
        $indexingEntity->setLastAction(Actions::NO_ACTION);
        $indexingEntity->setLastActionTimestamp(null);
        $indexingEntity->setNextAction(Actions::ADD);
        $indexingEntity->setLockTimestamp(null);
        $indexingEntity->setIsIndexable(true);
        $savedIndexingEntity = $repository->save($indexingEntity);

        $this->assertNotNull($savedIndexingEntity->getId());
    }

    public function testSave_Update(): void
    {
        $repository = $this->instantiateTestObject();
        $indexingEntity = $repository->create();
        $indexingEntity->setTargetId(1);
        $indexingEntity->setTargetParentId(100);
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetEntitySubtype('downloadable');
        $indexingEntity->setApiKey('klevu-js-api-key-test-1234');
        $indexingEntity->setLastAction(Actions::NO_ACTION);
        $indexingEntity->setLastActionTimestamp(null);
        $indexingEntity->setNextAction(Actions::ADD);
        $indexingEntity->setLockTimestamp(null);
        $indexingEntity->setIsIndexable(true);
        $savedIndexingEntity = $repository->save($indexingEntity);

        $lastActionTime = date('Y-m-d H:i:s');
        $savedIndexingEntity->setLastAction(Actions::ADD);
        $savedIndexingEntity->setLastActionTimestamp($lastActionTime);
        $savedIndexingEntity->setNextAction(Actions::UPDATE);
        $updatedIndexingEntity = $repository->save($savedIndexingEntity);

        $this->assertSame(
            expected: 100,
            actual: $updatedIndexingEntity->getTargetParentId(),
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $updatedIndexingEntity->getLastAction(),
        );
        $this->assertSame(
            expected: $lastActionTime,
            actual: $updatedIndexingEntity->getLastActionTimestamp(),
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $updatedIndexingEntity->getNextAction(),
        );
        $this->assertSame(
            expected: 'downloadable',
            actual: $updatedIndexingEntity->getTargetEntitySubtype(),
        );
    }

    public function testSave_Update_InvalidData(): void
    {
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessageMatches('#Could not save Indexing Entity: .*#');

        $repository = $this->instantiateTestObject();
        $indexingEntity = $repository->create();
        $indexingEntity->setTargetId(1);
        $indexingEntity->setTargetParentId(2);
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setApiKey('klevu-js-api-key-test-1234');
        $indexingEntity->setLastAction(Actions::NO_ACTION);
        $indexingEntity->setLastActionTimestamp(null);
        $indexingEntity->setNextAction(Actions::ADD);
        $indexingEntity->setLockTimestamp(null);
        $indexingEntity->setIsIndexable(true);
        $savedIndexingEntity = $repository->save($indexingEntity);

        $savedIndexingEntity->setData('target_id', 'not an integer'); // @phpstan-ignore-line
        $repository->save($savedIndexingEntity);
    }

    public function testSave_HandlesAlreadyExistsException(): void
    {
        $indexingEntity = $this->createIndexingEntity();

        $mockMessage = 'Entity Already Exists';
        $this->expectException(AlreadyExistsException::class);
        $this->expectExceptionMessage($mockMessage);

        $exception = new AlreadyExistsException(__($mockMessage));
        $mockResourceModel = $this->getMockBuilder(IndexingEntityResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('save')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'indexingEntityResourceModel' => $mockResourceModel,
        ]);
        $repository->save($indexingEntity);
    }

    public function testSave_HandlesException(): void
    {
        $indexingEntity = $this->createIndexingEntity();

        $mockMessage = 'Some core exception message.';
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage(sprintf('Could not save Indexing Entity: %s', $mockMessage));

        $exception = new \Exception($mockMessage);
        $mockResourceModel = $this->getMockBuilder(IndexingEntityResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('save')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'indexingEntityResourceModel' => $mockResourceModel,
        ]);
        $repository->save($indexingEntity);
    }

    public function testDelete_RemovesIndexingEntity(): void
    {
        $repository = $this->instantiateTestObject();
        $indexingEntity = $this->createIndexingEntity();
        $entityId = $indexingEntity->getId();
        $this->assertNotNull($entityId);
        $repository->delete($indexingEntity);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(sprintf('No such entity with entity_id = %s', $entityId));
        $repository->getById((int)$entityId);
    }

    public function testDelete_HandlesLocalizedException(): void
    {
        $indexingEntity = $this->createIndexingEntity();

        $mockMessage = 'A localized exception message';
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($mockMessage);

        $exception = new LocalizedException(__($mockMessage));
        $mockResourceModel = $this->getMockBuilder(IndexingEntityResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('delete')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'indexingEntityResourceModel' => $mockResourceModel,
        ]);
        $repository->delete($indexingEntity);
    }

    public function testDelete_HandlesException(): void
    {
        $indexingEntity = $this->createIndexingEntity();

        $mockMessage = 'Some core exception message.';
        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage(sprintf('Could not delete Indexing Entity: %s', $mockMessage));

        $exception = new \Exception($mockMessage);
        $mockResourceModel = $this->getMockBuilder(IndexingEntityResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('delete')
            ->willThrowException($exception);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                sprintf('Could not delete Indexing Entity: %s', $mockMessage),
                [
                    'exception' => \Exception::class,
                    'method' => 'Klevu\Indexing\Model\IndexingEntityRepository::delete',
                    'indexingEntity' => [
                        'entityId' => $indexingEntity->getId(),
                        'targetId' => $indexingEntity->getTargetId(),
                        'targetParentId' => $indexingEntity->getTargetParentId(),
                        'targetEntityType' => $indexingEntity->getTargetEntityType(),
                        'targetEntitySubType' => $indexingEntity->getTargetEntitySubtype(),
                        'apiKey' => $indexingEntity->getApiKey(),
                    ],
                ],
            );

        $repository = $this->instantiateTestObject([
            'indexingEntityResourceModel' => $mockResourceModel,
            'logger' => $mockLogger,
        ]);
        $repository->delete($indexingEntity);
    }

    public function testDeleteById_NotExists(): void
    {
        $entityId = -1;
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(sprintf('No such entity with entity_id = %s', $entityId));

        $repository = $this->instantiateTestObject();
        $repository->deleteById($entityId);
    }

    public function testDeleteById_Exists(): void
    {
        $repository = $this->instantiateTestObject();
        $indexingEntity = $this->createIndexingEntity();
        $entityId = $indexingEntity->getId();
        try {
            $repository->getById((int)$entityId);
        } catch (\Exception $exception) {
            $this->fail('Failed to create Indexing Entity for test: ' . $exception->getMessage());
        }

        $repository->deleteById((int)$entityId);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(sprintf('No such entity with entity_id = %s', $entityId));
        $repository->getById((int)$entityId);
    }

    public function testGetList_NoResults(): void
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

    public function testGetList_Results(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            'target_id' => 2,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => 5,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 5,
            IndexingEntity::API_KEY => $apiKey,
        ]);

        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();

        $sortOrderBuilder = $this->objectManager->get(SortOrderBuilder::class);
        $sortOrderBuilder->setField('target_id');
        $sortOrderBuilder->setAscendingDirection();
        $sortOrder = $sortOrderBuilder->create();
        $searchCriteriaBuilder->setSortOrders([$sortOrder]);

        $searchCriteriaBuilder->addFilter(
            field: IndexingEntity::TARGET_ENTITY_TYPE,
            value: 'KLEVU_PRODUCT',
        );
        $searchCriteriaBuilder->addFilter(
            field: IndexingEntity::API_KEY,
            value: $apiKey,
        );
        $searchCriteriaBuilder->addFilter(
            field: IndexingEntity::TARGET_ENTITY_SUBTYPE,
            value: 'simple',
        );
        $searchCriteriaBuilder->setPageSize(2);
        $searchCriteriaBuilder->setCurrentPage(2);
        $searchCriteria = $searchCriteriaBuilder->create();

        $repository = $this->instantiateTestObject();
        $searchResult = $repository->getList($searchCriteria, true);

        $this->assertSame($searchCriteria, $searchResult->getSearchCriteria());
        // total number of items available
        $this->assertEquals(4, $searchResult->getTotalCount());
        $items = $searchResult->getItems();
        // paginated number of items on this page
        $this->assertCount(expectedCount: 2, haystack: $items);
        // get target ids and ensure we are on page 2
        $targetIds = array_map(static fn (IndexingEntityInterface $indexingEntity): int => (
            $indexingEntity->getTargetId()
        ), $items);
        $this->assertContains(3, $targetIds);
        $this->assertContains(5, $targetIds);

        $searchResult = $repository->getList($searchCriteria, false);
        $this->assertSame($searchCriteria, $searchResult->getSearchCriteria());
        // number of items in results
        $this->assertEquals(2, $searchResult->getTotalCount());

        $this->cleanIndexingEntities($apiKey);
    }

    public function testGetUniqueEntityTypes_ReturnsEmptyArray_WhenTableIsEmpty(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $repository = $this->instantiateTestObject();
        $result = $repository->getUniqueEntityTypes(apiKey: $apiKey);

        $this->assertCount(0, $result);
    }

    public function testGetUniqueEntityTypes_ReturnsArrayOfTypesForApiKey(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->cleanIndexingEntities(apiKey: $apiKey . '2');

        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'CUSTOM_TYPE',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => false,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => false,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey . '2',
            IndexingEntity::TARGET_ENTITY_TYPE => 'OTHER_CUSTOM_TYPE',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $repository = $this->instantiateTestObject();

        $result = $repository->getUniqueEntityTypes(apiKey: $apiKey);
        $this->assertContains(needle: 'KLEVU_CATEGORY', haystack: $result);
        $this->assertContains(needle: 'KLEVU_CMS', haystack: $result);
        $this->assertContains(needle: 'KLEVU_PRODUCT', haystack: $result);
        $this->assertContains(needle: 'CUSTOM_TYPE', haystack: $result);
        $this->assertNotContains(needle: 'OTHER_CUSTOM_TYPE', haystack: $result);

        $result = $repository->getUniqueEntityTypes(apiKey: $apiKey . '2');
        $this->assertNotContains(needle: 'KLEVU_CATEGORY', haystack: $result);
        $this->assertNotContains(needle: 'KLEVU_CMS', haystack: $result);
        $this->assertNotContains(needle: 'KLEVU_PRODUCT', haystack: $result);
        $this->assertNotContains(needle: 'CUSTOM_TYPE', haystack: $result);
        $this->assertContains(needle: 'OTHER_CUSTOM_TYPE', haystack: $result);
    }

    public function testSaveBatch_RespectsMinimumBatchSize(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $repository = $this->instantiateTestObject();

        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: 'api_key',
            value: $apiKey,
            conditionType: 'eq',
        );
        $searchCriteria = $searchCriteriaBuilder->create();

        $existingEntities = $repository->getList($searchCriteria);
        $this->assertSame(
            expected: 0,
            actual: $existingEntities->getTotalCount(),
            message: 'Count before saveBatch',
        );

        for ($i = 1; $i <= 3; $i++) {
            $repository->addForBatchSave(
                indexingEntity: $this->createIndexingEntity(
                    data: [
                        IndexingEntity::API_KEY => $apiKey,
                        IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                        IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                        IndexingEntity::TARGET_ID => $i,
                        IndexingEntity::NEXT_ACTION => Actions::ADD,
                        IndexingEntity::IS_INDEXABLE => true,
                    ],
                    save: false,
                ),
            );
        }

        $repository->saveBatch(
            minimumBatchSize: 5,
        );

        $existingEntitiesAfterSave = $repository->getList($searchCriteria);
        $this->assertSame(
            expected: 0,
            actual: $existingEntitiesAfterSave->getTotalCount(),
            message: 'Count after first saveBatch with minimumBatchSize 5',
        );

        $repository->saveBatch(
            minimumBatchSize: 1,
        );

        $existingEntitiesAfterSecondSave = $repository->getList($searchCriteria);
        $this->assertSame(
            expected: 3,
            actual: $existingEntitiesAfterSecondSave->getTotalCount(),
            message: 'Count after second saveBatch with minimumBatchSize 1',
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    public function testSaveBatch_ThrowsValidationException(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $repository = $this->instantiateTestObject();

        // use objectManager::create to stop caching between tests
        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = $this->objectManager->create(type: IndexingEntityInterface::class);
        $indexingEntity->setData([
            IndexingEntity::API_KEY => 42,
            IndexingEntity::TARGET_ENTITY_TYPE => 3.14,
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => '1',
            IndexingEntity::NEXT_ACTION => 'no-such-action',
            IndexingEntity::IS_INDEXABLE => 1,
        ]);
        $repository->addForBatchSave($indexingEntity);

        $indexingEntity = $this->objectManager->create(type: IndexingEntityInterface::class);
        $indexingEntity->setData([
            IndexingEntity::API_KEY => 42,
            IndexingEntity::TARGET_ENTITY_TYPE => 3.14,
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => '1',
            IndexingEntity::NEXT_ACTION => 'no-such-action',
            IndexingEntity::IS_INDEXABLE => 1,
        ]);
        $repository->addForBatchSave($indexingEntity);

        $indexingEntity = $this->objectManager->create(type: IndexingEntityInterface::class);
        $indexingEntity->setData([
            IndexingEntity::ENTITY_ID => 999999,
            IndexingEntity::API_KEY => str_repeat(string: 'A', times: 32),
            IndexingEntity::TARGET_ENTITY_TYPE => str_repeat(string: 'B', times: 64),
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $repository->addForBatchSave($indexingEntity);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessageMatches(
            regularExpression: '/Could not bulk save Indexing Entities: \(new\): (.*);; #999999: (.*)/',
        );
        $repository->saveBatch(
            minimumBatchSize: 1,
        );
    }

    public function testSaveBatch_ThrowsCouldNotSaveException(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $mockIndexingEntityResource = $this->getMockIndexingEntityResource();
        $mockIndexingEntityResource->method('saveMultiple')
            ->willThrowException(
                exception: new LocalizedException(__('Test Exception Message')),
            );

        $repository = $this->instantiateTestObject([
            'indexingEntityResourceModel' => $mockIndexingEntityResource,
        ]);

        $indexingEntity = $this->createIndexingEntity(
            data: [
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::TARGET_ID => 1,
                IndexingEntity::NEXT_ACTION => Actions::ADD,
                IndexingEntity::IS_INDEXABLE => true,
            ],
            save: false,
        );
        $repository->addForBatchSave($indexingEntity);

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not bulk save Indexing Entities: Test Exception Message');
        $repository->saveBatch(
            minimumBatchSize: 1,
        );
    }

    /**
     * @param mixed[] $data
     * @param bool $save
     *
     * @return IndexingEntityInterface
     * @throws AlreadyExistsException
     */
    private function createIndexingEntity(array $data = [], bool $save = true): IndexingEntityInterface
    {
        // use objectManager::create to stop caching between tests
        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = $this->objectManager->create(type: IndexingEntityInterface::class);
        $indexingEntity->setTargetEntityType(entityType: $data['target_entity_type'] ?? 'KLEVU_PRODUCT');
        $indexingEntity->setTargetEntitySubtype(entitySubtype: $data['target_entity_subtype'] ?? null);
        $indexingEntity->setTargetId(targetId: $data['target_id'] ?? 1);
        $indexingEntity->setTargetParentId(targetParentId: $data['target_parent_id'] ?? null);
        $indexingEntity->setApiKey(
            apiKey: $data['api_key'] ?? 'klevu-js-api-key-' . random_int(min: 0, max: 999999999),
        );
        $indexingEntity->setNextAction(nextAction: $data['next_action'] ?? Actions::UPDATE);
        $indexingEntity->setLockTimestamp(lockTimestamp: $data['lock_timestamp'] ?? null);
        $indexingEntity->setLastAction(lastAction: $data['last_action'] ?? Actions::ADD);
        $indexingEntity->setLastActionTimestamp(lastActionTimestamp: $data['last_action_timestamp'] ?? null);
        $indexingEntity->setIsIndexable(isIndexable: $data['is_indexable'] ?? true);

        if ($save) {
            $resourceModel = $this->objectManager->get(type: IndexingEntityResourceModel::class);
            $resourceModel->save(object: $indexingEntity);
        }

        return $indexingEntity;
    }

    /**
     * @return MockObject|IndexingEntityResourceModel
     */
    private function getMockIndexingEntityResource(): MockObject
    {
        return $this->getMockBuilder(IndexingEntityResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
