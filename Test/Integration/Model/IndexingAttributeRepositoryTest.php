<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Exception\CouldNotDeleteException;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Model\IndexingAttributeRepository;
use Klevu\Indexing\Model\ResourceModel\IndexingAttribute as IndexingAttributeResourceModel;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
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
 * @covers \Klevu\Indexing\Model\IndexingAttributeRepository::class
 * @method IndexingAttributeRepositoryInterface instantiateTestObject(?array $arguments = null)
 * @method IndexingAttributeRepositoryInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingAttributeRepositoryTest extends TestCase
{
    use IndexingAttributesTrait;
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
        $this->implementationFqcn = IndexingAttributeRepository::class;
        $this->interfaceFqcn = IndexingAttributeRepositoryInterface::class;

        $this->cleanIndexingAttributes('klevu-js-api-key%');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanIndexingAttributes('klevu-js-api-key%');

    }

    public function testCreate_ReturnsIndexingEntityModel(): void
    {
        $repository = $this->instantiateTestObject();
        $indexingEntity = $repository->create();

        $this->assertInstanceOf(
            expected: IndexingAttributeInterface::class,
            actual: $indexingEntity,
        );
    }

    public function testGetById_NotExists(): void
    {
        $indexingAttributeId = 999999999;

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(
            sprintf('No such entity with entity_id = %s', $indexingAttributeId),
        );

        $repository = $this->instantiateTestObject();
        $repository->getById($indexingAttributeId);
    }

    public function testGetById_Exists(): void
    {
        $indexingAttribute = $this->createIndexingAttribute();

        $repository = $this->instantiateTestObject();
        $loadedIndexingAttribute = $repository->getById((int)$indexingAttribute->getId());

        $this->assertSame(
            expected: (int)$indexingAttribute->getId(),
            actual: $loadedIndexingAttribute->getId(),
            message: "getId",
        );
        $this->assertSame(
            expected: (int)$indexingAttribute->getId(),
            actual: $loadedIndexingAttribute->getData(IndexingAttribute::ENTITY_ID),
            message: "getData('entity_id')",
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $loadedIndexingAttribute->getTargetAttributeType(),
            message: "getTargetEntityType",
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $loadedIndexingAttribute->getData(IndexingAttribute::TARGET_ATTRIBUTE_TYPE),
            message: "getData('target_entity_type')",
        );
        $this->assertSame(
            expected: 1,
            actual: $loadedIndexingAttribute->getTargetId(),
            message: "getTargetId",
        );
        $this->assertSame(
            expected: 1,
            actual: $loadedIndexingAttribute->getData(IndexingAttribute::TARGET_ID),
            message: "getData('target_id')",
        );
        $this->assertSame(
            expected: 'klevu_test_attribute',
            actual: $loadedIndexingAttribute->getTargetCode(),
            message: "getTargetCode",
        );
        $this->assertSame(
            expected: 'klevu_test_attribute',
            actual: $loadedIndexingAttribute->getData(IndexingAttribute::TARGET_CODE),
            message: "getData('target_code')",
        );
        $this->assertSame(
            expected: 'klevu-js-api-key',
            actual: $loadedIndexingAttribute->getApiKey(),
            message: "getApiKey",
        );
        $this->assertSame(
            expected: 'klevu-js-api-key',
            actual: $loadedIndexingAttribute->getData(IndexingAttribute::API_KEY),
            message: "getData('api_key')",
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $loadedIndexingAttribute->getNextAction(),
            message: "getNextAction",
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $loadedIndexingAttribute->getData(IndexingAttribute::NEXT_ACTION),
            message: "getData('next_action')",
        );
        $this->assertNull(
            actual: $loadedIndexingAttribute->getLockTimestamp(),
            message: "getLockTimestamp",
        );
        $this->assertNull(
            actual: $loadedIndexingAttribute->getData(IndexingAttribute::LOCK_TIMESTAMP),
            message: "getData('lock_timestamp')",
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $loadedIndexingAttribute->getLastAction(),
            message: "getLastAction",
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $loadedIndexingAttribute->getData(IndexingAttribute::LAST_ACTION),
            message: "getData('last_action')",
        );
        $this->assertNull(
            actual: $loadedIndexingAttribute->getLastActionTimestamp(),
            message: "getLastActionTimestamp",
        );
        $this->assertNull(
            actual: $loadedIndexingAttribute->getData(IndexingAttribute::LAST_ACTION_TIMESTAMP),
            message: "getData('last_action_timestamp')",
        );
        $this->assertTrue(
            condition: $loadedIndexingAttribute->getIsIndexable(),
            message: "getIsIndexable",
        );
        $this->assertTrue(
            condition: $loadedIndexingAttribute->getData(IndexingAttribute::IS_INDEXABLE),
            message: "getData('is_indexable')",
        );
    }

    public function testSave_Create_Empty(): void
    {
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessageMatches('#Could not save Indexing Attribute: .*#');

        $repository = $this->instantiateTestObject();
        $indexingAttribute = $repository->create();
        $repository->save($indexingAttribute);
    }

    /**
     * @magentoDbIsolation enabled
     */
    public function testSave_Create(): void
    {
        $repository = $this->instantiateTestObject();
        $indexingAttribute = $repository->create();
        $indexingAttribute->setTargetId(123);
        $indexingAttribute->setTargetCode('test_attribute');
        $indexingAttribute->setTargetAttributeType('KLEVU_PRODUCT');
        $indexingAttribute->setApiKey('klevu-js-api-key-test-1234');
        $indexingAttribute->setLastAction(Actions::NO_ACTION);
        $indexingAttribute->setLastActionTimestamp(null);
        $indexingAttribute->setNextAction(Actions::ADD);
        $indexingAttribute->setLockTimestamp(null);
        $indexingAttribute->setIsIndexable(true);
        $savedIndexingEntity = $repository->save($indexingAttribute);

        $this->assertNotNull($savedIndexingEntity->getId());
    }

    public function testSave_Update(): void
    {
        $repository = $this->instantiateTestObject();
        $indexingAttribute = $repository->create();
        $indexingAttribute->setTargetId(1);
        $indexingAttribute->setTargetCode('test_attribute');
        $indexingAttribute->setTargetAttributeType('KLEVU_PRODUCT');
        $indexingAttribute->setApiKey('klevu-js-api-key-test-1234');
        $indexingAttribute->setLastAction(Actions::NO_ACTION);
        $indexingAttribute->setLastActionTimestamp(null);
        $indexingAttribute->setNextAction(Actions::ADD);
        $indexingAttribute->setLockTimestamp(null);
        $indexingAttribute->setIsIndexable(true);
        $savedIndexingAttribute = $repository->save($indexingAttribute);

        $lastActionTime = date('Y-m-d H:i:s');
        $savedIndexingAttribute->setLastAction(Actions::ADD);
        $savedIndexingAttribute->setLastActionTimestamp($lastActionTime);
        $savedIndexingAttribute->setNextAction(Actions::UPDATE);
        $updatedIndexingAttribute = $repository->save($savedIndexingAttribute);

        $this->assertSame(
            expected: Actions::ADD,
            actual: $updatedIndexingAttribute->getLastAction(),
        );
        $this->assertSame(
            expected: $lastActionTime,
            actual: $updatedIndexingAttribute->getLastActionTimestamp(),
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $updatedIndexingAttribute->getNextAction(),
        );
    }

    public function testSave_Update_InvalidData(): void
    {
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessageMatches('#Could not save Indexing Attribute: .*#');

        $repository = $this->instantiateTestObject();
        $indexingAttribute = $repository->create();
        $indexingAttribute->setTargetId(1);
        $indexingAttribute->setTargetCode('test_attribute');
        $indexingAttribute->setTargetAttributeType('KLEVU_PRODUCT');
        $indexingAttribute->setApiKey('klevu-js-api-key-test-1234');
        $indexingAttribute->setLastAction(Actions::NO_ACTION);
        $indexingAttribute->setLastActionTimestamp(null);
        $indexingAttribute->setNextAction(Actions::ADD);
        $indexingAttribute->setLockTimestamp(null);
        $indexingAttribute->setIsIndexable(true);
        $savedIndexingAttribute = $repository->save($indexingAttribute);

        $savedIndexingAttribute->setData('target_id', 'not an integer'); // @phpstan-ignore-line
        $repository->save($savedIndexingAttribute);
    }

    public function testSave_HandlesAlreadyExistsException(): void
    {
        $indexingEntity = $this->createIndexingAttribute();

        $mockMessage = 'Attribute Already Exists';
        $this->expectException(AlreadyExistsException::class);
        $this->expectExceptionMessage($mockMessage);

        $exception = new AlreadyExistsException(__($mockMessage));
        $mockResourceModel = $this->getMockBuilder(IndexingAttributeResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('save')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'indexingAttributeResourceModel' => $mockResourceModel,
        ]);
        $repository->save($indexingEntity);
    }

    public function testSave_HandlesException(): void
    {
        $indexingEntity = $this->createIndexingAttribute();

        $mockMessage = 'Some core exception message.';
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage(sprintf('Could not save Indexing Attribute: %s', $mockMessage));

        $exception = new \Exception($mockMessage);
        $mockResourceModel = $this->getMockBuilder(IndexingAttributeResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('save')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'indexingAttributeResourceModel' => $mockResourceModel,
        ]);
        $repository->save($indexingEntity);
    }

    public function testDelete_RemovesIndexingAttribute(): void
    {
        $repository = $this->instantiateTestObject();
        $indexingAttribute = $this->createIndexingAttribute();
        $indexingAttributeId = $indexingAttribute->getId();
        $this->assertNotNull($indexingAttributeId);
        $repository->delete($indexingAttribute);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(sprintf('No such entity with entity_id = %s', $indexingAttributeId));
        $repository->getById((int)$indexingAttributeId);
    }

    public function testDelete_HandlesLocalizedException(): void
    {
        $indexingEntity = $this->createIndexingAttribute();

        $mockMessage = 'A localized exception message';
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($mockMessage);

        $exception = new LocalizedException(__($mockMessage));
        $mockResourceModel = $this->getMockBuilder(IndexingAttributeResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockResourceModel->expects($this->once())
            ->method('delete')
            ->willThrowException($exception);

        $repository = $this->instantiateTestObject([
            'indexingAttributeResourceModel' => $mockResourceModel,
        ]);
        $repository->delete($indexingEntity);
    }

    public function testDelete_HandlesException(): void
    {
        $indexingEntity = $this->createIndexingAttribute();

        $mockMessage = 'Some core exception message.';
        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage(sprintf('Could not delete Indexing Attribute: %s', $mockMessage));

        $exception = new \Exception($mockMessage);
        $mockResourceModel = $this->getMockBuilder(IndexingAttributeResourceModel::class)
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
                sprintf('Could not delete Indexing Attribute: %s', $mockMessage),
                [
                    'exception' => \Exception::class,
                    'method' => 'Klevu\Indexing\Model\IndexingAttributeRepository::delete',
                    'indexingEntity' => [
                        'entityId' => $indexingEntity->getId(),
                        'targetId' => $indexingEntity->getTargetId(),
                        'targetAttributeType' => $indexingEntity->getTargetAttributeType(),
                        'apiKey' => $indexingEntity->getApiKey(),
                    ],
                ],
            );

        $repository = $this->instantiateTestObject([
            'indexingAttributeResourceModel' => $mockResourceModel,
            'logger' => $mockLogger,
        ]);
        $repository->delete($indexingEntity);
    }

    public function testDeleteById_NotExists(): void
    {
        $attributeId = -1;
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(sprintf('No such entity with entity_id = %s', $attributeId));

        $repository = $this->instantiateTestObject();
        $repository->deleteById($attributeId);
    }

    public function testDeleteById_Exists(): void
    {
        $repository = $this->instantiateTestObject();
        $indexingAttribute = $this->createIndexingAttribute();
        $indexingAttributeId = $indexingAttribute->getId();
        try {
            $repository->getById((int)$indexingAttributeId);
        } catch (\Exception $exception) {
            $this->fail('Failed to create Indexing Attribute for test: ' . $exception->getMessage());
        }
        $repository->deleteById((int)$indexingAttributeId);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage(sprintf('No such entity with entity_id = %s', $indexingAttributeId));
        $repository->getById((int)$indexingAttributeId);
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
        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'test_attribute_2',
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CMS',
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'test_attribute_1',
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'test_attribute_1',
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'test_attribute_2',
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::TARGET_CODE => 'test_attribute_3',
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::TARGET_CODE => 'test_attribute_4',
            IndexingAttribute::API_KEY => $apiKey,
        ]);

        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();

        $sortOrderBuilder = $this->objectManager->get(SortOrderBuilder::class);
        $sortOrderBuilder->setField(IndexingAttribute::TARGET_ID);
        $sortOrderBuilder->setAscendingDirection();
        $sortOrder = $sortOrderBuilder->create();
        $searchCriteriaBuilder->setSortOrders([$sortOrder]);

        $searchCriteriaBuilder->addFilter(
            field: IndexingAttribute::TARGET_ATTRIBUTE_TYPE,
            value: 'KLEVU_PRODUCT',
        );
        $searchCriteriaBuilder->addFilter(
            field: IndexingAttribute::API_KEY,
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
        $targetIds = array_map(static fn (IndexingAttributeInterface $indexingAttribute): int => (
        $indexingAttribute->getTargetId()
        ), $items);
        $this->assertContains(3, $targetIds);
        $this->assertContains(4, $targetIds);

        $this->cleanIndexingAttributes($apiKey);
    }
}
