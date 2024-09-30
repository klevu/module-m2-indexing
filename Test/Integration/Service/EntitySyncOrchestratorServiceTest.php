<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Exception\InvalidEntityIndexerServiceException;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\EntityIndexerService;
use Klevu\Indexing\Service\EntitySyncOrchestratorService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;
use Klevu\IndexingApi\Service\EntitySyncOrchestratorServiceInterface;
use Klevu\PhpSDK\Model\Indexing\Record as SdkIndexingRecord;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\PipelineEntityApiCallTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers EntitySyncOrchestratorService
 * @method EntitySyncOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntitySyncOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntitySyncOrchestratorServiceTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PipelineEntityApiCallTrait;
    use ProductTrait;
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

        $this->implementationFqcn = EntitySyncOrchestratorService::class;
        $this->interfaceFqcn = EntitySyncOrchestratorServiceInterface::class;
        $this->constructorArgumentDefaults = [
            'entityIndexerServices' => [],
        ];
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testConstruct_ThrowsException_ForInvalidAttributeIndexerService(): void
    {
        $this->expectException(InvalidEntityIndexerServiceException::class);

        $this->instantiateTestObject([
            'entityIndexerServices' => [
                'KLEVU_PRODUCT' => [
                    'add' => new DataObject(),
                ],
            ],
        ]);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_LogsError_ForInvalidAccountCredentials(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';

        $this->cleanIndexingEntities($apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $productFixture->getId(),
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Method: {method}, Warning: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\EntitySyncOrchestratorService::getCredentialsArray',
                    'message' => 'No Account found for provided API Keys. '
                        . 'Check the JS API Keys (incorrect-key) provided.',
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'entityIndexerServices' => [],
        ]);
        $service->execute(apiKeys: ['incorrect-key']);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_SyncsNewEntity(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';

        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $via = '\Klevu\Indexing\Test\Integration\Service\EntitySyncOrchestratorServiceTest::testExecute_SyncsNewEntity';

        $record = $this->objectManager->create(SdkIndexingRecord::class, [
            'id' => '1',
            'type' => 'KLEVU_PRODUCT',
            'relations' => [
                'categories' => [
                    'values' => ['1', '2'],
                ],
            ],
            'attributes' => [
                'sku' => 'TEST_SKU_001',
                'name' => [
                    'default' => 'Test Product',
                ],
                'description' => [
                    'default' => 'Test Product Description',
                ],
                'shortDescription' => [
                    'default' => 'Test Product Short Description',
                ],
                'url' => 'https://klevu.com',
                'inStock' => '1',
                'rating' => '1234',
                'ratingCount' => '345',
                'visibility' => [
                    'search',
                    'catalog',
                ],
                'prices' => [
                    '0' => [
                        'amount' => '99.99',
                        'currency' => 'GBP',
                        'type' => 'defaultPrice',
                    ],
                    '1' => [
                        'amount' => '74.99',
                        'currency' => 'GBP',
                        'type' => 'salePrice',
                    ],
                ],
                'images' => [
                    '0' => [
                        'url' => 'https://klevu.com/image',
                        'type' => 'default',
                        'height' => '200',
                        'width' => '300',
                    ],
                ],
            ],
            'display' => [
                'some_attribute' => 'some text',
            ],
        ]);
        $recordIterator = $this->objectManager->create(RecordIterator::class, [
            'data' => [
                $record,
            ],
        ]);

        $mockPipelineResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'messages' => [],
            'payload' => $recordIterator,
        ]);

        $mockIndexerResponse = $this->getMockBuilder(IndexerResultInterface::class)
            ->getMock();
        $mockIndexerResponse->expects($this->once())
            ->method('getStatus')
            ->willReturn(IndexerResultStatuses::SUCCESS);
        $mockIndexerResponse->expects($this->once())
            ->method('getMessages')
            ->willReturn([]);
        $mockIndexerResponse->expects($this->once())
            ->method('getPipelineResult')
            ->willReturn([$mockPipelineResult]);

        $mockIndexerService = $this->getMockBuilder(EntityIndexerService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIndexerService->expects($this->once())
            ->method('execute')
            ->with($apiKey, $via)
            ->willReturn($mockIndexerResponse);

        $service = $this->instantiateTestObject([
            'entityIndexerServices' => [
                'KLEVU_PRODUCT' => [
                    'add' => $mockIndexerService,
                ],
            ],
        ]);
        $result = $service->execute(
            entityTypes: ['KLEVU_PRODUCT'],
            apiKeys: [$apiKey],
            via: $via,
        );

        $this->assertArrayHasKey(key: $apiKey, array: $result);

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        /** @var IndexerResultInterface $integration1 */
        $integration1 = $result[$apiKey];
        $pipelineResults = $integration1->getPipelineResult();
        $this->assertCount(expectedCount: 1, haystack: $pipelineResults);

        $this->assertArrayNotHasKey(key: 'KLEVU_PRODUCT::delete', array: $pipelineResults);
        $this->assertArrayNotHasKey(key: 'KLEVU_PRODUCT::update', array: $pipelineResults);
        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT::add', array: $pipelineResults);
        $addResponses = $pipelineResults['KLEVU_PRODUCT::add'];
        $this->assertCount(expectedCount: 1, haystack: $addResponses);

        /** @var ApiPipelineResult $pipelineResult */
        $pipelineResult = array_shift($addResponses);

        $this->assertTrue(condition: $pipelineResult->success);
        $this->assertCount(expectedCount: 0, haystack: $pipelineResult->messages);

        /** @var RecordIterator $payload */
        $payload = $pipelineResult->payload;
        $this->assertCount(expectedCount: 1, haystack: $payload);
        $record = $payload->current();

        $this->assertSame(
            expected: '1',
            actual: $record->getId(),
            message: 'Record ID: ' . $record->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $record->getType(),
            message: 'Record Type: ' . $record->getType(),
        );

        $relations = $record->getRelations();
        $this->assertArrayHasKey(key: 'categories', array: $relations);
        $categories = $relations['categories'];

        $this->assertArrayHasKey(key: 'values', array: $categories);
        $this->assertContains(needle: '1', haystack: $categories['values']);
        $this->assertContains(needle: '2', haystack: $categories['values']);

        $attributes = $record->getAttributes();
        $this->assertArrayHasKey(key: 'sku', array: $attributes);
        $this->assertSame(
            expected: 'TEST_SKU_001',
            actual: $attributes['sku'],
            message: 'SKU: ' . $attributes['sku'],
        );

        $this->assertArrayHasKey(key: 'name', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['name']);
        $this->assertSame(
            expected: 'Test Product',
            actual: $attributes['name']['default'],
            message: 'Name: ' . $attributes['name']['default'],
        );

        $this->assertArrayHasKey(key: 'shortDescription', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['shortDescription']);
        $this->assertSame(
            expected: 'Test Product Short Description',
            actual: $attributes['shortDescription']['default'],
            message: 'Short Description: ' . $attributes['shortDescription']['default'],
        );

        $this->assertArrayHasKey(key: 'description', array: $attributes);
        $this->assertArrayHasKey(key: 'default', array: $attributes['description']);
        $this->assertSame(
            expected: 'Test Product Description',
            actual: $attributes['description']['default'],
            message: 'Description: ' . $attributes['description']['default'],
        );

        $this->assertArrayHasKey(key: 'visibility', array: $attributes);
        $this->assertContains(needle: 'catalog', haystack: $attributes['visibility']);
        $this->assertContains(needle: 'search', haystack: $attributes['visibility']);

        $this->assertArrayHasKey(key: 'inStock', array: $attributes);
        $this->assertSame(expected: '1', actual: $attributes['inStock']);

        $this->assertArrayHasKey(key: 'url', array: $attributes);
        $this->assertSame(expected: 'https://klevu.com', actual: $attributes['url']);

        $this->assertArrayHasKey(key: 'rating', array: $attributes);
        $this->assertSame(expected: '1234', actual: $attributes['rating']);

        $this->assertArrayHasKey(key: 'ratingCount', array: $attributes);
        $this->assertSame(expected: '345', actual: $attributes['ratingCount']);

        $this->assertArrayHasKey(key: 'prices', array: $attributes);
        $defaultPrice = $attributes['prices']['0'];
        $this->assertSame(expected: '99.99', actual: $defaultPrice['amount']);
        $this->assertSame(expected: 'GBP', actual: $defaultPrice['currency']);
        $salePrice = $attributes['prices']['1'];
        $this->assertSame(expected: '74.99', actual: $salePrice['amount']);
        $this->assertSame(expected: 'GBP', actual: $salePrice['currency']);

        $this->assertArrayHasKey(key: 'images', array: $attributes);
        $defaultImage = $attributes['images']['0'];
        $this->assertSame(expected: 'https://klevu.com/image', actual: $defaultImage['url'] ?? null);
        $this->assertSame(expected: 'default', actual: $defaultImage['type'] ?? null);
        $this->assertSame(expected: '200', actual: $defaultImage['height'] ?? null);
        $this->assertSame(expected: '300', actual: $defaultImage['width'] ?? null);

        $this->cleanIndexingEntities($apiKey);
    }
}
