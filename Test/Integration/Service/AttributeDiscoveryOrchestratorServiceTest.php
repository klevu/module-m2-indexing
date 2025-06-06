<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Model\ResourceModel\IndexingAttribute\Collection;
use Klevu\Indexing\Service\AttributeDiscoveryOrchestratorService;
use Klevu\Indexing\Service\FilterAttributesToDeleteService;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\Data\IndexingAttributeSearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Action\AddIndexingAttributesActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingAttributesToBeIndexableActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingAttributesToDeleteActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingAttributesToUpdateActionInterface;
use Klevu\IndexingApi\Service\AttributeConflictHandlerServiceInterface;
use Klevu\IndexingApi\Service\AttributeDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\FilterAttributesToAddServiceInterface;
use Klevu\IndexingApi\Service\FilterAttributesToDeleteServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributeDiscoveryProviderInterface;
use Klevu\IndexingProducts\Model\Source\Aspect;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\AttributeApiCallTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Service\AttributeDiscoveryOrchestratorService::class
 * @method AttributeDiscoveryOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeDiscoveryOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeDiscoveryOrchestratorServiceTest extends TestCase
{
    use AttributeApiCallTrait;
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = AttributeDiscoveryOrchestratorService::class;
        $this->interfaceFqcn = AttributeDiscoveryOrchestratorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
        $this->mockSdkAttributeGetApiCall();
        $this->cleanIndexingAttributes('klevu-api-key');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanIndexingAttributes('klevu-api-key');
        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();

        $this->removeSharedApiInstances();
    }

    public function testExecute_NoProviders_ReturnsSuccessFalse(): void
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Method: {method} - Warning: {message}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\Indexing\Service\AttributeDiscoveryOrchestratorService::validateDiscoveryProviders',
                    'message' => 'No providers available for attribute discovery.',
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'discoveryProviders' => [],
            'attributeConflictHandlerService' => $this->getMockAttributeConflictHandlerService(),
        ]);
        $result = $service->execute();

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 1, haystack: $messages, message: 'Message Count');
        $this->assertContains(
            needle: 'No providers available for attribute discovery.',
            haystack: $messages,
            message: 'Expected Message Exists',
        );
    }

    public function testExecute_WithInvalidTypeArgument_ReturnsFailure(): void
    {
        $collection = $this->objectManager->create(Collection::class);
        $count = $collection->getSize();

        $mockProvider = $this->getMockBuilder(AttributeDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->expects($this->once())
            ->method('getAttributeType')
            ->willReturn('KLEVU_PRODUCTS');
        $mockProvider->expects($this->never())
            ->method('getData');

        $service = $this->instantiateTestObject([
            'discoveryProviders' => [
                'products' => $mockProvider,
            ],
            'attributeConflictHandlerService' => $this->getMockAttributeConflictHandlerService(),
        ]);
        $result = $service->execute(attributeTypes: ['KLEVU_CMS']);

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $this->assertContains(
            needle: 'Supplied attribute types did not match any providers.',
            haystack: $result->getMessages(),
        );
        $collection = $this->objectManager->create(Collection::class);
        $this->assertCount(
            expectedCount: 0 + $count,
            haystack: $collection->getItems(),
            message: 'Final Items Count',
        );
    }

    public function testExecute_WithTypeArgument(): void
    {
        $collection = $this->objectManager->create(Collection::class);
        $count = $collection->getSize();

        $mockProvider = $this->getMockBuilder(AttributeDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->expects($this->exactly(2))
            ->method('getAttributeType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProvider->expects($this->once())
            ->method('getData')
            ->willReturn([]);

        $service = $this->instantiateTestObject([
            'discoveryProviders' => [
                'products' => $mockProvider,
            ],
            'attributeConflictHandlerService' => $this->getMockAttributeConflictHandlerService(),
        ]);
        $result = $service->execute(attributeTypes: ['KLEVU_PRODUCT']);

        $this->assertTrue(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertFalse(condition: $result->hasMessages(), message: 'Has Messages');
        $collection = $this->objectManager->create(Collection::class);
        $this->assertCount(
            expectedCount: 0 + $count,
            haystack: $collection->getItems(),
            message: 'Final Items Count',
        );
    }

    public function testExecute_Save_ReturnSuccessFalse_AnyAttributesFailToSave(): void
    {
        $apiKey = 'klevu-api-key';
        $authKey = 'klevu-rest-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $mockProvider = $this->getMockBuilder(AttributeDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->expects($this->exactly(2))
            ->method('getAttributeType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProvider->expects($this->once())
            ->method('getData')
            ->willReturn([
                'klevu-api-key' => [
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 1,
                        'attributeCode' => 'klevu_test_attribute_1',
                        'apiKey' => 'klevu-api-key',
                        'isIndexable' => true,
                        'klevuAttributeName' => 'name1',
                    ]),
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 2,
                        'attributeCode' => 'klevu_test_attribute_2',
                        'apiKey' => 'klevu-api-key',
                        'isIndexable' => false,
                        'klevuAttributeName' => 'name2',
                    ]),
                ],
            ]);

        $mockIndexingAttributeRepository = $this->getMockBuilder(IndexingAttributeRepositoryInterface::class)
            ->getMock();
        $mockIndexingAttributeRepository->expects($this->exactly(2))
            ->method('create')
            ->willReturn(
                $this->objectManager->create(IndexingAttributeInterface::class),
            );
        $matcher = $this->exactly(2);
        $mockIndexingAttributeRepository->expects($matcher)
            ->method('save')
            ->willReturnCallback(callback: function () use ($matcher): IndexingAttributeInterface {
                if ($matcher->getInvocationCount() === 1) {
                    throw new \Exception('Could not Save Attribute');
                }
                return $this->objectManager->create(IndexingAttributeInterface::class);
            });

        $addIndexingAttributesAction = $this->objectManager->create(AddIndexingAttributesActionInterface::class, [
            'indexingAttributeRepository' => $mockIndexingAttributeRepository,
        ]);

        $service = $this->instantiateTestObject([
            'addIndexingAttributesAction' => $addIndexingAttributesAction,
            'discoveryProviders' => [
                'products' => $mockProvider,
            ],
            'attributeConflictHandlerService' => $this->getMockAttributeConflictHandlerService(),
        ]);
        $result = $service->execute(attributeTypes: ['KLEVU_PRODUCT']);

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 1, haystack: $messages, message: 'Message Count');
        $this->assertContains(
            needle: 'Failed to save Indexing Attributes for Magento Attribute IDs (1). See log for details.',
            haystack: $messages,
            message: 'Expected Message Exists',
        );
    }

    public function testExecute_Deletion_ReturnSuccessFalse_AnyAttributesFailToSave(): void
    {
        $mockProvider = $this->getMockBuilder(AttributeDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->expects($this->exactly(2))
            ->method('getAttributeType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProvider->expects($this->once())
            ->method('getData')
            ->willReturn([
                'klevu-api-key' => [
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 1,
                        'attributeCode' => 'klevu_test_attribute_1',
                        'apiKey' => 'klevu-api-key',
                        'isIndexable' => true,
                        'klevuAttributeName' => 'name1',
                    ]),
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 2,
                        'attributeCode' => 'klevu_test_attribute_2',
                        'apiKey' => 'klevu-api-key',
                        'isIndexable' => false,
                        'klevuAttributeName' => 'name2',
                    ]),
                ],
            ]);

        $mockFilterAttributesToAddService = $this->getMockBuilder(FilterAttributesToAddServiceInterface::class)
            ->getMock();
        $mockFilterAttributesToAddService->expects($this->exactly(1))
            ->method('execute')
            ->willReturn([]);

        $mockFilterAttributesToDeleteService = $this->getMockBuilder(FilterAttributesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterAttributesToDeleteService->expects($this->exactly(1))
            ->method('execute')
            ->willReturn([
                '1-klevu-api-key-KLEVU_PRODUCTS',
                '2-klevu-api-key-KLEVU_PRODUCTS',
            ]);

        $attribute1 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute1->setId(1);
        $attribute2 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute2->setId(2);
        $mockSearchResult = $this->getMockBuilder(IndexingAttributeSearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([$attribute1, $attribute2]);
        $mockIndexingAttributeRepository = $this->getMockBuilder(IndexingAttributeRepositoryInterface::class)
            ->getMock();
        $mockIndexingAttributeRepository->expects($this->once())
            ->method('getList')
            ->willReturn($mockSearchResult);

        $matcher = $this->exactly(2);
        $mockIndexingAttributeRepository->expects($matcher)
            ->method('save')
            ->willReturnCallback(callback: function () use ($matcher): IndexingAttributeInterface {
                if ($matcher->getInvocationCount() === 1) {
                    throw new \Exception('Could not Save Attribute');
                }
                return $this->objectManager->create(IndexingAttributeInterface::class);
            });

        // phpcs:ignore Generic.Files.LineLength.TooLong
        $setIndexingAttributesToDeleteAction = $this->objectManager->create(SetIndexingAttributesToDeleteActionInterface::class, [
            'indexingAttributeRepository' => $mockIndexingAttributeRepository,
        ]);

        $service = $this->instantiateTestObject([
            'setIndexingAttributesToDeleteAction' => $setIndexingAttributesToDeleteAction,
            'filterAttributesToAddService' => $mockFilterAttributesToAddService,
            'filterAttributesToDeleteService' => $mockFilterAttributesToDeleteService,
            'discoveryProviders' => [
                'products' => $mockProvider,
            ],
            'attributeConflictHandlerService' => $this->getMockAttributeConflictHandlerService(),
        ]);
        $result = $service->execute(attributeTypes: ['KLEVU_PRODUCT']);

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 1, haystack: $messages, message: 'Message Count');
        $this->assertContains(
            needle: 'Indexing attributes (1) failed to save. See log for details.',
            haystack: $messages,
            message: 'Expected Message Exists',
        );
    }

    public function testExecute_SetExistingAttributesToUpdate_WhenAttributeIdsProvided(): void
    {
        $apiKey = 'klevu-api-key';
        $mockProvider = $this->getMockBuilder(AttributeDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->expects($this->exactly(2))
            ->method('getAttributeType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProvider->expects($this->once())
            ->method('getData')
            ->with(
                [$apiKey],
                [1, 2, 3, 4],
            )
            ->willReturn([
                'klevu-api-key' => [
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 1,
                        'attributeCode' => 'klevu_test_attribute_1',
                        'apiKey' => $apiKey,
                        'isIndexable' => true,
                        'klevuAttributeName' => 'name1',
                    ]),
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 2,
                        'attributeCode' => 'klevu_test_attribute_2',
                        'apiKey' => $apiKey,
                        'isIndexable' => false,
                        'klevuAttributeName' => 'name2',
                    ]),
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 3,
                        'attributeCode' => 'klevu_test_attribute_3',
                        'apiKey' => $apiKey,
                        'isIndexable' => false,
                        'klevuAttributeName' => 'name3',
                    ]),
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 4,
                        'attributeCode' => 'klevu_test_attribute_4',
                        'apiKey' => $apiKey,
                        'isIndexable' => true,
                        'klevuAttributeName' => 'name4',
                    ]),
                ],
            ]);

        $mockFilterAttributesToAddService = $this->getMockBuilder(FilterAttributesToAddServiceInterface::class)
            ->getMock();
        $mockFilterAttributesToAddService->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $mockFilterAttributesToDeleteService = $this->getMockBuilder(FilterAttributesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterAttributesToDeleteService->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $attribute1 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute1->setId(1);
        $attribute2 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute2->setId(2);
        $attribute3 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute3->setId(3);
        $attribute4 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute4->setId(4);

        $mockSearchResult = $this->getMockBuilder(IndexingAttributeSearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([$attribute1, $attribute2, $attribute3, $attribute4]);
        $mockIndexingAttributeRepository = $this->getMockBuilder(IndexingAttributeRepositoryInterface::class)
            ->getMock();
        $mockIndexingAttributeRepository->expects($this->once())
            ->method('getList')
            ->willReturn($mockSearchResult);
        $mockIndexingAttributeRepository->expects($this->never())
            ->method('save');

        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => null,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => null,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $setIndexingAttributesToUpdateAction = $this->objectManager->create(
            type: SetIndexingAttributesToUpdateActionInterface::class,
            arguments: [
                'indexingAttributeRepository' => $mockIndexingAttributeRepository,
            ],
        );

        $service = $this->instantiateTestObject([
            'setIndexingAttributesToUpdateAction' => $setIndexingAttributesToUpdateAction,
            'filterAttributesToAddService' => $mockFilterAttributesToAddService,
            'filterAttributesToDeleteService' => $mockFilterAttributesToDeleteService,
            'discoveryProviders' => [
                'products' => $mockProvider,
            ],
            'attributeConflictHandlerService' => $this->getMockAttributeConflictHandlerService(),
        ]);
        $result = $service->execute(
            attributeTypes: ['KLEVU_PRODUCT'],
            apiKeys: [$apiKey],
            attributeIds: [1, 2, 3, 4],
        );

        $this->assertTrue(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertFalse(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 0, haystack: $messages, message: 'Message Count');
    }

    public function testExecute_LogsError_WhenIndexingAttributeSaveExceptionThrown_forUpdate(): void
    {
        $apiKey = 'klevu-api-key';
        $mockProvider = $this->getMockBuilder(AttributeDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->expects($this->exactly(2))
            ->method('getAttributeType')
            ->willReturn('KLEVU_CATEGORY');
        $mockProvider->expects($this->once())
            ->method('getData')
            ->with(
                [$apiKey],
                [1, 2],
            )
            ->willReturn([
                'klevu-api-key' => [
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 1,
                        'attributeCode' => 'klevu_test_attribute_1',
                        'apiKey' => $apiKey,
                        'isIndexable' => true,
                        'klevuAttributeName' => 'name1',
                    ]),
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 2,
                        'attributeCode' => 'klevu_test_attribute_2',
                        'apiKey' => $apiKey,
                        'isIndexable' => false,
                        'klevuAttributeName' => 'name2',
                    ]),
                ],
            ]);

        $mockFilterAttributesToAddService = $this->getMockBuilder(FilterAttributesToAddServiceInterface::class)
            ->getMock();
        $mockFilterAttributesToAddService->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $mockFilterAttributesToDeleteService = $this->getMockBuilder(FilterAttributesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterAttributesToDeleteService->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $attribute1 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute1->setId(1);
        $attribute1->setIsIndexable(true);
        $attribute1->setNextAction(Actions::NO_ACTION);

        $attribute2 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute2->setId(2);
        $attribute2->setIsIndexable(true);
        $attribute2->setNextAction(Actions::DELETE);

        $mockSearchResult = $this->getMockBuilder(IndexingAttributeSearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([$attribute1, $attribute2]);
        $mockIndexingAttributeRepository = $this->getMockBuilder(IndexingAttributeRepositoryInterface::class)
            ->getMock();
        $mockIndexingAttributeRepository->expects($this->once())
            ->method('getList')
            ->willReturn($mockSearchResult);

        $mockIndexingAttributeRepository->expects($this->exactly(2))
            ->method('save')
            ->willThrowException(new \Exception('Some Exception'));

        $setIndexingAttributesToUpdateAction = $this->objectManager->create(
            type: SetIndexingAttributesToUpdateActionInterface::class,
            arguments: [
                'indexingAttributeRepository' => $mockIndexingAttributeRepository,
            ],
        );

        $service = $this->instantiateTestObject([
            'setIndexingAttributesToUpdateAction' => $setIndexingAttributesToUpdateAction,
            'filterAttributesToAddService' => $mockFilterAttributesToAddService,
            'filterAttributesToDeleteService' => $mockFilterAttributesToDeleteService,
            'discoveryProviders' => [
                'category' => $mockProvider,
            ],
            'attributeConflictHandlerService' => $this->getMockAttributeConflictHandlerService(),
        ]);
        $result = $service->execute(
            attributeTypes: ['KLEVU_CATEGORY'],
            apiKeys: [$apiKey],
            attributeIds: [1, 2],
        );

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 1, haystack: $messages, message: 'Message Count');
        $this->assertContains(
            needle: 'Indexing attributes (1, 2) failed to save. See log for details.',
            haystack: $messages,
            message: 'Expected Message Exists',
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_SetExistingAttributesToBeIndexable(): void
    {
        $apiKey = 'klevu-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $mockProvider = $this->getMockBuilder(AttributeDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->expects($this->exactly(2))
            ->method('getAttributeType')
            ->willReturn('KLEVU_CATEGORY');
        $mockProvider->expects($this->once())
            ->method('getData')
            ->with(
                [$apiKey],
                [1, 2],
            )
            ->willReturn([
                'klevu-api-key' => [
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 1,
                        'attributeCode' => 'klevu_test_attribute_1',
                        'apiKey' => $apiKey,
                        'isIndexable' => true,
                        'klevuAttributeName' => 'name1',
                    ]),
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 2,
                        'attributeCode' => 'klevu_test_attribute_2',
                        'apiKey' => $apiKey,
                        'isIndexable' => false,
                        'klevuAttributeName' => 'name2',
                    ]),
                ],
            ]);

        $mockFilterAttributesToAddService = $this->getMockBuilder(FilterAttributesToAddServiceInterface::class)
            ->getMock();
        $mockFilterAttributesToAddService->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $mockFilterAttributesToDeleteService = $this->getMockBuilder(FilterAttributesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterAttributesToDeleteService->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $attribute1 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute1->setId(1);

        $attribute2 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute2->setId(2);

        $mockSearchResult = $this->getMockBuilder(IndexingAttributeSearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([$attribute1, $attribute2]);
        $mockIndexingAttributeRepository = $this->getMockBuilder(IndexingAttributeRepositoryInterface::class)
            ->getMock();
        $mockIndexingAttributeRepository->expects($this->once())
            ->method('getList')
            ->willReturn($mockSearchResult);

        $mockIndexingAttributeRepository->expects($this->exactly(2))
            ->method('save');

        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_3',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => false,
        ]);

        $setIndexingAttributesToBeIndexableAction = $this->objectManager->create(
            type: SetIndexingAttributesToBeIndexableActionInterface::class,
            arguments: [
                'indexingAttributeRepository' => $mockIndexingAttributeRepository,
            ],
        );
        $service = $this->instantiateTestObject([
            'setIndexingAttributesToBeIndexableAction' => $setIndexingAttributesToBeIndexableAction,
            'filterAttributesToAddService' => $mockFilterAttributesToAddService,
            'filterAttributesToDeleteService' => $mockFilterAttributesToDeleteService,
            'discoveryProviders' => [
                'categories' => $mockProvider,
            ],
            'attributeConflictHandlerService' => $this->getMockAttributeConflictHandlerService(),
        ]);
        $result = $service->execute(
            attributeTypes: ['KLEVU_CATEGORY'],
            apiKeys: [$apiKey],
            attributeIds: [1, 2],
        );

        $this->assertTrue(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertFalse(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 0, haystack: $messages, message: 'Message Count');
    }

    public function WhenIndexingAttributeSaveExceptionThrown_forChangeOfIndexableStatus(): void
    {
        $apiKey = 'klevu-api-key';
        $mockProvider = $this->getMockBuilder(AttributeDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->expects($this->exactly(2))
            ->method('getAttributeType')
            ->willReturn('KLEVU_CATEGORY');
        $mockProvider->expects($this->once())
            ->method('getData')
            ->with(
                [$apiKey],
                [1, 2],
            )
            ->willReturn([
                'klevu-api-key' => [
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 1,
                        'attributeCode' => 'klevu_test_attribute_1',
                        'apiKey' => $apiKey,
                        'isIndexable' => true,
                        'klevuAttributeName' => 'name1',
                    ]),
                    $this->objectManager->create(MagentoAttributeInterface::class, [
                        'attributeId' => 2,
                        'attributeCode' => 'klevu_test_attribute_2',
                        'apiKey' => $apiKey,
                        'isIndexable' => false,
                        'klevuAttributeName' => 'name2',
                    ]),
                ],
            ]);

        $mockFilterAttributesToAddService = $this->getMockBuilder(FilterAttributesToAddServiceInterface::class)
            ->getMock();
        $mockFilterAttributesToAddService->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $mockFilterAttributesToDeleteService = $this->getMockBuilder(FilterAttributesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterAttributesToDeleteService->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $attribute1 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute1->setId(1);

        $attribute2 = $this->objectManager->create(IndexingAttributeInterface::class);
        $attribute2->setId(2);

        $mockSearchResult = $this->getMockBuilder(IndexingAttributeSearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([$attribute1, $attribute2]);
        $mockIndexingAttributeRepository = $this->getMockBuilder(IndexingAttributeRepositoryInterface::class)
            ->getMock();
        $mockIndexingAttributeRepository->expects($this->once())
            ->method('getList')
            ->willReturn($mockSearchResult);

        $mockIndexingAttributeRepository->expects($this->exactly(2))
            ->method('save')
            ->willThrowException(new \Exception('Some Exception'));

        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => false,
        ]);

        $setIndexingAttributesToBeIndexableAction = $this->objectManager->create(
            type: SetIndexingAttributesToBeIndexableActionInterface::class,
            arguments: [
                'indexingAttributeRepository' => $mockIndexingAttributeRepository,
            ],
        );
        $service = $this->instantiateTestObject([
            'setIndexingAttributesToBeIndexableAction' => $setIndexingAttributesToBeIndexableAction,
            'filterAttributesToAddService' => $mockFilterAttributesToAddService,
            'filterAttributesToDeleteService' => $mockFilterAttributesToDeleteService,
            'discoveryProviders' => [
                'categories' => $mockProvider,
            ],
            'attributeConflictHandlerService' => $this->getMockAttributeConflictHandlerService(),
        ]);
        $result = $service->execute(
            attributeTypes: ['KLEVU_CATEGORY'],
            apiKeys: [$apiKey],
            attributeIds: [1, 2],
        );

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 1, haystack: $messages, message: 'Message Count');
        $this->assertContains(
            needle: 'Indexing attributes (1, 2) failed to save. See log for details.',
            haystack: $messages,
            message: 'Expected Message Exists',
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_WillMarkAttributeToDelete_WhenAttributeTypeHasChanged(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ALL,
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('test_attribute_1');
        $this->createAttribute([
            'key' => 'test_attribute_2',
            'code' => 'klevu_test_attribute_2',
            'index_as' => IndexType::INDEX,
            'aspect' => Aspect::ALL,
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('test_attribute_2');

        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => $attributeFixture1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture1->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute(data: [
            IndexingAttribute::TARGET_ID => $attributeFixture2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture2->getAttributeCode(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->clearAttributeCache();
        $this->objectManager->removeSharedInstance(
            className: FilterAttributesToDeleteService::class,
        );
        $this->mockSdkAttributeGetApiCall(
            attributes: [
                $attributeFixture1->getAttributeCode() => $this->objectManager->create(
                    type: Attribute::class,
                    arguments: [
                        'attributeName' => $attributeFixture1->getAttributeCode(),
                        'datatype' => DataType::NUMBER->value,
                        'label' => [
                            'default' => 'Label 1',
                        ],
                        'searchable' => false,
                        'filterable' => false,
                        'returnable' => true,
                        'abbreviate' => false,
                        'rangeable' => false,
                        'aliases' => [],
                        'immutable' => false,
                    ],
                ),
                $attributeFixture2->getAttributeCode() => $this->objectManager->create(
                    type: Attribute::class,
                    arguments: [
                        'attributeName' => $attributeFixture2->getAttributeCode(),
                        'datatype' => DataType::STRING->value,
                        'label' => [
                            'default' => 'Label 2',
                        ],
                        'searchable' => false,
                        'filterable' => false,
                        'returnable' => true,
                        'abbreviate' => false,
                        'rangeable' => false,
                        'aliases' => [],
                        'immutable' => false,
                    ],
                ),
            ],
        );

        $service = $this->instantiateTestObject([
            'attributeConflictHandlerService' => $this->getMockAttributeConflictHandlerService(),
        ]);
        $result = $service->execute(attributeTypes: ['KLEVU_PRODUCT']);

        $this->assertTrue(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertFalse(condition: $result->hasMessages(), message: 'Has Messages');

        $indexingAttribute1 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attributeFixture1->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertTrue($indexingAttribute1->getIsIndexable());
        $this->assertSame(expected: Actions::DELETE, actual: $indexingAttribute1->getNextAction());

        $indexingAttribute2 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attributeFixture2->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertTrue($indexingAttribute2->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute2->getNextAction());
    }

    /**
     * @return MockObject&AttributeConflictHandlerServiceInterface
     */
    private function getMockAttributeConflictHandlerService(): MockObject&AttributeConflictHandlerServiceInterface
    {
        /** @var MockObject&AttributeConflictHandlerServiceInterface $mockAttributeConflictHandlerService */
        $mockAttributeConflictHandlerService = $this->getMockBuilder(AttributeConflictHandlerServiceInterface::class)
            ->getMock();
        $mockAttributeConflictHandlerService->expects($this->once())
            ->method('execute');

        return $mockAttributeConflictHandlerService;
    }
}
