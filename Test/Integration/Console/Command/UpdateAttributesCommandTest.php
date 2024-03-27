<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Console\Command;

use Klevu\Indexing\Console\Command\UpdateAttributesCommand;
use Klevu\IndexingApi\Service\AttributeDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributeDiscoveryProviderInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Klevu\Indexing\Console\Command\UpdateAttributesCommand::class
 * @method UpdateAttributesCommand instantiateTestObject(?array $arguments = null)
 */
class UpdateAttributesCommandTest extends TestCase
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

        $this->implementationFqcn = UpdateAttributesCommand::class;
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
    public function testExecute_Fails_ForMissingAttributeIds(): void
    {
        $this->createStore();
        $this->storeFixturesPool->get('test_store');

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Update Failed');
        $this->assertStringContainsString(
            needle: 'Begin Attribute Update.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Attribute IDs are required.',
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
        $this->storeFixturesPool->get('test_store');

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--attribute-ids' => '1,2,3',
                '--api-key' => 'klevu-api-key-with-no-store',
            ],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Update Failed');
        $this->assertStringContainsString(
            needle: 'Begin Attribute Update.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Attribute Update Failed. See Logs for more details.',
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
                '--attribute-ids' => '1,2,3',
                '--attribute-type' => 'something',
            ],
        );

        $this->assertSame(expected: 1, actual: $isFailure, message: 'Update Failed');
        $this->assertStringContainsString(
            'Begin Attribute Update.',
            $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Attribute Update Failed. See Logs for more details.',
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
                '--attribute-ids' => '1,2,3',
                '--api-key' => 'klevu-js-api-key',
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Update Failed');
        $this->assertStringContainsString(
            needle: 'Begin Attribute Update.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Attribute Update Completed Successfully.',
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

        $mockProductDiscoveryProvider = $this->getMockBuilder(AttributeDiscoveryProviderInterface::class)
            ->getMock();
        $mockProductDiscoveryProvider->expects($this->exactly(2))
            ->method('getAttributeType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProductDiscoveryProvider->expects($this->once())
            ->method('getData')
            ->willReturn([]);

        $discoveryOrchestrator = $this->objectManager->create(AttributeDiscoveryOrchestratorServiceInterface::class, [
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
                '--attribute-ids' => '1,2,3',
                '--attribute-type' => 'KLEVU_PRODUCT',
            ],
        );

        $this->assertSame(expected: 0, actual: $isFailure, message: 'Update Failed');
        $this->assertStringContainsString(
            needle: 'Begin Attribute Update.',
            haystack: $tester->getDisplay(),
        );
        $this->assertStringContainsString(
            needle: 'Attribute Update Completed Successfully.',
            haystack: $tester->getDisplay(),
        );
    }
}
