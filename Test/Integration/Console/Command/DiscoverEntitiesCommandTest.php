<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Console\Command;

use Klevu\Indexing\Console\Command\DiscoverEntitiesCommand;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Klevu\Indexing\Console\Command\DiscoverEntitiesCommand::class
 * @method DiscoverEntitiesCommand instantiateTestObject(?array $arguments = null)
 */
class DiscoverEntitiesCommandTest extends TestCase
{
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = DiscoverEntitiesCommand::class;
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

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--api-key' => 'klevu-api-key-with-no-store',
            ],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Discovery Failed');
        $this->assertStringContainsString(
            needle: 'Begin Entity Discovery.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Entity Discovery Failed. See Logs for more details.',
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

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--entity-type' => 'something',
            ],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Discovery Failed');
        $this->assertStringContainsString(
            'Begin Entity Discovery.',
            $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Entity Discovery Failed. See Logs for more details.',
            haystack: $tester->getDisplay(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testExecute_Succeeds_WithApiKey(): void
    {
        $this->createStore();
        $this->storeFixturesPool->get('test_store');

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--api-key' => 'klevu-js-api-key',
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Discovery Failed');
        $this->assertStringContainsString(
            needle: 'Begin Entity Discovery.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Entity Discovery Completed Successfully.',
            haystack: $tester->getDisplay(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testExecute_Succeeds_WithAttributeType(): void
    {
        $this->createStore();
        $this->storeFixturesPool->get('test_store');

        $mockProductDiscoveryProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProductDiscoveryProvider->expects($this->exactly(2))
            ->method('getEntityType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProductDiscoveryProvider->expects($this->once())
            ->method('getData')
            ->willReturn([]);

        $discoveryOrchestrator = $this->objectManager->create(EntityDiscoveryOrchestratorServiceInterface::class, [
            'discoveryProviders' => [
                'products' => $mockProductDiscoveryProvider,
            ],
        ]);

        $discoverAttributesCommand = $this->instantiateTestObject([
            'discoveryOrchestratorService' => $discoveryOrchestrator,
        ]);
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--entity-type' => 'KLEVU_PRODUCT',
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Discovery Failed');
        $this->assertStringContainsString(
            needle: 'Begin Entity Discovery.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Entity Discovery Completed Successfully.',
            haystack: $tester->getDisplay(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testExecute_Succeeds_WithOutFilters(): void
    {
        $this->createStore();
        $this->storeFixturesPool->get('test_store');

        $mockProductDiscoveryProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProductDiscoveryProvider->expects($this->once())
            ->method('getEntityType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProductDiscoveryProvider->expects($this->once())
            ->method('getData')
            ->willReturn([]);

        $discoveryOrchestrator = $this->objectManager->create(EntityDiscoveryOrchestratorServiceInterface::class, [
            'discoveryProviders' => [
                'products' => $mockProductDiscoveryProvider,
            ],
        ]);

        $discoverAttributesCommand = $this->instantiateTestObject([
            'discoveryOrchestratorService' => $discoveryOrchestrator,
        ]);
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Discovery Failed');
        $this->assertStringContainsString(
            needle: 'Begin Entity Discovery.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Entity Discovery Completed Successfully.',
            haystack: $tester->getDisplay(),
        );
    }
}
