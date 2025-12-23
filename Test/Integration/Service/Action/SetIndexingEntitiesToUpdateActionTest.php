<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Exception\IndexingEntitySaveException;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Action\SetIndexingEntitiesToUpdateAction;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntitySearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToUpdateActionInterface;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Service\Action\SetIndexingEntitiesToUpdateAction::class
 * @method SetIndexingEntitiesToUpdateActionInterface instantiateTestObject(?array $arguments = null)
 * @method SetIndexingEntitiesToUpdateActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SetIndexingEntitiesToUpdateActionTest extends TestCase
{
    use ObjectInstantiationTrait;
    use StoreTrait;
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

        $this->implementationFqcn = SetIndexingEntitiesToUpdateAction::class;
        $this->interfaceFqcn = SetIndexingEntitiesToUpdateActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith [1]
     *           [2500]
     *           [9999999]
     *
     * @param int $batchSize
     *
     * @return void
     */
    public function testConstruct_ValidBatchSize(int $batchSize): void
    {
        $this->instantiateTestObject([
            'batchSize' => $batchSize,
        ]);
        $this->addToAssertionCount(1);
    }

    /**
     * @testWith [0]
     *           [10000000]
     *
     * @param int $batchSize
     *
     * @return void
     */
    public function testConstruct_InvalidBatchSize(int $batchSize): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->instantiateTestObject([
            'batchSize' => $batchSize,
        ]);
    }

    /**
     * @testWith ["KLEVU_CATEGORY"]
     *           ["KLEVU_CMS"]
     *           ["KLEVU_PRODUCT"]
     */
    public function testExecute_SetsIndexingEntityNextActionUpdate_ForIndexableEntities(string $type): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::REQUIRES_UPDATE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::REQUIRES_UPDATE => false,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::DELETE,
            IndexingEntity::REQUIRES_UPDATE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::REQUIRES_UPDATE => false,
        ]);

        $indexingEntities = $this->getIndexingEntities($apiKey, $type);
        $this->assertCount(expectedCount: 4, haystack: $indexingEntities);
        $entityIds = $this->getEntityIds($indexingEntities);

        $action = $this->instantiateTestObject();
        $action->execute($entityIds);

        $indexingEntities = $this->getIndexingEntities($apiKey, $type);
        $this->assertCount(expectedCount: 4, haystack: $indexingEntities);

        $indexingEntityArray1 = $this->filterIndexEntities($indexingEntities, 1);
        $indexingEntity1 = array_shift($indexingEntityArray1);
        $this->assertTrue($indexingEntity1->getIsIndexable());
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingEntity1->getNextAction());
        $this->assertFalse(condition: $indexingEntity1->getRequiresUpdate());

        $indexingEntityArray2 = $this->filterIndexEntities($indexingEntities, 2);
        $indexingEntity2 = array_shift($indexingEntityArray2);
        $this->assertTrue($indexingEntity2->getIsIndexable());
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingEntity2->getNextAction());
        $this->assertFalse(condition: $indexingEntity2->getRequiresUpdate());

        $indexingEntityArray3 = $this->filterIndexEntities($indexingEntities, 3);
        $indexingEntity3 = array_shift($indexingEntityArray3);
        $this->assertFalse($indexingEntity3->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingEntity3->getNextAction());
        $this->assertFalse(condition: $indexingEntity3->getRequiresUpdate());

        $indexingEntityArray4 = $this->filterIndexEntities($indexingEntities, 4);
        $indexingEntity4 = array_shift($indexingEntityArray4);
        $this->assertTrue($indexingEntity4->getIsIndexable());
        $this->assertSame(expected: Actions::ADD, actual: $indexingEntity4->getNextAction());
        $this->assertFalse(condition: $indexingEntity4->getRequiresUpdate());
    }

    public function testExecute_LogsError_WhenSaveExceptionIsThrown(): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);

        $indexingEntity1 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 1234,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
        ]);
        $indexingEntity2 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 2345,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
        ]);

        $indexingEntities = $this->getIndexingEntities($apiKey, 'KLEVU_PRODUCT');
        $this->assertCount(expectedCount: 2, haystack: $indexingEntities);
        $entityIds = $this->getEntityIds($indexingEntities);

        $mockSearchResult = $this->getMockBuilder(IndexingEntitySearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([
                $indexingEntity1,
                $indexingEntity2,
            ]);

        $mockIndexingEntityRepository = $this->getMockBuilder(IndexingEntityRepositoryInterface::class)
            ->getMock();
        $mockIndexingEntityRepository->expects($this->once())
            ->method('getList')
            ->willReturn($mockSearchResult);
        $mockIndexingEntityRepository->expects($this->once())
            ->method('saveBatch')
            ->willThrowException(new \Exception('Exception thrown by repo'));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error');

        $this->expectException(IndexingEntitySaveException::class);
        $this->expectExceptionMessage('Indexing entities failed to save. See log for details.');

        $action = $this->instantiateTestObject([
            'indexingEntityRepository' => $mockIndexingEntityRepository,
            'logger' => $mockLogger,
        ]);
        $action->execute($entityIds);
    }

    /**
     * @group wip
     */
    public function testExecute_UtilisesBatching(): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);

        $indexingEntities = [];
        for ($i = 1; $i <= 3; $i++) {
            $indexingEntities[] = $this->createIndexingEntity([
                IndexingEntity::TARGET_ID => $i,
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::API_KEY => $apiKey,
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            ]);
        }

        $mockSearchResult = $this->getMockBuilder(IndexingEntitySearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn($indexingEntities);

        $mockIndexingEntityRepository = $this->getMockBuilder(IndexingEntityRepositoryInterface::class)
            ->getMock();
        $mockIndexingEntityRepository->expects($this->once())
            ->method('getList')
            ->willReturn($mockSearchResult);
        $mockIndexingEntityRepository->expects($this->exactly(3))
            ->method('addForBatchSave');
        $invocationRule = $this->exactly(4);
        $mockIndexingEntityRepository->expects($invocationRule)
            ->method('saveBatch')
            ->willReturnCallback(function (int $minimumBatchSize) use ($invocationRule): void {
                $invocationCount = match (true) {
                    method_exists($invocationRule, 'getInvocationCount') => $invocationRule->getInvocationCount(),
                    method_exists($invocationRule, 'numberOfInvocations') => $invocationRule->numberOfInvocations(),
                    default => throw new \RuntimeException('Cannot determine invocation count'),
                };

                switch ($invocationCount) {
                    case 1:
                    case 2:
                    case 3:
                        $this->assertSame(
                            expected: 2,
                            actual: $minimumBatchSize,
                        );
                        break;
                    case 4:
                        $this->assertSame(
                            expected: 1,
                            actual: $minimumBatchSize,
                        );
                        break;
                }
            });

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->never())
            ->method('error');

        $action = $this->instantiateTestObject([
            'indexingEntityRepository' => $mockIndexingEntityRepository,
            'logger' => $mockLogger,
            'batchSize' => 2,
        ]);
        $action->execute(
            entityIds: array_map(
                callback: static fn (IndexingEntityInterface $entity): int => $entity->getId(),
                array: $indexingEntities,
            ),
        );
    }

    /**
     * @param string $apiKey
     * @param string $type
     *
     * @return IndexingEntityInterface[]
     */
    private function getIndexingEntities(string $apiKey, string $type): array
    {
        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: IndexingEntity::TARGET_ENTITY_TYPE,
            value: $type,
        );
        $searchCriteriaBuilder->addFilter(
            field: IndexingEntity::API_KEY,
            value: $apiKey,
        );
        $searchCriteria = $searchCriteriaBuilder->create();
        $repository = $this->objectManager->create(IndexingEntityRepositoryInterface::class);
        $searchResult = $repository->getList($searchCriteria);

        return $searchResult->getItems();
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     *
     * @return int[]
     */
    private function getEntityIds(array $indexingEntities): array
    {
        return array_map(static fn (IndexingEntityInterface $indexingEntity): int => (
            (int)$indexingEntity->getId()
        ), $indexingEntities);
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param int $entityId
     *
     * @return IndexingEntityInterface[]
     */
    private function filterIndexEntities(array $indexingEntities, int $entityId): array
    {
        return array_filter(
            array: $indexingEntities,
            callback: static function (IndexingEntityInterface $indexingEntity) use ($entityId) {
                return $entityId === (int)$indexingEntity->getTargetId();
            },
        );
    }

    /**
     * @param mixed[] $data
     *
     * @return IndexingEntityInterface
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createIndexingEntity(array $data): IndexingEntityInterface
    {
        $repository = $this->objectManager->get(IndexingEntityRepositoryInterface::class);
        $indexingEntity = $repository->create();
        $indexingEntity->setTargetId((int)$data[IndexingEntity::TARGET_ID]);
        $indexingEntity->setTargetParentId($data[IndexingEntity::TARGET_PARENT_ID] ?? null);
        $indexingEntity->setTargetEntityType($data[IndexingEntity::TARGET_ENTITY_TYPE] ?? 'KLEVU_PRODUCT');
        $indexingEntity->setApiKey($data[IndexingEntity::API_KEY] ?? 'klevu-js-api-key');
        $indexingEntity->setNextAction($data[IndexingEntity::NEXT_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastAction($data[IndexingEntity::LAST_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastActionTimestamp($data[IndexingEntity::LAST_ACTION_TIMESTAMP] ?? null);
        $indexingEntity->setLockTimestamp($data[IndexingEntity::LOCK_TIMESTAMP] ?? null);
        $indexingEntity->setIsIndexable($data[IndexingEntity::IS_INDEXABLE] ?? true);

        return $repository->save($indexingEntity);
    }
}
