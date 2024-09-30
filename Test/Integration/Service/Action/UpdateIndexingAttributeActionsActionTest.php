<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\Action\UpdateIndexingAttributeActionsAction;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\Data\IndexingAttributeSearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\UpdateIndexingAttributeActionsActionInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers UpdateIndexingAttributeActionsAction
 * @method UpdateIndexingAttributeActionsActionInterface instantiateTestObject(?array $arguments = null)
 * @method UpdateIndexingAttributeActionsActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class UpdateIndexingAttributeActionsActionTest extends TestCase
{
    use IndexingAttributesTrait;
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

        $this->implementationFqcn = UpdateIndexingAttributeActionsAction::class;
        $this->interfaceFqcn = UpdateIndexingAttributeActionsActionInterface::class;
        $this->constructorArgumentDefaults = [
            'lastAction' => '',
            'targetAttributeType' => 'KLEVU_PRODUCT',
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_UpdatesProductAttributeActions_ForEntityId(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $productAttribute = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $categoryAttribute = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $action = $this->instantiateTestObject([
            'lastAction' => 'Add',
            'targetAttributeType' => 'KLEVU_PRODUCT',
        ]);
        $action->execute(apiKey: $apiKey, targetId: $productAttribute->getTargetId());

        $result = $this->getIndexingAttributes(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $filteredResult = array_filter(
            array: $result,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $productAttribute->getId() === $indexingAttribute->getId()
            ),
        );
        $indexingAttribute = array_shift($filteredResult);
        $this->assertSame(expected: $productAttribute->getTargetId(), actual: $indexingAttribute->getTargetId());
        $this->assertSame(expected: $productAttribute->getApiKey(), actual: $indexingAttribute->getApiKey());
        $this->assertSame(
            expected: $productAttribute->getTargetAttributeType(),
            actual: $indexingAttribute->getTargetAttributeType(),
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());

        $result = $this->getIndexingAttributes(type: 'KLEVU_CATEGORY', apiKey: $apiKey);
        $filteredResult = array_filter(
            array: $result,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $categoryAttribute->getId() === $indexingAttribute->getId()
            ),
        );
        $indexingAttribute = array_shift($filteredResult);
        $this->assertSame(expected: $categoryAttribute->getTargetId(), actual: $indexingAttribute->getTargetId());
        $this->assertSame(expected: $categoryAttribute->getApiKey(), actual: $indexingAttribute->getApiKey());
        $this->assertSame(
            expected: $categoryAttribute->getTargetAttributeType(),
            actual: $indexingAttribute->getTargetAttributeType(),
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getLastAction());
        $this->assertNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());

        $this->cleanIndexingAttributes(apiKey: $apiKey);
    }

    public function testExecute_UpdatesProductAttributeActions_ForEntityCode(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $productAttribute = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $categoryAttribute = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $action = $this->instantiateTestObject([
            'lastAction' => 'Add',
            'targetAttributeType' => 'KLEVU_PRODUCT',
        ]);
        $action->execute(apiKey: $apiKey, targetCode: $productAttribute->getTargetCode());

        $result = $this->getIndexingAttributes(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $filteredResult = array_filter(
            array: $result,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $productAttribute->getId() === $indexingAttribute->getId()
            ),
        );
        $indexingAttribute = array_shift($filteredResult);
        $this->assertSame(expected: $productAttribute->getTargetId(), actual: $indexingAttribute->getTargetId());
        $this->assertSame(expected: $productAttribute->getApiKey(), actual: $indexingAttribute->getApiKey());
        $this->assertSame(
            expected: $productAttribute->getTargetAttributeType(),
            actual: $indexingAttribute->getTargetAttributeType(),
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());

        $result = $this->getIndexingAttributes(type: 'KLEVU_CATEGORY', apiKey: $apiKey);
        $filteredResult = array_filter(
            array: $result,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $categoryAttribute->getId() === $indexingAttribute->getId()
            ),
        );
        $indexingAttribute = array_shift($filteredResult);
        $this->assertSame(expected: $categoryAttribute->getTargetId(), actual: $indexingAttribute->getTargetId());
        $this->assertSame(expected: $categoryAttribute->getApiKey(), actual: $indexingAttribute->getApiKey());
        $this->assertSame(
            expected: $categoryAttribute->getTargetAttributeType(),
            actual: $indexingAttribute->getTargetAttributeType(),
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getLastAction());
        $this->assertNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());

        $this->cleanIndexingAttributes(apiKey: $apiKey);
    }

    public function testExecute_ThrowsException_WhenNeitherTargetIdAndTargetCodeProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Either TargetId or TargetCode is required to update and indexing attribute.');

        $apiKey = 'klevu-test-api-key';
        $action = $this->instantiateTestObject([
            'lastAction' => 'Add',
            'targetAttributeType' => 'KLEVU_PRODUCT',
        ]);
        $action->execute(apiKey: $apiKey);
    }

    public function testExecute_DoesNotUpdateAttribute_WithDifferentApiKey(): void
    {
        $apiKey1 = 'klevu-test-api-key-1';
        $apiKey2 = 'klevu-test-api-key-2';
        $this->cleanIndexingAttributes(apiKey: $apiKey1);
        $this->cleanIndexingAttributes(apiKey: $apiKey2);

        $productAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $productAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $action = $this->instantiateTestObject([
            'lastAction' => 'Add',
            'targetAttributeType' => 'KLEVU_PRODUCT',
        ]);
        $action->execute($apiKey1, $productAttribute1->getTargetId());

        $result = $this->getIndexingAttributes(type: 'KLEVU_PRODUCT', apiKey: $apiKey1);
        $filteredResult = array_filter(
            array: $result,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $productAttribute1->getId() === $indexingAttribute->getId()
            ),
        );
        $indexingAttribute = array_shift($filteredResult);
        $this->assertSame(expected: $productAttribute1->getTargetId(), actual: $indexingAttribute->getTargetId());
        $this->assertSame(expected: $apiKey1, actual: $indexingAttribute->getApiKey());
        $this->assertSame(
            expected: $productAttribute1->getTargetAttributeType(),
            actual: $indexingAttribute->getTargetAttributeType(),
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());

        $result = $this->getIndexingAttributes(type: 'KLEVU_PRODUCT', apiKey: $apiKey2);
        $filteredResult = array_filter(
            array: $result,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $productAttribute2->getId() === $indexingAttribute->getId()
            ),
        );
        $indexingAttribute = array_shift($filteredResult);
        $this->assertSame(expected: $productAttribute2->getTargetId(), actual: $indexingAttribute->getTargetId());
        $this->assertSame(expected: $apiKey2, actual: $indexingAttribute->getApiKey());
        $this->assertSame(
            expected: $productAttribute2->getTargetAttributeType(),
            actual: $indexingAttribute->getTargetAttributeType(),
        );
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getLastAction());
        $this->assertNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());

        $this->cleanIndexingAttributes(apiKey: $apiKey1);
        $this->cleanIndexingAttributes(apiKey: $apiKey2);
    }

    public function testExecute_DoesNotUpdateAttribute_WhenNotIndexable(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $productAttribute = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);

        $action = $this->instantiateTestObject([
            'lastAction' => 'Add',
            'targetAttributeType' => 'KLEVU_PRODUCT',
        ]);
        $action->execute($apiKey, $productAttribute->getTargetId());

        $result = $this->getIndexingAttributes(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $filteredResult = array_filter(
            array: $result,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $productAttribute->getId() === $indexingAttribute->getId()
            ),
        );
        $indexingAttribute = array_shift($filteredResult);
        $this->assertSame(expected: $productAttribute->getTargetId(), actual: $indexingAttribute->getTargetId());
        $this->assertSame(expected: $apiKey, actual: $indexingAttribute->getApiKey());
        $this->assertSame(
            expected: $productAttribute->getTargetAttributeType(),
            actual: $indexingAttribute->getTargetAttributeType(),
        );
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getLastAction());
        $this->assertNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertFalse(condition: $indexingAttribute->getIsIndexable());

        $this->cleanIndexingAttributes(apiKey: $apiKey);
    }

    public function testExecute_SetsDeletedAttributesToNoLongerBeIndexable(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $productAttribute = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $action = $this->instantiateTestObject([
            'lastAction' => 'Delete',
            'targetAttributeType' => 'KLEVU_PRODUCT',
        ]);
        $action->execute($apiKey, $productAttribute->getTargetId());

        $result = $this->getIndexingAttributes(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $filteredResult = array_filter(
            array: $result,
            callback: static fn (IndexingAttributeInterface $indexingAttribute): bool => (
                $productAttribute->getId() === $indexingAttribute->getId()
            ),
        );
        $indexingAttribute = array_shift($filteredResult);
        $this->assertSame(expected: $productAttribute->getTargetId(), actual: $indexingAttribute->getTargetId());
        $this->assertSame(expected: $apiKey, actual: $indexingAttribute->getApiKey());
        $this->assertSame(
            expected: $productAttribute->getTargetAttributeType(),
            actual: $indexingAttribute->getTargetAttributeType(),
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::DELETE, actual: $indexingAttribute->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertFalse(condition: $indexingAttribute->getIsIndexable());

        $this->cleanIndexingAttributes(apiKey: $apiKey);
    }

    public function testExecute_LogsError_WhenIndexingRepositorySaveThrowsException(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $productAttribute = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $exceptionMessage = 'Save Exception';

        $mockSearchResult = $this->getMockBuilder(IndexingAttributeSearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([
                $productAttribute,
            ]);

        $mockRepository = $this->getMockBuilder(IndexingAttributeRepositoryInterface::class)
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
                    'method' => 'Klevu\Indexing\Service\Action\UpdateIndexingAttributeActionsAction::execute',
                    'message' => $exceptionMessage,
                ],
            );

        $action = $this->instantiateTestObject([
            'indexingAttributeRepository' => $mockRepository,
            'logger' => $mockLogger,
            'lastAction' => 'Add',
            'targetAttributeType' => 'KLEVU_PRODUCT',
        ]);
        $action->execute($apiKey, $productAttribute->getTargetId());
    }
}
