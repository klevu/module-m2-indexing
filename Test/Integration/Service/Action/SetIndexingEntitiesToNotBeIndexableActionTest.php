<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Exception\IndexingEntitySaveException;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Action\SetIndexingEntitiesToNotBeIndexableAction;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntitySearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToNotBeIndexableActionInterface;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\GeneratorTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers \Klevu\Indexing\Service\Action\SetIndexingEntitiesToNotBeIndexableAction::class
 * @method SetIndexingEntitiesToNotBeIndexableActionInterface instantiateTestObject(?array $arguments = null)
 * @method SetIndexingEntitiesToNotBeIndexableActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SetIndexingEntitiesToNotBeIndexableActionTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
    use GeneratorTrait;
    use IndexingEntitiesTrait;
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

        $this->implementationFqcn = SetIndexingEntitiesToNotBeIndexableAction::class;
        $this->interfaceFqcn = SetIndexingEntitiesToNotBeIndexableActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith ["KLEVU_CATEGORY"]
     *           ["KLEVU_CMS"]
     *           ["KLEVU_PRODUCT"]
     */
    public function testExecute_SetsIndexingEntityToBeIndexable_ForNoneIndexableEntities(string $type): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
        ]);

        $indexingEntities = $this->getIndexingEntities($apiKey, $type);
        $this->assertCount(expectedCount: 3, haystack: $indexingEntities);
        $entityIds = $this->getEntityIds($indexingEntities);

        $action = $this->instantiateTestObject();
        $action->execute($this->generate([$entityIds]));

        $indexingEntities = $this->getIndexingEntities($apiKey, $type);
        $this->assertCount(expectedCount: 3, haystack: $indexingEntities);

        $indexingEntityArray1 = $this->filterIndexEntities($indexingEntities, 1);
        $indexingEntity1 = array_shift($indexingEntityArray1);
        $this->assertFalse($indexingEntity1->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingEntity1->getNextAction());

        $indexingEntityArray2 = $this->filterIndexEntities($indexingEntities, 2);
        $indexingEntity2 = array_shift($indexingEntityArray2);
        $this->assertFalse($indexingEntity2->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingEntity2->getNextAction());

        $indexingEntityArray3 = $this->filterIndexEntities($indexingEntities, 3);
        $indexingEntity3 = array_shift($indexingEntityArray3);
        $this->assertFalse($indexingEntity3->getIsIndexable());
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingEntity3->getNextAction());

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    public function testExecute_LogsError_WhenSaveExceptionIsThrown(): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $indexingEntity1 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 1234,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCTS',
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
        ]);
        $indexingEntity2 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 2345,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCTS',
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $indexingEntities = $this->getIndexingEntities($apiKey, 'KLEVU_PRODUCTS');
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
        $mockIndexingEntityRepository->expects($this->exactly(2))
            ->method('save')
            ->willThrowException(new \Exception('Exception thrown by repo'));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->exactly(2))
            ->method('error');

        $this->expectException(IndexingEntitySaveException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Indexing entities (%s) failed to save. See log for details.',
                implode(', ', [$indexingEntity1->getId(), $indexingEntity2->getId()]),
            ),
        );

        $action = $this->instantiateTestObject([
            'indexingEntityRepository' => $mockIndexingEntityRepository,
            'logger' => $mockLogger,
        ]);
        $action->execute($this->generate([$entityIds]));

        $this->cleanIndexingEntities(apiKey: $apiKey);
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
}
