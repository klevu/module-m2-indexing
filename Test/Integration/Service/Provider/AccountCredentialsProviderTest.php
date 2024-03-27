<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Service\Provider\AccountCredentialsProvider;
use Klevu\IndexingApi\Service\Provider\AccountCredentialsProviderInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers AccountCredentialsProvider
 * @method AccountCredentialsProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AccountCredentialsProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AccountCredentialsProviderTest extends TestCase
{
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

        $this->implementationFqcn = AccountCredentialsProvider::class;
        $this->interfaceFqcn = AccountCredentialsProviderInterface::class;
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

    public function testGet_ReturnsEmptyArray_WhenNoStoresIntegrated(): void
    {
        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertEmpty(actual: $result);
    }

    public function testGet_ReturnsCredentialsForRequestedStoreOnly(): void
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

        $provider = $this->instantiateTestObject();
        $result = $provider->get($storeFixture->get());

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);
        $accountCredentials = $result[$apiKey];
        $this->assertSame(expected: $apiKey, actual: $accountCredentials->jsApiKey);
        $this->assertSame(expected: $authKey, actual: $accountCredentials->restAuthKey);
    }

    public function testGet_ReturnsCredentialsForAllStores(): void
    {
        $apiKey1 = 'klevu-js-api-key-1';
        $authKey1 = 'klevu-rest-auth-key-1';
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

        $apiKey2 = 'klevu-js-api-key-2';
        $authKey2 = 'klevu-rest-auth-key-2';
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

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertCount(expectedCount: 2, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey1, array: $result);
        $accountCredentials = $result[$apiKey1];
        $this->assertSame(expected: $apiKey1, actual: $accountCredentials->jsApiKey);
        $this->assertSame(expected: $authKey1, actual: $accountCredentials->restAuthKey);

        $this->assertArrayHasKey(key: $apiKey2, array: $result);
        $accountCredentials = $result[$apiKey2];
        $this->assertSame(expected: $apiKey2, actual: $accountCredentials->jsApiKey);
        $this->assertSame(expected: $authKey2, actual: $accountCredentials->restAuthKey);
    }

    public function testGet_ReturnsCredentialsOnlyOnce_WhenUsedInMultipleStores(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';
        $this->createStore([
            'key' => 'test_store_1',
            'code' => 'klevu_test_store_1',
        ]);
        $storeFixture1 = $this->storeFixturesPool->get('test_store_1');
        $scopeProvider1 = $this->objectManager->create(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope(scope: $storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
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
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
            removeApiKeys: false,
        );

        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertCount(expectedCount: 1, haystack: $result);

        $this->assertArrayHasKey(key: $apiKey, array: $result);
        $accountCredentials = $result[$apiKey];
        $this->assertSame(expected: $apiKey, actual: $accountCredentials->jsApiKey);
        $this->assertSame(expected: $authKey, actual: $accountCredentials->restAuthKey);
    }

    public function testGet_LogsError_WhenNoSuchEntityExceptionThrown(): void
    {
        $exceptionMessage = 'No such entity found.';

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

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    'method' => 'Klevu\Indexing\Service\Provider\AccountCredentialsProvider::generateAccountCredentials',
                    'message' => $exceptionMessage,
                ],
            );

        $mockApiKeyProvider = $this->getMockBuilder(ApiKeyProviderInterface::class)
            ->getMock();
        $mockApiKeyProvider->expects($this->once())
            ->method('get')
            ->willThrowException(new NoSuchEntityException(__($exceptionMessage)));

        $provider = $this->instantiateTestObject([
            'apiKeyProvider' => $mockApiKeyProvider,
            'logger' => $mockLogger,
        ]);
        $result = $provider->get($storeFixture->get());
        $this->assertEmpty(actual: $result);
    }
}
