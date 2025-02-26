<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Console\Command;

use Klevu\Indexing\Console\Command\UpdateEntitiesCommand;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\GeneratorTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Klevu\Indexing\Console\Command\UpdateEntitiesCommand::class
 * @method UpdateEntitiesCommand instantiateTestObject(?array $arguments = null)
 */
class UpdateEntitiesCommandTest extends TestCase
{
    use GeneratorTrait;
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

        $this->implementationFqcn = UpdateEntitiesCommand::class;
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
    public function testExecute_Fails_WithOutEntityIds(): void
    {
        $this->createStore();

        $mockProductDiscoveryProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProductDiscoveryProvider->expects($this->never())
            ->method('getEntityType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProductDiscoveryProvider->expects($this->never())
            ->method('getData');

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
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Update Failed');
        $this->assertStringContainsString(
            needle: 'Begin Entity Update.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Entity IDs are required.',
            haystack: $tester->getDisplay(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testExecute_Fails_forNonExistentApiKey(): void
    {
        $this->createStore();

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--entity-ids' => '1,2,3',
                '--api-keys' => 'klevu-api-key-with-no-store',
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Update Failed');
        $this->assertStringContainsString(
            needle: 'Begin Entity Update.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            '...',
            $tester->getDisplay(),
        );
        $this->assertStringNotMatchesFormat(
            format: '%ADiscover %A to %A Batch %d Completed Successfully.%A',
            string: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Entity Update Completed.',
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

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--entity-ids' => '1,2,3',
                '--entity-types' => 'something',
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Update Failed');
        $this->assertStringContainsString(
            'Begin Entity Update.',
            $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Supplied entity types did not match any providers.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Entity Update Completed.',
            haystack: $tester->getDisplay(),
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-1
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-1
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key-2
     * @magentoConfigFixture klevu_test_store_2_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key-2
     */
    public function testExecute_Succeeds_WithApiKeys(): void
    {
        $this->createStore();
        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--entity-ids' => '1,2,3',
                '--api-keys' => 'klevu-js-api-key-1, klevu-js-api-key-2',
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Update Failed');
        $this->assertStringContainsString(
            needle: 'Begin Entity Update.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: '...',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringMatchesFormat(
            format: '%ADiscover %A to %A Batch %d Completed Successfully.%A',
            string: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Entity Update Completed.',
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

        $mockProductDiscoveryProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProductDiscoveryProvider->method('getEntityType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProductDiscoveryProvider->expects($this->once())
            ->method('getData')
            ->willReturn($this->generate([]));

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
                '--entity-ids' => 'all',
                '--entity-types' => 'KLEVU_PRODUCT',
            ],
            options: [
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Update Failed');
        $this->assertStringContainsString(
            needle: 'Begin Entity Update.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: '...',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Entity Update Completed.',
            haystack: $tester->getDisplay(),
        );
    }
}
