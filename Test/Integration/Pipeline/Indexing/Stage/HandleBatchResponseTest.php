<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Pipeline\Indexing\Stage;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Pipeline\Indexing\Stage\HandleBatchResponse;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\UpdateIndexingEntitiesActionsActionInterface;
use Klevu\PhpSDK\Model\Indexing\Record as IndexingRecord;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelineArgumentsException;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelinePayloadException;
use Klevu\Pipelines\Model\Extraction;
use Klevu\Pipelines\Pipeline\Context;
use Klevu\Pipelines\Pipeline\PipelineInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers HandleBatchResponse
 * @method HandleBatchResponse instantiateTestObject(?array $arguments = null)
 * @method HandleBatchResponse instantiateTestObjectFromInterface(?array $arguments = null)
 */
class HandleBatchResponseTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
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

        $this->implementationFqcn = HandleBatchResponse::class;
        $this->interfaceFqcn = PipelineInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
    }

    public function testGetIdentifier_ReturnsIdentifier(): void
    {
        $pipeline = $this->instantiateTestObject([
            'identifier' => 'some-string',
        ]);

        $this->assertSame(expected: 'some-string', actual: $pipeline->getIdentifier());
    }

    public function testSetArgs_ThrowsException_WhenActionMissing(): void
    {
        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Argument (%s) is required',
                HandleBatchResponse::ARGUMENT_KEY_ACTION,
            ),
        );

        $mockApiResult = $this->getMockBuilder(ApiPipelineResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        $pipeline = $this->instantiateTestObject([
            'identifier' => 'some-string',
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => 'klevu-test-api-key',
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => 'KLEUU_PRODUCT',
            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );
    }

    public function testSetArgs_ThrowsException_WhenApiKeyMissing(): void
    {
        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Argument (%s) is required',
                HandleBatchResponse::ARGUMENT_KEY_API_KEY,
            ),
        );

        $mockApiResult = $this->getMockBuilder(ApiPipelineResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        $pipeline = $this->instantiateTestObject([
            'identifier' => 'some-string',
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_ACTION => Actions::ADD->value,
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => 'KLEUU_PRODUCT',

            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );
    }

    public function testSetArgs_ThrowsException_WhenEntityTypeMissing(): void
    {
        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Argument (%s) is required',
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE,
            ),
        );

        $mockApiResult = $this->getMockBuilder(ApiPipelineResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        $pipeline = $this->instantiateTestObject([
            'identifier' => 'some-string',
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_ACTION => Actions::ADD->value,
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => 'klevu-test-api-key',
            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );
    }

    /**
     * @dataProvider dataProvider_testExecute_ActionArgument_NotString
     * @magentoAppIsolation enabled
     */
    public function testExecute_ActionArgument_NotString(mixed $action): void
    {
        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Argument (%s) must be string|%s; Received %s',
                HandleBatchResponse::ARGUMENT_KEY_ACTION,
                Extraction::class,
                get_debug_type($action),
            ),
        );

        $mockApiResult = $this->getMockBuilder(ApiPipelineResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        $pipeline = $this->instantiateTestObject([
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => 'klevu-test-api-key',
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => 'KLEUU_PRODUCT',
                HandleBatchResponse::ARGUMENT_KEY_ACTION => $action,
            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );
    }

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testExecute_ActionArgument_NotString(): array
    {
        return [
            [42],
            [3.14],
            [[42]],
            [(object)['payload' => 42]],
        ];
    }

    public function testExecute_ThrowsException_WhenInvalidAction_Provided(): void
    {
        $action = 'invalidString';

        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Argument (%s) valid action (%s); Received %s',
                HandleBatchResponse::ARGUMENT_KEY_ACTION,
                Actions::class,
                $action,
            ),
        );

        $mockApiResult = $this->getMockBuilder(ApiPipelineResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        $pipeline = $this->instantiateTestObject([
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => 'klevu-test-api-key',
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => 'KLEUU_PRODUCT',
                HandleBatchResponse::ARGUMENT_KEY_ACTION => $action,
            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );
    }

    /**
     * @dataProvider dataProvider_testExecute_ApiKeyArgument_NotString
     * @magentoAppIsolation enabled
     */
    public function testExecute_ApiKeyArgument_NotString(mixed $apiKey): void
    {
        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Argument (%s) must be string|%s; Received %s',
                HandleBatchResponse::ARGUMENT_KEY_API_KEY,
                Extraction::class,
                get_debug_type($apiKey),
            ),
        );

        $mockApiResult = $this->getMockBuilder(ApiPipelineResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        $pipeline = $this->instantiateTestObject([
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => $apiKey,
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => 'KLEUU_PRODUCT',
                HandleBatchResponse::ARGUMENT_KEY_ACTION => Actions::UPDATE->value,
            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );
    }

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testExecute_ApiKeyArgument_NotString(): array
    {
        return [
            [42],
            [3.14],
            [[42]],
            [(object)['payload' => 42]],
        ];
    }

    /**
     * @dataProvider dataProvider_testExecute_EntityTypeArgument_NotString
     * @magentoAppIsolation enabled
     */
    public function testExecute_EntityTypeArgument_NotString(mixed $entityType): void
    {
        $this->expectException(InvalidPipelineArgumentsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Argument (%s) must be string|%s; Received %s',
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE,
                Extraction::class,
                get_debug_type($entityType),
            ),
        );

        $mockApiResult = $this->getMockBuilder(ApiPipelineResult::class)
            ->disableOriginalConstructor()
            ->getMock();

        $pipeline = $this->instantiateTestObject([
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => 'Klevu-test-api-key',
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => $entityType,
                HandleBatchResponse::ARGUMENT_KEY_ACTION => Actions::UPDATE->value,
            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );
    }

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testExecute_EntityTypeArgument_NotString(): array
    {
        return [
            [42],
            [3.14],
            [[42]],
            [(object)['payload' => 42]],
        ];
    }

    /**
     * @dataProvider dataProvider_testExecute_PayloadNotApiResult
     */
    public function testExecute_PayloadNotApiResult(mixed $payload): void
    {
        $this->expectException(InvalidPipelinePayloadException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Payload must be instance of %s; Received %s',
                ApiPipelineResult::class,
                is_scalar($payload)
                    ? $payload
                    : get_debug_type($payload),
            ),
        );

        $pipeline = $this->instantiateTestObject([
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => 'klevu-test-api-key',
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => 'KLEUU_PRODUCT',
                HandleBatchResponse::ARGUMENT_KEY_ACTION => Actions::ADD->value,
            ],
        ]);
        $pipeline->execute(
            payload: $payload,
            context: new Context([]),
        );
    }

    /**
     * @return mixed[][]
     */
    public function dataProvider_testExecute_PayloadNotApiResult(): array
    {
        return [
            [null],
            [true],
            [1],
            [1.23],
            ['string'],
            [['array']],
            [new DataObject()],
        ];
    }

    public function testExecute_DoesNotCallUpdate_Actions_WhenPipelineFails(): void
    {
        $apiKey = 'klevu-test-api-key';

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_parent_product',
        ]);
        $parentProductFixture = $this->productFixturePool->get('test_parent_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $parentProductFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );

        $record = $this->objectManager->create(IndexingRecord::class, [
            'id' => $parentProductFixture->getId() . '-' . $productFixture->getId(),
            'type' => 'KLEVU_PRODUCT',
            'relations' => [],
            'attributes' => [],
            'display' => [],
        ]);

        $recordIterator = $this->objectManager->create(RecordIterator::class, [
            'data' => [$record],
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => false,
            'message' => 'Batch rejected',
            'payload' => $recordIterator,
        ]);

        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEventManager->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [
                    'klevu_indexing_handle_batch_response_before',
                    [
                        'apiPipelineResult' => $mockApiResult,
                        'action' => Actions::ADD,
                        'indexingEntities' => [$indexingEntity->getId() => $indexingEntity],
                        'entityType' => 'KLEVU_PRODUCT',
                        'apiKey' => $apiKey,
                    ],
                ],
                [
                    'klevu_indexing_handle_batch_response_after',
                    [
                        'apiPipelineResult' => $mockApiResult,
                        'action' => Actions::ADD,
                        'indexingEntities' => [$indexingEntity->getId() => $indexingEntity],
                        'entityType' => 'KLEVU_PRODUCT',
                        'apiKey' => $apiKey,
                    ],
                ],
            );

        $mockUpdateEntitiesAction = $this->getMockBuilder(UpdateIndexingEntitiesActionsActionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockUpdateEntitiesAction->expects($this->never())
            ->method('execute');

        $pipeline = $this->instantiateTestObject([
            'updateIndexingEntitiesActionsAction' => $mockUpdateEntitiesAction,
            'eventManager' => $mockEventManager,
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => $apiKey,
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => 'KLEVU_PRODUCT',
                HandleBatchResponse::ARGUMENT_KEY_ACTION => Actions::ADD->value,
            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );

        $this->cleanIndexingEntities($apiKey);
    }

    public function testExecute_UpdatesEntityAction_WhenPipelineSucceeds(): void
    {
        $apiKey = 'klevu-test-api-key';

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_parent_product',
        ]);
        $parentProductFixture = $this->productFixturePool->get('test_parent_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => $parentProductFixture->getId(),
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $indexingEntity = $this->getIndexingEntityForEntity(
            apiKey: $apiKey,
            entity: $productFixture->getProduct(),
            type: 'KLEVU_PRODUCT',
        );

        $record = $this->objectManager->create(IndexingRecord::class, [
            'id' => $parentProductFixture->getId() . '-' . $productFixture->getId(),
            'type' => 'KLEVU_PRODUCT',
            'relations' => [],
            'attributes' => [],
            'display' => [],
        ]);

        $recordIterator = $this->objectManager->create(RecordIterator::class, [
            'data' => [$record],
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'message' => 'Batch accepted successfully',
            'payload' => $recordIterator,
        ]);

        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEventManager->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [
                    'klevu_indexing_handle_batch_response_before',
                    [
                        'apiPipelineResult' => $mockApiResult,
                        'action' => Actions::ADD,
                        'indexingEntities' => [$indexingEntity->getId() => $indexingEntity],
                        'entityType' => 'KLEVU_PRODUCT',
                        'apiKey' => $apiKey,
                    ],
                ],
                [
                    'klevu_indexing_handle_batch_response_after',
                    [
                        'apiPipelineResult' => $mockApiResult,
                        'action' => Actions::ADD,
                        'indexingEntities' => [$indexingEntity->getId() => $indexingEntity],
                        'entityType' => 'KLEVU_PRODUCT',
                        'apiKey' => $apiKey,
                    ],
                ],
            );

        $pipeline = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => $apiKey,
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => 'KLEVU_PRODUCT',
                HandleBatchResponse::ARGUMENT_KEY_ACTION => Actions::ADD->value,
            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );

        $indexingEntities = $this->getIndexingEntities(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $result = array_shift($indexingEntities);
        $this->assertSame(expected: Actions::NO_ACTION, actual: $result->getNextAction());
        $this->assertSame(expected: Actions::ADD, actual: $result->getLastAction());
        $this->assertTrue(condition: $result->getIsIndexable());
        $this->assertNotNull(actual: $result->getLastActionTimestamp());

        $this->cleanIndexingEntities($apiKey);
    }

    public function testExecute_UpdatesCorrectEntity_WhenMultipleEntitiesWithSameIdExist_OnPipelineSuccess_ForDelete(
    ): void {
        $apiKey = 'klevu-test-api-key';

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION_TIMESTAMP => null,
        ]);
        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );

        $record = $this->objectManager->create(IndexingRecord::class, [
            'id' => $productFixture->getId(),
            'type' => 'KLEVU_PRODUCT',
        ]);

        $recordIterator = $this->objectManager->create(RecordIterator::class, [
            'data' => [$record],
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'message' => 'Batch accepted successfully',
            'payload' => $recordIterator,
        ]);

        $eventDataIndexingEntities = [];
        foreach ($indexingEntities as $indexingEntity) {
            $eventDataIndexingEntities[$indexingEntity->getId()] = $indexingEntity;
        }

        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEventManager->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [
                    'klevu_indexing_handle_batch_response_before',
                    [
                        'apiPipelineResult' => $mockApiResult,
                        'action' => Actions::DELETE,
                        'indexingEntities' => $eventDataIndexingEntities,
                        'entityType' => 'KLEVU_PRODUCT',
                        'apiKey' => $apiKey,
                    ],
                ],
                [
                    'klevu_indexing_handle_batch_response_after',
                    [
                        'apiPipelineResult' => $mockApiResult,
                        'action' => Actions::DELETE,
                        'indexingEntities' => $eventDataIndexingEntities,
                        'entityType' => 'KLEVU_PRODUCT',
                        'apiKey' => $apiKey,
                    ],
                ],
            );

        $pipeline = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => $apiKey,
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => 'KLEVU_PRODUCT',
                HandleBatchResponse::ARGUMENT_KEY_ACTION => Actions::DELETE->value,
            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );

        $indexingEntities = $this->getIndexingEntities(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $simpleResults = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity): bool => (
                $indexingEntity->getTargetEntitySubtype() === 'simple'
            ),
        );
        $simpleResult = array_shift($simpleResults);
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $simpleResult->getNextAction(),
            message: sprintf(
                'Expected %s: Received %s',
                Actions::NO_ACTION->value,
                $simpleResult->getNextAction()->value,
            ),
        );
        $this->assertSame(
            expected: Actions::DELETE,
            actual: $simpleResult->getLastAction(),
            message: sprintf(
                'Expected %s: Received %s',
                Actions::DELETE->value,
                $simpleResult->getLastAction()->value,
            ),
        );
        $this->assertFalse(condition: $simpleResult->getIsIndexable());
        $this->assertNotNull(actual: $simpleResult->getLastActionTimestamp());

        $configurableResults = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity): bool => (
                $indexingEntity->getTargetEntitySubtype() === 'configurable'
            ),
        );
        $configurableResult = array_shift($configurableResults);
        $this->assertSame(
            expected: Actions::ADD,
            actual: $configurableResult->getNextAction(),
            message: sprintf(
                'Expected %s: Received %s',
                Actions::ADD->value,
                $simpleResult->getNextAction()->value,
            ),
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $configurableResult->getLastAction(),
            message: sprintf(
                'Expected %s: Received %s',
                Actions::NO_ACTION->value,
                $simpleResult->getLastAction()->value,
            ),
        );
        $this->assertTrue(condition: $configurableResult->getIsIndexable());
        $this->assertNull(actual: $configurableResult->getLastActionTimestamp());

        $this->cleanIndexingEntities($apiKey);
    }

    public function testExecute_UpdatesCorrectEntity_WhenMultipleEntitiesWithSameIdExist_OnPipelineSuccess_ForAdd(
    ): void {
        $apiKey = 'klevu-test-api-key';

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION_TIMESTAMP => null,
        ]);
        $indexingEntities = $this->getIndexingEntities(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );

        $record = $this->objectManager->create(IndexingRecord::class, [
            'id' => $productFixture->getId(),
            'type' => 'KLEVU_PRODUCT',
            'relations' => [],
            'attributes' => [],
            'display' => [],
        ]);

        $recordIterator = $this->objectManager->create(RecordIterator::class, [
            'data' => [$record],
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'message' => 'Batch accepted successfully',
            'payload' => $recordIterator,
        ]);

        $eventDataIndexingEntities = [];
        foreach ($indexingEntities as $indexingEntity) {
            $eventDataIndexingEntities[$indexingEntity->getId()] = $indexingEntity;
        }

        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEventManager->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [
                    'klevu_indexing_handle_batch_response_before',
                    [
                        'apiPipelineResult' => $mockApiResult,
                        'action' => Actions::ADD,
                        'indexingEntities' => $eventDataIndexingEntities,
                        'entityType' => 'KLEVU_PRODUCT',
                        'apiKey' => $apiKey,
                    ],
                ],
                [
                    'klevu_indexing_handle_batch_response_after',
                    [
                        'apiPipelineResult' => $mockApiResult,
                        'action' => Actions::ADD,
                        'indexingEntities' => $eventDataIndexingEntities,
                        'entityType' => 'KLEVU_PRODUCT',
                        'apiKey' => $apiKey,
                    ],
                ],
            );

        $pipeline = $this->instantiateTestObject([
            'eventManager' => $mockEventManager,
            'args' => [
                HandleBatchResponse::ARGUMENT_KEY_API_KEY => $apiKey,
                HandleBatchResponse::ARGUMENT_KEY_ENTITY_TYPE => 'KLEVU_PRODUCT',
                HandleBatchResponse::ARGUMENT_KEY_ACTION => Actions::ADD->value,
            ],
        ]);
        $pipeline->execute(
            payload: $mockApiResult,
            context: new Context([]),
        );

        $indexingEntities = $this->getIndexingEntities(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $simpleResults = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity): bool => (
                $indexingEntity->getTargetEntitySubtype() === 'simple'
            ),
        );
        $simpleResult = array_shift($simpleResults);
        $this->assertSame(
            expected: Actions::DELETE,
            actual: $simpleResult->getNextAction(),
            message: sprintf(
                'Expected %s: Received %s',
                Actions::DELETE->value,
                $simpleResult->getNextAction()->value,
            ),
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $simpleResult->getLastAction(),
            message: sprintf(
                'Expected %s: Received %s',
                Actions::UPDATE->value,
                $simpleResult->getLastAction()->value,
            ),
        );
        $this->assertTrue(condition: $simpleResult->getIsIndexable());
        $this->assertNotNull(actual: $simpleResult->getLastActionTimestamp());

        $configurableResults = array_filter(
            array: $indexingEntities,
            callback: static fn (IndexingEntityInterface $indexingEntity): bool => (
                $indexingEntity->getTargetEntitySubtype() === 'configurable'
            ),
        );
        $configurableResult = array_shift($configurableResults);
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $configurableResult->getNextAction(),
            message: sprintf(
                'Expected %s: Received %s',
                Actions::NO_ACTION->value,
                $simpleResult->getNextAction()->value,
            ),
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $configurableResult->getLastAction(),
            message: sprintf(
                'Expected %s: Received %s',
                Actions::ADD->value,
                $simpleResult->getLastAction()->value,
            ),
        );
        $this->assertTrue(condition: $configurableResult->getIsIndexable());
        $this->assertNotNull(actual: $configurableResult->getLastActionTimestamp());

        $this->cleanIndexingEntities($apiKey);
    }
}
