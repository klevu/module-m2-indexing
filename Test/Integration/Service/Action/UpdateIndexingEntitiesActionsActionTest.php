<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Action\UpdateIndexingEntitiesActionsAction;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntitySearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\UpdateIndexingEntitiesActionsActionInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers UpdateIndexingEntitiesActionsAction
 * @method UpdateIndexingEntitiesActionsActionInterface instantiateTestObject(?array $arguments = null)
 * @method UpdateIndexingEntitiesActionsActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class UpdateIndexingEntitiesActionsActionTest extends TestCase
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

        $this->implementationFqcn = UpdateIndexingEntitiesActionsAction::class;
        $this->interfaceFqcn = UpdateIndexingEntitiesActionsActionInterface::class;
        $this->constructorArgumentDefaults = [
            'lastAction' => '',
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_UpdatesMultipleEntityActions(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities($apiKey);

        $productIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $cmsIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 234,
            IndexingEntity::TARGET_PARENT_ID => 567,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $categoryIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 345,
            IndexingEntity::TARGET_PARENT_ID => 678,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $action = $this->instantiateTestObject();
        $action->execute(
            entityIds: [
                (int)$productIndexingEntity->getId(),
                (int)$cmsIndexingEntity->getId(),
                (int)$categoryIndexingEntity->getId(),
            ],
            lastAction: Actions::ADD,
        );

        $entities = $this->getIndexingEntities(apiKey: $apiKey);
        $this->assertCount(expectedCount: 3, haystack: $entities);
        foreach ($entities as $entity) {
            $this->assertSame(expected: Actions::NO_ACTION, actual: $entity->getNextAction());
            $this->assertSame(expected: Actions::ADD, actual: $entity->getLastAction());
            $this->assertTrue(condition: $entity->getIsIndexable());
            $this->assertNotNull(actual: $entity->getLastActionTimestamp());
        }

        $this->cleanIndexingEntities($apiKey);
    }

    public function testExecute_DoesNotUpdateEntity_WhenNotIndexable(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities($apiKey);

        $productIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $cmsIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 234,
            IndexingEntity::TARGET_PARENT_ID => 567,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $categoryIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 345,
            IndexingEntity::TARGET_PARENT_ID => 678,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $action = $this->instantiateTestObject();
        $action->execute(
            entityIds: [
                (int)$productIndexingEntity->getId(),
                (int)$cmsIndexingEntity->getId(),
                (int)$categoryIndexingEntity->getId(),
            ],
            lastAction: Actions::ADD,
        );

        $entities = $this->getIndexingEntities(apiKey: $apiKey);
        $this->assertCount(expectedCount: 3, haystack: $entities);
        foreach ($entities as $entity) {
            $this->assertSame(expected: Actions::ADD, actual: $entity->getNextAction());
            $this->assertSame(expected: Actions::NO_ACTION, actual: $entity->getLastAction());
            $this->assertFalse(condition: $entity->getIsIndexable());
            $this->assertNull(actual: $entity->getLastActionTimestamp());
        }

        $this->cleanIndexingEntities($apiKey);
    }

    public function testExecute_DoesNotUpdateNextAction_WhenActionIsDifferent(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities($apiKey);

        $productIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $action = $this->instantiateTestObject();
        $action->execute(
            entityIds: [
                (int)$productIndexingEntity->getId(),
            ],
            lastAction: Actions::ADD,
        );

        $entities = $this->getIndexingEntities(apiKey: $apiKey);
        $this->assertCount(expectedCount: 1, haystack: $entities);
        foreach ($entities as $entity) {
            $this->assertSame(expected: Actions::UPDATE, actual: $entity->getNextAction());
            $this->assertSame(expected: Actions::ADD, actual: $entity->getLastAction());
            $this->assertTrue(condition: $entity->getIsIndexable());
            $this->assertNotNull(actual: $entity->getLastActionTimestamp());
        }

        $this->cleanIndexingEntities($apiKey);
    }

    public function testExecute_SetsDeletedEntitiesToNoLongerBeIndexable(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities($apiKey);

        $productIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $cmsIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 234,
            IndexingEntity::TARGET_PARENT_ID => 567,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $categoryIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 345,
            IndexingEntity::TARGET_PARENT_ID => 678,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $action = $this->instantiateTestObject();
        $action->execute(
            entityIds: [
                (int)$productIndexingEntity->getId(),
                (int)$cmsIndexingEntity->getId(),
                (int)$categoryIndexingEntity->getId(),
            ],
            lastAction: Actions::DELETE,
        );

        $entities = $this->getIndexingEntities(apiKey: $apiKey);
        $this->assertCount(expectedCount: 3, haystack: $entities);
        foreach ($entities as $entity) {
            $this->assertSame(expected: Actions::NO_ACTION, actual: $entity->getNextAction());
            $this->assertSame(expected: Actions::DELETE, actual: $entity->getLastAction());
            $this->assertFalse(condition: $entity->getIsIndexable());
            $this->assertNotNull(actual: $entity->getLastActionTimestamp());
        }

        $this->cleanIndexingEntities($apiKey);
    }

    public function testExecute_LogsError_WhenIndexingRepositorySaveThrowsException(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities($apiKey);

        $indexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $exceptionMessage = 'Save Exception';

        $mockSearchResult = $this->getMockBuilder(IndexingEntitySearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([
                $indexingEntity,
            ]);

        $mockRepository = $this->getMockBuilder(IndexingEntityRepositoryInterface::class)
            ->getMock();
        $mockRepository->expects($this->once())
            ->method('getList')
            ->willReturn($mockSearchResult);
        $mockRepository->expects($this->once())
            ->method('save')
            ->willThrowException(new CouldNotSaveException(__($exceptionMessage)));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\Indexing\Service\Action\UpdateIndexingEntitiesActionsAction::updateIndexingEntity',
                    'message' => $exceptionMessage,
                ],
            );

        $action = $this->instantiateTestObject([
            'indexingEntityRepository' => $mockRepository,
            'logger' => $mockLogger,
        ]);
        $action->execute(
            entityIds: [
                (int)$indexingEntity->getId(),
            ],
            lastAction: Actions::UPDATE,
        );

        $this->cleanIndexingEntities($apiKey);
    }
}
