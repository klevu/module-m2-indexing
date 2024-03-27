<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Console\Command;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Console\Command\SyncAttributesCommand;
use Klevu\IndexingApi\Api\Data\SyncResultInterface;
use Klevu\IndexingApi\Service\AttributeIndexerServiceInterface;
use Klevu\IndexingApi\Service\AttributeSyncOrchestratorServiceInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Klevu\Indexing\Console\Command\SyncAttributesCommand::class
 * @method SyncAttributesCommand instantiateTestObject(?array $arguments = null)
 */
class SyncAttributesCommandTest extends TestCase
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

        $this->implementationFqcn = SyncAttributesCommand::class;
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
    public function testExecute_Fails_forNonExistentApiKey(): void
    {
        $this->createStore();
        $this->storeFixturesPool->get('test_store');

        $syncAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $syncAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--api-key' => 'klevu-api-key-with-no-store',
            ],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Sync Failed');
        $this->assertStringContainsString(
            needle: 'Begin Attribute Sync',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'No attributes were found that require syncing.',
            haystack: $tester->getDisplay(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testExecute_Fails_forNonExistentAttributeType(): void
    {
        $this->createStore();
        $this->storeFixturesPool->get('test_store');

        $syncAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $syncAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--attribute-type' => 'something',
            ],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Sync Failed');
        $this->assertStringContainsString(
            'Begin Attribute Sync',
            $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'No attributes were found that require syncing.',
            haystack: $tester->getDisplay(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_Succeeds_WithApiKey(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';
        $this->createStore();
        $storeFixture1 = $this->storeFixturesPool->get('test_store');
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $mockSyncResult = $this->getMockBuilder(SyncResultInterface::class)
            ->getMock();
        $mockSyncResult->method('isSuccess')
            ->willReturn(true);
        $mockSyncResult->method('getMessages')
            ->willReturn([]);

        $indexerService = $this->getMockBuilder(AttributeIndexerServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $indexerService->expects($this->once())
            ->method('execute')
            ->willReturn([
                'attribute-code' => $mockSyncResult,
            ]);

        $syncOrchestrator = $this->objectManager->create(AttributeSyncOrchestratorServiceInterface::class, [
            'attributesIndexerServices' => [
                'KLEVU_PRODUCT' => [
                    'add' => $indexerService,
                ],
            ],
        ]);

        $syncAttributesCommand = $this->instantiateTestObject([
            'syncOrchestratorService' => $syncOrchestrator,
        ]);

        $tester = new CommandTester(
            command: $syncAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--api-key' => 'klevu-js-api-key',
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Sync Failed');
        $output = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: 'Begin Attribute Sync',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: sprintf('Attribute Sync for API Key: %s.', $apiKey),
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: 'Attribute Sync for Action: KLEVU_PRODUCT::add.',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: 'Attribute Sync for Attribute: "attribute-code" Completed Successfully.',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: 'Attribute Sync Completed Successfully.',
            haystack: $output,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_Succeeds_WithAttributeType(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';
        $this->createStore();
        $storeFixture1 = $this->storeFixturesPool->get('test_store');
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $mockSyncResult = $this->getMockBuilder(SyncResultInterface::class)
            ->getMock();
        $mockSyncResult->method('isSuccess')
            ->willReturn(true);
        $mockSyncResult->method('getMessages')
            ->willReturn([]);

        $indexerService = $this->getMockBuilder(AttributeIndexerServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $indexerService->expects($this->once())
            ->method('execute')
            ->willReturn([
                'attribute-code' => $mockSyncResult,
            ]);

        $syncOrchestrator = $this->objectManager->create(AttributeSyncOrchestratorServiceInterface::class, [
            'attributesIndexerServices' => [
                'KLEVU_CATEGORY' => [
                    'update' => $indexerService,
                ],
            ],
        ]);

        $syncAttributesCommand = $this->instantiateTestObject([
            'syncOrchestratorService' => $syncOrchestrator,
        ]);
        $tester = new CommandTester(
            command: $syncAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--attribute-type' => 'KLEVU_CATEGORY',
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Update Failed');

        $output = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: 'Begin Attribute Sync',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: sprintf('Attribute Sync for API Key: %s.', $apiKey),
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: 'Attribute Sync for Action: KLEVU_CATEGORY::update.',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: 'Attribute Sync for Attribute: "attribute-code" Completed Successfully.',
            haystack: $output,
        );
        $this->assertStringContainsString(
            needle: 'Attribute Sync Completed Successfully.',
            haystack: $output,
        );
    }
}
