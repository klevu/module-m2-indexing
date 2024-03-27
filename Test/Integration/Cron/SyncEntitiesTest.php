<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Cron;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Cron\SyncEntities;
use Klevu\Indexing\Service\EntityIndexerService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;
use Klevu\IndexingApi\Service\EntitySyncOrchestratorServiceInterface;
use Klevu\PhpSDK\Model\Indexing\Record as SdkIndexingRecord;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\PipelineEntityApiCallTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Cron\Model\Config as CronConfig;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SyncEntitiesTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PipelineEntityApiCallTrait;
    use SetAuthKeysTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = SyncEntities::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
    }

    public function testCrontabIsConfigured(): void
    {
        $cronConfig = $this->objectManager->get(CronConfig::class);
        $cronJobs = $cronConfig->getJobs();

        $this->assertArrayHasKey(key: 'klevu', array: $cronJobs);
        $klevuCronJobs = $cronJobs['klevu'];

        $this->assertArrayHasKey(key: 'klevu_indexing_sync_entities', array: $klevuCronJobs);
        $syncEntityCron = $klevuCronJobs['klevu_indexing_sync_entities'];

        $this->assertSame(expected: SyncEntities::class, actual: $syncEntityCron['instance']);
        $this->assertSame(expected: 'execute', actual: $syncEntityCron['method']);
        $this->assertSame(expected: 'klevu_indexing_sync_entities', actual: $syncEntityCron['name']);
        $this->assertSame(expected: '16,46 * * * *', actual: $syncEntityCron['schedule']);
    }

    public function testExecute_PrintsSuccessMessage_onSuccess(): void
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

        $via = sprintf('Cron: %s', SyncEntities::class);

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

        $syncOrchestrator = $this->objectManager->create(EntitySyncOrchestratorServiceInterface::class, [
            'entityIndexerServices' => [
                'KLEVU_PRODUCT' => [
                    'add' => $mockIndexerService,
                ],
            ],
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Starting sync of entities.'],
                ['Sync of entities for apiKey: klevu-js-api-key, KLEVU_PRODUCT::add batch 0: completed successfully.'],
            );

        $cron = $this->instantiateTestObject([
            'syncOrchestratorService' => $syncOrchestrator,
            'logger' => $mockLogger,
        ]);
        $cron->execute();
    }

    public function testExecute_PrintsFailureMessage_onFailure(): void
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

        $via = sprintf('Cron: %s', SyncEntities::class);

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
            'success' => false,
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

        $syncOrchestrator = $this->objectManager->create(EntitySyncOrchestratorServiceInterface::class, [
            'entityIndexerServices' => [
                'KLEVU_PRODUCT' => [
                    'add' => $mockIndexerService,
                ],
            ],
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Starting sync of entities.'],
                ['Sync of entities for apiKey: klevu-js-api-key, KLEVU_PRODUCT::add batch 0: completed with failures. See logs for more details.'], // phpcs:ignore Generic.Files.LineLength.TooLong
            );

        $cron = $this->instantiateTestObject([
            'syncOrchestratorService' => $syncOrchestrator,
            'logger' => $mockLogger,
        ]);
        $cron->execute();
    }
}
