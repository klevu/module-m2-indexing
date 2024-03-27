<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Exception\InvalidAttributeIndexerServiceException;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\AttributeSyncOrchestratorService;
use Klevu\Indexing\Service\Indexing\AttributesService as AttributesServiceVirtualType;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\AttributeSyncOrchestratorServiceInterface;
use Klevu\PhpSDK\Api\Model\ApiResponseInterface;
use Klevu\PhpSDK\Api\Service\Indexing\AttributesServiceInterface;
use Klevu\PhpSDK\Service\Indexing\AttributesService;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers AttributeSyncOrchestratorService
 * @method AttributeSyncOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeSyncOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeSyncOrchestratorServiceTest extends TestCase
{
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

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = AttributeSyncOrchestratorService::class;
        $this->interfaceFqcn = AttributeSyncOrchestratorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->objectManager->removeSharedInstance( //@phpstan-ignore-line
            className: AttributesService::class,
        );

        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testConstruct_ThrowsException_ForInvalidAttributeIndexerService(): void
    {
        $this->expectException(InvalidAttributeIndexerServiceException::class);

        $this->instantiateTestObject([
            'attributesIndexerServices' => [
                'KLEVU_PRODUCT' => [
                    'add' => new DataObject(),
                ],
            ],
        ]);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_LogsError_ForInvalidAccountCredentials(): void
    {
        $apiKey = 'k';
        $authKey = 'k';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createAttribute();
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->cleanIndexingAttributes($apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\AttributeSyncOrchestratorService::execute',
                    'message' => sprintf(
                        'Invalid account credentials provided. Check the JS API Key (%s) and Rest Auth Key (%s).',
                        $apiKey,
                        $authKey,
                    ),
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
        ]);
        $service->execute();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_SyncsNewAttribute(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );
        $scopeProvider->unsetCurrentScope();

        $this->mockSdkAttributeServiceSuccess();

        $this->createAttribute();
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->cleanIndexingAttributes(apiKey: $apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute();

        $this->assertArrayHasKey(key: $apiKey, array: $result);
        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::add', array: $result[$apiKey]);
        $this->assertArrayHasKey(
            key: $attributeFixture->getAttributeCode(),
            array: $result[$apiKey]['KLEVU_PRODUCT::add'],
        );
        $response = $result[$apiKey]['KLEVU_PRODUCT::add'][$attributeFixture->getAttributeCode()];
        $this->assertTrue(condition: $response->isSuccess());
        $this->assertEmpty(actual: $response->getMessages());

        $indexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attributeFixture->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );

        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());

        $this->cleanIndexingAttributes($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_SyncsAttributeUpdate(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->mockSdkAttributeServiceSuccess();

        $this->createAttribute();
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute();

        $this->assertArrayHasKey(key: $apiKey, array: $result);
        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::update', array: $result[$apiKey]);
        $this->assertArrayHasKey(
            key: $attributeFixture->getAttributeCode(),
            array: $result[$apiKey]['KLEVU_PRODUCT::update'],
        );
        $response = $result[$apiKey]['KLEVU_PRODUCT::update'][$attributeFixture->getAttributeCode()];
        $this->assertTrue(condition: $response->isSuccess());
        $this->assertEmpty(actual: $response->getMessages());

        $indexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attributeFixture->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );

        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingAttribute->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute->getIsIndexable());

        $this->cleanIndexingAttributes($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_DeletesAttribute(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->mockSdkAttributeServiceSuccess();

        $this->createAttribute();
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->cleanIndexingAttributes($apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute();

        $this->assertArrayHasKey(key: $apiKey, array: $result);
        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::delete', array: $result[$apiKey]);
        $this->assertArrayHasKey(
            key: $attributeFixture->getAttributeCode(),
            array: $result[$apiKey]['KLEVU_PRODUCT::delete'],
        );
        $response = $result[$apiKey]['KLEVU_PRODUCT::delete'][$attributeFixture->getAttributeCode()];
        $this->assertTrue(condition: $response->isSuccess());
        $this->assertEmpty(actual: $response->getMessages());

        $indexingAttribute = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey,
            attribute: $attributeFixture->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );

        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute->getNextAction());
        $this->assertSame(expected: Actions::DELETE, actual: $indexingAttribute->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertFalse(condition: $indexingAttribute->getIsIndexable());

        $this->cleanIndexingAttributes($apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_MultipleAttributes_AllActions_MultipleApiKeys_AllSuccessful(): void
    {
        $apiKey1 = 'klevu-js-api-key-1';
        $authKey1 = 'klevu-rest-auth-key-1';
        $apiKey2 = 'klevu-js-api-key-2';
        $authKey2 = 'klevu-rest-auth-key-2';

        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider1 = $this->objectManager->create(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey1,
            restAuthKey: $authKey1,
        );

        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->create(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope(scope: $storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: $apiKey2,
            restAuthKey: $authKey2,
            removeApiKeys: false,
        );

        $this->mockSdkAttributeServiceSuccess();

        $this->createAttribute([
            'key' => 'test_attribute_1',
            'code' => 'klevu_test_attribute_1',
        ]);
        $attributeFixture1 = $this->attributeFixturePool->get('test_attribute_1');

        $this->createAttribute([
            'key' => 'test_attribute_2',
            'code' => 'klevu_test_attribute_2',
        ]);
        $attributeFixture2 = $this->attributeFixturePool->get('test_attribute_2');

        $this->createAttribute([
            'key' => 'test_attribute_3',
            'code' => 'klevu_test_attribute_3',
        ]);
        $attributeFixture3 = $this->attributeFixturePool->get('test_attribute_3');

        $this->createAttribute([
            'key' => 'test_attribute_4',
            'code' => 'klevu_test_attribute_4',
        ]);
        $attributeFixture4 = $this->attributeFixturePool->get('test_attribute_4');

        $this->cleanIndexingAttributes($apiKey1);
        $this->cleanIndexingAttributes($apiKey2);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture1->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture1->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture1->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture2->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture2->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture2->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture3->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture3->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture3->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture3->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture4->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture4->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey1,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture4->getAttributeId(),
            IndexingAttribute::TARGET_CODE => $attributeFixture4->getAttributeCode(),
            IndexingAttribute::API_KEY => $apiKey2,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute();

        $this->assertArrayHasKey(key: $apiKey1, array: $result);
        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::add', array: $result[$apiKey1]);
        $this->assertArrayHasKey(
            key: $attributeFixture1->getAttributeCode(),
            array: $result[$apiKey1]['KLEVU_PRODUCT::add'],
        );
        $response1_1 = $result[$apiKey1]['KLEVU_PRODUCT::add'][$attributeFixture1->getAttributeCode()];
        $this->assertTrue(condition: $response1_1->isSuccess());
        $this->assertEmpty(actual: $response1_1->getMessages());

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::update', array: $result[$apiKey1]);
        $this->assertArrayHasKey(
            key: $attributeFixture2->getAttributeCode(),
            array: $result[$apiKey1]['KLEVU_PRODUCT::update'],
        );
        $response2_1 = $result[$apiKey1]['KLEVU_PRODUCT::update'][$attributeFixture2->getAttributeCode()];
        $this->assertTrue(condition: $response2_1->isSuccess());
        $this->assertEmpty(actual: $response2_1->getMessages());
        $this->assertArrayNotHasKey(
            key: $attributeFixture3->getAttributeCode(),
            array: $result[$apiKey1]['KLEVU_PRODUCT::update'],
        );

        $this->assertArrayHasKey(key: $apiKey2, array: $result);
        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::add', array: $result[$apiKey2]);
        $this->assertArrayHasKey(
            key: $attributeFixture1->getAttributeCode(),
            array: $result[$apiKey2]['KLEVU_PRODUCT::add'],
        );
        $response1_2 = $result[$apiKey2]['KLEVU_PRODUCT::add'][$attributeFixture1->getAttributeCode()];
        $this->assertTrue(condition: $response1_2->isSuccess());
        $this->assertEmpty(actual: $response1_2->getMessages());
        $this->assertArrayNotHasKey(
            key: $attributeFixture3->getAttributeCode(),
            array: $result[$apiKey2]['KLEVU_PRODUCT::add'],
        );

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::delete', array: $result[$apiKey2]);
        $this->assertArrayHasKey(
            key: $attributeFixture2->getAttributeCode(),
            array: $result[$apiKey2]['KLEVU_PRODUCT::delete'],
        );
        $response2_2 = $result[$apiKey2]['KLEVU_PRODUCT::delete'][$attributeFixture2->getAttributeCode()];
        $this->assertTrue(condition: $response2_2->isSuccess());
        $this->assertEmpty(actual: $response2_2->getMessages());

        $indexingAttribute1_1 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey1,
            attribute: $attributeFixture1->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute1_1->getNextAction());
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute1_1->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute1_1->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute1_1->getIsIndexable());

        $indexingAttribute1_2 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey2,
            attribute: $attributeFixture1->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute1_2->getNextAction());
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute1_2->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute1_2->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute1_2->getIsIndexable());

        $indexingAttribute2_1 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey1,
            attribute: $attributeFixture2->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute2_1->getNextAction());
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingAttribute2_1->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute2_1->getLastActionTimestamp());
        $this->assertTrue(condition: $indexingAttribute2_1->getIsIndexable());

        $indexingAttribute2_2 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey2,
            attribute: $attributeFixture2->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute2_2->getNextAction());
        $this->assertSame(expected: Actions::DELETE, actual: $indexingAttribute2_2->getLastAction());
        $this->assertNotNull(actual: $indexingAttribute2_2->getLastActionTimestamp());
        $this->assertFalse(condition: $indexingAttribute2_2->getIsIndexable());

        $indexingAttribute3_1 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey1,
            attribute: $attributeFixture3->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingAttribute3_1->getNextAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute3_1->getLastAction());
        $this->assertNull(actual: $indexingAttribute3_1->getLastActionTimestamp());
        $this->assertFalse(condition: $indexingAttribute3_1->getIsIndexable());

        $indexingAttribute3_2 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey2,
            attribute: $attributeFixture3->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute3_2->getNextAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute3_2->getLastAction());
        $this->assertNull(actual: $indexingAttribute3_2->getLastActionTimestamp());
        $this->assertFalse(condition: $indexingAttribute3_2->getIsIndexable());

        $indexingAttribute4_1 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey1,
            attribute: $attributeFixture4->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute4_1->getNextAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute4_1->getLastAction());
        $this->assertNull(actual: $indexingAttribute4_1->getLastActionTimestamp());
        $this->AssertTrue(condition: $indexingAttribute4_1->getIsIndexable());

        $indexingAttribute4_2 = $this->getIndexingAttributeForAttribute(
            apiKey: $apiKey2,
            attribute: $attributeFixture4->getAttribute(),
            type: 'KLEVU_PRODUCT',
        );
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute4_2->getNextAction());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute4_2->getLastAction());
        $this->assertNull(actual: $indexingAttribute4_2->getLastActionTimestamp());
        $this->AssertTrue(condition: $indexingAttribute4_2->getIsIndexable());

        $this->cleanIndexingAttributes($apiKey1);
        $this->cleanIndexingAttributes($apiKey2);
    }

    /**
     * @return void
     */
    private function mockSdkAttributeServiceSuccess(): void
    {
        $mockSdkResponse = $this->getMockBuilder(ApiResponseInterface::class)
            ->getMock();
        $mockSdkResponse->method('isSuccess')
            ->willReturn(true);
        $mockSdkResponse->method('getResponseCode')
            ->willReturn(200);
        $mockSdkResponse->method('getMessages')
            ->willReturn([]);

        $mockSdkAttributeService = $this->getMockBuilder(AttributesServiceInterface::class)
            ->getMock();
        $mockSdkAttributeService->method('put')
            ->willReturn($mockSdkResponse);
        $mockSdkAttributeService->method('delete')
            ->willReturn($mockSdkResponse);

        $this->objectManager->addSharedInstance(
            instance: $mockSdkAttributeService,
            className: AttributesService::class,
        );
        $this->objectManager->addSharedInstance(
            instance: $mockSdkAttributeService,
            className: AttributesServiceVirtualType::class, // @phpstan-ignore-line
        );
    }
}
