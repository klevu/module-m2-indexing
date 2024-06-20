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
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => 5,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 4,
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
        $targetIds = array_map(static fn (IndexingEntityInterface $indexingEntity): int => (
            $indexingEntity->getTargetId()
        ), $items);
        $this->assertContains(3, $targetIds);
        $this->assertContains(4, $targetIds);

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @param mixed[] $data
     *
     * @return IndexingEntityInterface
     * @throws AlreadyExistsException
     */
    private function createIndexingEntity(array $data = []): IndexingEntityInterface
    {
        // use objectManager::create to stop caching between tests
        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = $this->objectManager->create(type: IndexingEntityInterface::class);
        $indexingEntity->setTargetEntityType(entityType: $data['target_entity_type'] ?? 'KLEVU_PRODUCT');
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

        $resourceModel = $this->objectManager->get(type: IndexingEntityResourceModel::class);
        $resourceModel->save(object: $indexingEntity);

        return $indexingEntity;
    }
}
