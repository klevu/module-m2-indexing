<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Console\Command;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Console\Command\SyncEntitiesCommand;
use Klevu\Indexing\Service\EntityIndexerService;
use Klevu\Indexing\Service\EntitySyncOrchestratorService;
use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;
use Klevu\PhpSDK\Model\Indexing\Record as SdkIndexingRecord;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Framework\Console\Cli;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Klevu\Indexing\Console\Command\SyncEntitiesCommand::class
 * @method SyncEntitiesCommand instantiateTestObject(?array $arguments = null)
 */
class SyncEntitiesCommandTest extends TestCase
{
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;

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

        $this->implementationFqcn = SyncEntitiesCommand::class;
        // newrelic-describe-commands globs onto Console commands
        $this->expectPlugins = true;

        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testExecute_forNonExistentApiKey(): void
    {
        $this->createStore();

        $syncEntitiesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $syncEntitiesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--api-keys' => 'klevu-api-key-with-no-store',
            ],
        );

        $this->assertSame(expected: Cli::RETURN_SUCCESS, actual: $isFailure, message: 'Sync Success');
        $this->assertStringContainsString(
            needle: 'Begin Entity Sync with filters: API Keys = klevu-api-key-with-no-store.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'No entities were found that require syncing.',
            haystack: $tester->getDisplay(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testExecute_forNonExistentAttributeType(): void
    {
        $this->createStore();

        $syncEntitiesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $syncEntitiesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--entity-types' => 'something',
            ],
        );

        $this->assertSame(expected: Cli::RETURN_SUCCESS, actual: $isFailure, message: 'Sync Success');
        $this->assertStringContainsString(
            'Begin Entity Sync with filters: Entity Types = something.',
            $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'No entities were found that require syncing.',
            haystack: $tester->getDisplay(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_Succeeds_WithApiKeyAndEntityType(): void
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

        $mockIndexerResponseNoop = $this->getMockBuilder(IndexerResultInterface::class)
            ->getMock();
        $mockIndexerResponseNoop->expects($this->exactly(2))
            ->method('getStatus')
            ->willReturn(IndexerResultStatuses::NOOP);
        $mockIndexerResponseNoop->expects($this->exactly(2))
            ->method('getMessages')
            ->willReturn([]);
        $mockIndexerResponseNoop->expects($this->exactly(2))
            ->method('getPipelineResult')
            ->willReturn([]);

        $mockIndexerResponseSuccess = $this->getMockBuilder(IndexerResultInterface::class)
            ->getMock();
        $mockIndexerResponseSuccess->expects($this->once())
            ->method('getStatus')
            ->willReturn(IndexerResultStatuses::SUCCESS);
        $mockIndexerResponseSuccess->expects($this->once())
            ->method('getMessages')
            ->willReturn([]);
        $mockIndexerResponseSuccess->expects($this->once())
            ->method('getPipelineResult')
            ->willReturn([$mockPipelineResult]);

        $mockIndexerService = $this->getMockBuilder(EntityIndexerService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIndexerService->expects($this->once())
            ->method('execute')
            ->with($apiKey, 'CLI::klevu:indexing:entity-sync')
            ->willReturn($mockIndexerResponseSuccess);

        $mockIndexerServiceNoop = $this->getMockBuilder(EntityIndexerService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIndexerServiceNoop->expects($this->exactly(2))
            ->method('execute')
            ->with($apiKey, 'CLI::klevu:indexing:entity-sync')
            ->willReturn($mockIndexerResponseNoop);

        $mockIndexerServiceNotCalled = $this->getMockBuilder(EntityIndexerService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIndexerServiceNotCalled->expects($this->never())
            ->method('execute');

        $syncOrchestrator = $this->objectManager->create(EntitySyncOrchestratorService::class, [
            'entityIndexerServices' => [
                'KLEVU_PRODUCT' => [
                    'add' => $mockIndexerService,
                    'delete' => $mockIndexerServiceNoop,
                    'update' => $mockIndexerServiceNoop,
                ],
                'KLEVU_CATEGORY' => [
                    'add' => $mockIndexerServiceNotCalled,
                    'delete' => $mockIndexerServiceNotCalled,
                    'update' => $mockIndexerServiceNotCalled,
                ],
                'KLEVU_CMS' => [
                    'add' => $mockIndexerServiceNotCalled,
                    'delete' => $mockIndexerServiceNotCalled,
                    'update' => $mockIndexerServiceNotCalled,
                ],
            ],
        ]);

        $syncEntitiesCommand = $this->instantiateTestObject([
            'syncOrchestratorService' => $syncOrchestrator,
        ]);

        $tester = new CommandTester(
            command: $syncEntitiesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--api-keys' => 'klevu-js-api-key',
                '--entity-types' => 'KLEVU_PRODUCT',
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: Cli::RETURN_SUCCESS, actual: $isFailure, message: 'Entity Sync Successful');

        $display = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: sprintf('Begin Entity Sync with filters: Entity Types = KLEVU_PRODUCT, API Keys = %s', $apiKey),
            haystack: $display,
        );
        $this->assertStringContainsString(
            needle: sprintf('Entity Sync for API Key: %s.', $apiKey),
            haystack: $display,
        );

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::add'
            . '\s*Batches : 1'
            . '\s*Batch        : 0'
            . '\s*Success      : True'
            . '\s*API Response : '
            . '\s*Job ID       : n/a'
            . '\s*Record Count : 1'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::add batches');

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::delete'
            . '\s*Batches : 0'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::delete batches');

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::update'
            . '\s*Batches : 0'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::update batches');

        $this->assertStringContainsString(
            needle: 'Entity sync command completed successfully.',
            haystack: $display,
        );

        $matches = [];
        preg_match(
            pattern: '#Sync operations complete in .* seconds.#',
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'Time taken is displayed');
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_Fails_WithApiKeyAndEntityType(): void
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
            'messages' => ['There has been an ERROR'],
            'payload' => $recordIterator,
        ]);

        $mockIndexerResponseNoop = $this->getMockBuilder(IndexerResultInterface::class)
            ->getMock();
        $mockIndexerResponseNoop->expects($this->exactly(2))
            ->method('getStatus')
            ->willReturn(IndexerResultStatuses::NOOP);
        $mockIndexerResponseNoop->expects($this->exactly(2))
            ->method('getMessages')
            ->willReturn([]);
        $mockIndexerResponseNoop->expects($this->exactly(2))
            ->method('getPipelineResult')
            ->willReturn([]);

        $mockIndexerResponseFailure = $this->getMockBuilder(IndexerResultInterface::class)
            ->getMock();
        $mockIndexerResponseFailure->expects($this->once())
            ->method('getStatus')
            ->willReturn(IndexerResultStatuses::ERROR);
        $mockIndexerResponseFailure->expects($this->once())
            ->method('getMessages')
            ->willReturn(['An exception was thrown']);
        $mockIndexerResponseFailure->expects($this->once())
            ->method('getPipelineResult')
            ->willReturn([$mockPipelineResult]);

        $mockIndexerService = $this->getMockBuilder(EntityIndexerService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIndexerService->expects($this->once())
            ->method('execute')
            ->with($apiKey, 'CLI::klevu:indexing:entity-sync')
            ->willReturn($mockIndexerResponseFailure);

        $mockIndexerServiceNoop = $this->getMockBuilder(EntityIndexerService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIndexerServiceNoop->expects($this->exactly(2))
            ->method('execute')
            ->with($apiKey, 'CLI::klevu:indexing:entity-sync')
            ->willReturn($mockIndexerResponseNoop);

        $mockIndexerServiceNotCalled = $this->getMockBuilder(EntityIndexerService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIndexerServiceNotCalled->expects($this->never())
            ->method('execute');

        $syncOrchestrator = $this->objectManager->create(EntitySyncOrchestratorService::class, [
            'entityIndexerServices' => [
                'KLEVU_PRODUCT' => [
                    'add' => $mockIndexerService,
                    'delete' => $mockIndexerServiceNoop,
                    'update' => $mockIndexerServiceNoop,
                ],
                'KLEVU_CATEGORY' => [
                    'add' => $mockIndexerServiceNotCalled,
                    'delete' => $mockIndexerServiceNotCalled,
                    'update' => $mockIndexerServiceNotCalled,
                ],
                'KLEVU_CMS' => [
                    'add' => $mockIndexerServiceNotCalled,
                    'delete' => $mockIndexerServiceNotCalled,
                    'update' => $mockIndexerServiceNotCalled,
                ],
            ],
        ]);

        $syncEntitiesCommand = $this->instantiateTestObject([
            'syncOrchestratorService' => $syncOrchestrator,
        ]);

        $tester = new CommandTester(
            command: $syncEntitiesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--api-keys' => 'klevu-js-api-key',
                '--entity-types' => 'KLEVU_PRODUCT',
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: Cli::RETURN_FAILURE, actual: $isFailure, message: 'Entity Sync Failure');

        $display = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: sprintf('Begin Entity Sync with filters: Entity Types = KLEVU_PRODUCT, API Keys = %s', $apiKey),
            haystack: $display,
        );
        $this->assertStringContainsString(
            needle: sprintf('Entity Sync for API Key: %s.', $apiKey),
            haystack: $display,
        );
        $this->assertStringContainsString(
            needle: 'An exception was thrown',
            haystack: $display,
        );

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::add'
            . '\s*Batches : 1'
            . '\s*Batch        : 0'
            . '\s*Success      : False'
            . '\s*API Response : .*'
            . '\s*Job ID       : n/a'
            . '\s*Record Count : 1'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::add batches');

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::delete'
            . '\s*Batches : 0'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::delete batches');

        $pattern = '#'
            . 'Action  : KLEVU_PRODUCT::update'
            . '\s*Batches : 0'
            . '\s*--'
            . '#';
        $matches = [];
        preg_match(
            pattern: $pattern,
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'KLEVU_PRODUCT::update batches');

        $this->assertStringContainsString(
            needle: 'All or part of Entity Sync Failed. See Logs for more details.',
            haystack: $display,
        );

        $matches = [];
        preg_match(
            pattern: '#Sync operations complete in .* seconds.#',
            subject: $display,
            matches: $matches,
        );
        $this->assertCount(1, $matches, 'Time taken is displayed');
    }
}
