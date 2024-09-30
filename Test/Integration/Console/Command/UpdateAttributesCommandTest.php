<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Console\Command;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Console\Command\UpdateAttributesCommand;
use Klevu\IndexingApi\Service\AttributeDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributeDiscoveryProviderInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\AttributeApiCallTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
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
    use AttributeApiCallTrait;
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

        $this->implementationFqcn = UpdateAttributesCommand::class;
        // newrelic-describe-commands globs onto Console commands
        $this->expectPlugins = true;

        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);

        $this->mockSdkAttributeGetApiCall();
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
     */
    public function testExecute_Fails_ForMissingAttributeIds(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-api-key',
            restAuthKey: 'klevu-rest-auth-key',
        );

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
     */
    public function testExecute_Fails_forNonExistentApiKey(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-api-key',
            restAuthKey: 'klevu-rest-auth-key',
        );

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--attribute-ids' => '1,2,3',
                '--api-keys' => 'klevu-api-key-with-no-store',
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
     */
    public function testExecute_Fails_forNonExistentAttributeType(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-api-key',
            restAuthKey: 'klevu-rest-auth-key',
        );

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--attribute-ids' => '1,2,3',
                '--attribute-types' => 'something',
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
     */
    public function testExecute_Succeeds_WithApiKeys(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-api-key-1',
            restAuthKey: 'klevu-rest-auth-key-1',
        );

        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope($storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-api-key-2',
            restAuthKey: 'klevu-rest-auth-key-2',
            removeApiKeys: false,
        );

        $discoverAttributesCommand = $this->instantiateTestObject();
        $tester = new CommandTester(
            command: $discoverAttributesCommand,
        );
        $isFailure = $tester->execute(
            input: [
                '--attribute-ids' => '1,2,3',
                '--api-keys' => 'klevu-js-api-key-1, klevu-js-api-key-2',
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
     */
    public function testExecute_Succeeds_WithAttributeType(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-api-key',
            restAuthKey: 'klevu-rest-auth-key',
        );

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
                '--attribute-ids' => 'all',
                '--attribute-types' => 'KLEVU_PRODUCT',
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
