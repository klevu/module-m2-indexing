<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\UpdateIndexingEntitiesActionsService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\UpdateIndexingEntitiesActionsActionInterface;
use Klevu\IndexingApi\Service\BatchResponderServiceInterface;
use Klevu\PhpSDK\Model\Indexing\Record as IndexingRecord;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers UpdateIndexingEntitiesActionsService
 * @method BatchResponderServiceInterface instantiateTestObject(?array $arguments = null)
 * @method BatchResponderServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class UpdateIndexingEntitiesActionsServiceTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

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

        $this->implementationFqcn = UpdateIndexingEntitiesActionsService::class;
        $this->interfaceFqcn = BatchResponderServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_UpdatesMultipleEntityActions(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities($apiKey);

        $productIndexingEntity1 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $productIndexingEntity2 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 124,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $record1 = $this->objectManager->create(IndexingRecord::class, [
            'id' => $productIndexingEntity1->getTargetParentId() . '-' . $productIndexingEntity1->getTargetId(),
            'type' => 'KLEVU_PRODUCT',
            'relations' => [],
            'attributes' => [],
        ]);
        $record2 = $this->objectManager->create(IndexingRecord::class, [
            'id' => $productIndexingEntity2->getTargetId(),
            'type' => 'KLEVU_PRODUCT',
            'relations' => [],
            'attributes' => [],
        ]);

        $recordIterator = $this->objectManager->create(RecordIterator::class, [
            'data' => [$record1, $record2],
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'message' => 'Batch accepted successfully',
            'payload' => $recordIterator,
        ]);

        $service = $this->instantiateTestObject();
        $service->execute(
            apiPipelineResult: $mockApiResult,
            action: Actions::ADD,
            indexingEntities: [
                $productIndexingEntity1,
                $productIndexingEntity2,
            ],
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );

        $entities = $this->getIndexingEntities(apiKey: $apiKey);
        $this->assertCount(expectedCount: 2, haystack: $entities);
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

        $indexingEntity1 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $indexingEntity2 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 234,
            IndexingEntity::TARGET_PARENT_ID => 567,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $indexingEntity3 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 345,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $record1 = $this->objectManager->create(IndexingRecord::class, [
            'id' => $indexingEntity1->getTargetParentId() . '-' . $indexingEntity1->getTargetId(),
            'type' => 'KLEVU_CMS',
            'relations' => [],
            'attributes' => [],
        ]);
        $record2 = $this->objectManager->create(IndexingRecord::class, [
            'id' => $indexingEntity2->getTargetId(),
            'type' => 'KLEVU_CMS',
            'relations' => [],
            'attributes' => [],
        ]);
        $record3 = $this->objectManager->create(IndexingRecord::class, [
            'id' => $indexingEntity3->getTargetId(),
            'type' => 'KLEVU_CMS',
            'relations' => [],
            'attributes' => [],
        ]);

        $recordIterator = $this->objectManager->create(RecordIterator::class, [
            'data' => [$record1, $record2, $record3],
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'message' => 'Batch accepted successfully',
            'payload' => $recordIterator,
        ]);

        $service = $this->instantiateTestObject();
        $service->execute(
            apiPipelineResult: $mockApiResult,
            action: Actions::ADD,
            indexingEntities: [
                $indexingEntity1,
                $indexingEntity2,
                $indexingEntity3,
            ],
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
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

        $timestamp = date('Y-m-d H:i:s');

        $productIndexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => $timestamp,
        ]);

        $record1 = $this->objectManager->create(IndexingRecord::class, [
            'id' => $productIndexingEntity->getTargetId(),
            'type' => 'KLEVU_CATEGORY',
            'relations' => [],
            'attributes' => [],
        ]);

        $recordIterator = $this->objectManager->create(RecordIterator::class, [
            'data' => [$record1],
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'message' => 'Batch accepted successfully',
            'payload' => $recordIterator,
        ]);

        $service = $this->instantiateTestObject();
        $service->execute(
            apiPipelineResult: $mockApiResult,
            action: Actions::ADD,
            indexingEntities: [
                $productIndexingEntity,
            ],
            entityType: 'KLEVU_CATEGORY',
            apiKey: $apiKey,
        );

        $entities = $this->getIndexingEntities(apiKey: $apiKey);
        $this->assertCount(expectedCount: 1, haystack: $entities);
        foreach ($entities as $entity) {
            $this->assertSame(expected: Actions::UPDATE, actual: $entity->getNextAction());
            $this->assertSame(expected: Actions::UPDATE, actual: $entity->getLastAction());
            $this->assertTrue(condition: $entity->getIsIndexable());
            $this->assertSame(expected: $timestamp, actual: $entity->getLastActionTimestamp());
        }

        $this->cleanIndexingEntities($apiKey);
    }

    public function testExecute_SetsDeletedEntitiesToNoLongerBeIndexable(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities($apiKey);

        $categoryIndexingEntity1 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
        ]);

        $categoryIndexingEntity2 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 234,
            IndexingEntity::TARGET_PARENT_ID => 567,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
        ]);

        $categoryIndexingEntity3 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 345,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
        ]);

        $record1 = $this->objectManager->create(IndexingRecord::class, [
            'id' => $categoryIndexingEntity1->getTargetParentId() . '-' . $categoryIndexingEntity1->getTargetId(),
            'type' => 'KLEVU_CATEGORY',
            'relations' => [],
            'attributes' => [],
        ]);
        $record2 = $this->objectManager->create(IndexingRecord::class, [
            'id' => $categoryIndexingEntity2->getTargetParentId() . '-' . $categoryIndexingEntity2->getTargetId(),
            'type' => 'KLEVU_CATEGORY',
            'relations' => [],
            'attributes' => [],
        ]);
        $record3 = $this->objectManager->create(IndexingRecord::class, [
            'id' => $categoryIndexingEntity3->getTargetId(),
            'type' => 'KLEVU_CATEGORY',
            'relations' => [],
            'attributes' => [],
        ]);

        $recordIterator = $this->objectManager->create(RecordIterator::class, [
            'data' => [$record1, $record2, $record3],
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'message' => 'Batch accepted successfully',
            'payload' => $recordIterator,
        ]);

        $service = $this->instantiateTestObject();
        $service->execute(
            apiPipelineResult: $mockApiResult,
            action: Actions::DELETE,
            indexingEntities: [
                $categoryIndexingEntity1,
                $categoryIndexingEntity2,
                $categoryIndexingEntity3,
            ],
            entityType: 'KLEVU_CATEGORY',
            apiKey: $apiKey,
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

    public function testActionIsNotCalled_WhenApiResponseIsNotSuccessful(): void
    {
        $apiKey = 'klevu-test-api-key';

        $indexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => false,
        ]);

        $mockUpdateIndexingEntitiesAction = $this->getMockBuilder(UpdateIndexingEntitiesActionsActionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockUpdateIndexingEntitiesAction->expects($this->never())
            ->method('execute');

        $service = $this->instantiateTestObject([
            'updateIndexingEntitiesActionsAction' => $mockUpdateIndexingEntitiesAction,
        ]);
        $service->execute(
            apiPipelineResult: $mockApiResult,
            action: Actions::DELETE,
            indexingEntities: [$indexingEntity],
            entityType: 'KLEVU_CATEGORY',
            apiKey: $apiKey,
        );
    }
}
