<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Tests\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Cache\Attributes as AttributesCache;
use Klevu\Indexing\Exception\StoreApiKeyException;
use Klevu\Indexing\Service\Provider\StandardAttributesProvider;
use Klevu\IndexingApi\Service\Provider\Sdk\AttributesProviderInterface;
use Klevu\IndexingApi\Service\Provider\StandardAttributesProviderInterface;
use Klevu\PhpSDK\Exception\Api\BadRequestException;
use Klevu\PhpSDK\Exception\Api\BadResponseException;
use Klevu\PhpSDK\Model\AccountCredentials;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\AttributeIterator;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\PhpSDK\Service\Indexing\AttributesService;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers StandardAttributesProvider::class
 * @runTestsInSeparateProcesses
 */
class StandardAttributesProviderTest extends TestCase
{
    use SetAuthKeysTrait;
    use StoreTrait;

    /**
     * @var string|null
     */
    private ?string $implementationFqcn = null;
    /**
     * @var string|null
     */
    private ?string $interfaceFqcn = null;
    /**
     * @var mixed[]|null
     */
    private ?array $constructorArgumentDefaults = null;
    /**
     * @var string|null
     */
    private ?string $implementationForVirtualType = null;
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

        $this->implementationFqcn = StandardAttributesProvider::class;
        $this->interfaceFqcn = StandardAttributesProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->clearAttributesCache();
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

    public function testGet_throwsException_ApiKeyDoesNotExistInMagento(): void
    {
        $this->expectException(StoreApiKeyException::class);
        $this->expectExceptionMessage('API key "klevu-js-key" not integrated with any store.');

        $jsApiKey = 'klevu-js-key';
        $this->createStore();

        $provider = $this->instantiateTestObject();
        $provider->get(apiKey: $jsApiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_throwsException_ApiKeyDoesNotExistInKlevu(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Authentication failed. Please ensure your credentials are valid and try again.');

        $jsApiKey = 'klevu-123456789';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $jsApiKey,
            restAuthKey: 'ABCDEFGHI123456780',
        );

        $provider = $this->instantiateTestObject();
        $provider->get(apiKey: $jsApiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_throwsException_OnApiResponseFailure(): void
    {
        $this->expectException(BadResponseException::class);
        $this->expectExceptionMessage('Something went wrong');

        $jsApiKey = 'klevu-js-key';
        $restAuthKey = 'klevu-rest-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $jsApiKey,
            restAuthKey: $restAuthKey,
        );

        $accountCredentials = new AccountCredentials(
            jsApiKey: $jsApiKey,
            restAuthKey: $restAuthKey,
        );

        $mockAttributesService = $this->getMockBuilder(AttributesService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributesService->expects($this->once())
            ->method('get')
            ->with($accountCredentials)
            ->willThrowException(
                new BadResponseException('Something went wrong', 500),
            );

        $attributesProvider = $this->objectManager->create(AttributesProviderInterface::class, [
            'attributesService' => $mockAttributesService,
        ]);

        $provider = $this->instantiateTestObject([
            'attributesProvider' => $attributesProvider,
        ]);
        $provider->get(apiKey: $jsApiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_throwsException_OnApiRequestFailure(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Malformed request');

        $jsApiKey = 'klevu-js-key';
        $restAuthKey = 'klevu-rest-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $jsApiKey,
            restAuthKey: $restAuthKey,
        );

        $accountCredentials = new AccountCredentials(
            jsApiKey: $jsApiKey,
            restAuthKey: $restAuthKey,
        );

        $mockAttributesService = $this->getMockBuilder(AttributesService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributesService->expects($this->once())
            ->method('get')
            ->with($accountCredentials)
            ->willThrowException(
                new BadRequestException('Malformed request', 400),
            );

        $attributesProvider = $this->objectManager->create(AttributesProviderInterface::class, [
            'attributesService' => $mockAttributesService,
        ]);

        $provider = $this->instantiateTestObject([
            'attributesProvider' => $attributesProvider,
        ]);
        $provider->get(apiKey: $jsApiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_OnlyReturnsImmutableAttributes(): void
    {
        $jsApiKey = 'klevu-js-key';
        $restAuthKey = 'klevu-rest-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $jsApiKey,
            restAuthKey: $restAuthKey,
        );

        $accountCredentials = new AccountCredentials(
            jsApiKey: $jsApiKey,
            restAuthKey: $restAuthKey,
        );

        $attributeIterator = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'description',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [
                        'desc',
                    ],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'name',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'sku',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_custom_attribute',
                    'datatype' => DataType::NUMBER->value,
                    'searchable' => false,
                    'filterable' => true,
                    'returnable' => false,
                    'aliases' => [],
                    'immutable' => false,
                ],
            ),
        );

        $mockAttributesService = $this->getMockBuilder(AttributesService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributesService->expects($this->once())
            ->method('get')
            ->with($accountCredentials)
            ->willReturn($attributeIterator);

        $attributesProvider = $this->objectManager->create(AttributesProviderInterface::class, [
            'attributesService' => $mockAttributesService,
        ]);

        $provider = $this->instantiateTestObject([
            'attributesProvider' => $attributesProvider,
        ]);
        $results = $provider->get(apiKey: $jsApiKey);

        $attributeCodes = [];
        foreach ($results as $attribute) {
            $attributeCodes[] = $attribute->getAttributeName();
        }

        $this->assertContains(needle: 'description', haystack: $attributeCodes);
        $this->assertContains(needle: 'name', haystack: $attributeCodes);
        $this->assertContains(needle: 'sku', haystack: $attributeCodes);
        $this->assertNotContains(needle: 'my_custom_attribute', haystack: $attributeCodes);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetAttributeCodes_OnlyReturnsImmutableAttributeCodes(): void
    {
        $jsApiKey = 'klevu-js-key';
        $restAuthKey = 'klevu-rest-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $jsApiKey,
            restAuthKey: $restAuthKey,
        );

        $accountCredentials = new AccountCredentials(
            jsApiKey: $jsApiKey,
            restAuthKey: $restAuthKey,
        );

        $attributeIterator = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'description',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [
                        'desc',
                    ],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'name',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'sku',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_custom_attribute',
                    'datatype' => DataType::NUMBER->value,
                    'searchable' => false,
                    'filterable' => true,
                    'returnable' => false,
                    'aliases' => [],
                    'immutable' => false,
                ],
            ),
        );

        $mockAttributesService = $this->getMockBuilder(AttributesService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributesService->expects($this->once())
            ->method('get')
            ->with($accountCredentials)
            ->willReturn($attributeIterator);

        $attributesProvider = $this->objectManager->create(AttributesProviderInterface::class, [
            'attributesService' => $mockAttributesService,
        ]);

        $provider = $this->instantiateTestObject([
            'attributesProvider' => $attributesProvider,
        ]);
        $results = $provider->getAttributeCodes(apiKey: $jsApiKey, includeAliases: false);

        $this->assertContains(needle: 'description', haystack: $results);
        $this->assertNotContains(needle: 'desc', haystack: $results);
        $this->assertContains(needle: 'name', haystack: $results);
        $this->assertContains(needle: 'sku', haystack: $results);
        $this->assertNotContains(needle: 'my_custom_attribute', haystack: $results);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetAttributeCodes_OnlyReturnsImmutableAttributeCodes_IncludeAliases(): void
    {
        $jsApiKey = 'klevu-js-key';
        $restAuthKey = 'klevu-rest-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $jsApiKey,
            restAuthKey: $restAuthKey,
        );

        $accountCredentials = new AccountCredentials(
            jsApiKey: $jsApiKey,
            restAuthKey: $restAuthKey,
        );

        $attributeIterator = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'description',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [
                        'desc',
                    ],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'name',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'sku',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_custom_attribute',
                    'datatype' => DataType::NUMBER->value,
                    'searchable' => false,
                    'filterable' => true,
                    'returnable' => false,
                    'aliases' => [],
                    'immutable' => false,
                ],
            ),
        );

        $mockAttributesService = $this->getMockBuilder(AttributesService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributesService->expects($this->once())
            ->method('get')
            ->with($accountCredentials)
            ->willReturn($attributeIterator);

        $attributesProvider = $this->objectManager->create(AttributesProviderInterface::class, [
            'attributesService' => $mockAttributesService,
        ]);

        $provider = $this->instantiateTestObject([
            'attributesProvider' => $attributesProvider,
        ]);
        $results = $provider->getAttributeCodes(apiKey: $jsApiKey, includeAliases: true);

        $this->assertContains(needle: 'desc', haystack: $results);
        $this->assertContains(needle: 'description', haystack: $results);
        $this->assertContains(needle: 'name', haystack: $results);
        $this->assertContains(needle: 'sku', haystack: $results);
        $this->assertNotContains(needle: 'my_custom_attribute', haystack: $results);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetForAllApiKeys(): void
    {
        $jsApiKey1 = 'klevu-js-key-1';
        $restAuthKey1 = 'klevu-rest-key-1';
        $this->createStore();
        $storeFixture1 = $this->storeFixturesPool->get('test_store');
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope($storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $jsApiKey1,
            restAuthKey: $restAuthKey1,
        );
        $accountCredentials1 = new AccountCredentials(
            jsApiKey: $jsApiKey1,
            restAuthKey: $restAuthKey1,
        );

        $jsApiKey2 = 'klevu-js-key-2';
        $restAuthKey2 = 'klevu-rest-key-2';
        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope($storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: $jsApiKey2,
            restAuthKey: $restAuthKey2,
            removeApiKeys: false,
        );
        $accountCredentials2 = new AccountCredentials(
            jsApiKey: $jsApiKey2,
            restAuthKey: $restAuthKey2,
        );

        $attributeIterator1 = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator1->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_1',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [
                        'standard_att',
                    ],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator1->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_2',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator2 = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator2->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_3',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator2->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_custom_attribute_4',
                    'datatype' => DataType::NUMBER->value,
                    'searchable' => false,
                    'filterable' => true,
                    'returnable' => false,
                    'immutable' => false,
                ],
            ),
        );

        $mockAttributesService = $this->getMockBuilder(AttributesService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $expectation = $this->atLeast(2);
        $mockAttributesService->expects($expectation)
            ->method('get')
            ->willReturnCallBack(
                callback: function (AccountCredentials $accountCredentials) use ($expectation, $accountCredentials1, $accountCredentials2, $attributeIterator1, $attributeIterator2): AttributeIterator { // phpcs:ignore Generic.Files.LineLength.TooLong
                    $invocationCount = match (true) {
                        method_exists($expectation, 'getInvocationCount') => $expectation->getInvocationCount(),
                        method_exists($expectation, 'numberOfInvocations') => $expectation->numberOfInvocations(),
                        default => throw new \RuntimeException('Cannot determine invocation count from matcher'),
                    };

                    switch ($invocationCount) {
                        case 1:
                            $this->assertEquals(
                                expected: $accountCredentials1,
                                actual: $accountCredentials,
                            );
                            $return = $attributeIterator1;
                            break;

                        case 2:
                            $this->assertEquals(
                                expected: $accountCredentials2,
                                actual: $accountCredentials,
                            );
                            $return = $attributeIterator2;
                            break;

                        default:
                            $this->fail('AttributesService::get called more than expected');
                            break;
                    }

                    return $return;
                },
            );

        $attributesProvider = $this->objectManager->create(AttributesProviderInterface::class, [
            'attributesService' => $mockAttributesService,
        ]);

        $provider = $this->instantiateTestObject([
            'attributesProvider' => $attributesProvider,
        ]);
        $results = $provider->getAttributeCodesForAllApiKeys(includeAliases: false);

        $this->assertNotContains(needle: 'name', haystack: $results);
        $this->assertNotContains(needle: 'sku', haystack: $results);
        $this->assertContains(needle: 'my_standard_attribute_1', haystack: $results);
        $this->assertNotContains(needle: 'standard_att', haystack: $results);
        $this->assertContains(needle: 'my_standard_attribute_2', haystack: $results);
        $this->assertContains(needle: 'my_standard_attribute_3', haystack: $results);
        $this->assertNotContains(needle: 'my_custom_attribute_4', haystack: $results);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetForAllApiKeys_includeAliases(): void
    {
        $jsApiKey1 = 'klevu-js-key-1';
        $restAuthKey1 = 'klevu-rest-key-1';
        $this->createStore();
        $storeFixture1 = $this->storeFixturesPool->get('test_store');
        $scopeProvider1 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider1->setCurrentScope($storeFixture1->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider1,
            jsApiKey: $jsApiKey1,
            restAuthKey: $restAuthKey1,
        );
        $accountCredentials1 = new AccountCredentials(
            jsApiKey: $jsApiKey1,
            restAuthKey: $restAuthKey1,
        );

        $jsApiKey2 = 'klevu-js-key-2';
        $restAuthKey2 = 'klevu-rest-key-2';
        $this->createStore([
            'key' => 'test_store_2',
            'code' => 'klevu_test_store_2',
        ]);
        $storeFixture2 = $this->storeFixturesPool->get('test_store_2');
        $scopeProvider2 = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider2->setCurrentScope($storeFixture2->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider2,
            jsApiKey: $jsApiKey2,
            restAuthKey: $restAuthKey2,
            removeApiKeys: false,
        );
        $accountCredentials2 = new AccountCredentials(
            jsApiKey: $jsApiKey2,
            restAuthKey: $restAuthKey2,
        );

        $attributeIterator1 = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator1->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_1',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [
                        'standard_att',
                    ],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator1->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_2',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator2 = $this->objectManager->create(AttributeIterator::class);
        $attributeIterator2->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_standard_attribute_3',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'aliases' => [],
                    'immutable' => true,
                ],
            ),
        );
        $attributeIterator2->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_custom_attribute_4',
                    'datatype' => DataType::NUMBER->value,
                    'searchable' => false,
                    'filterable' => true,
                    'returnable' => false,
                    'aliases' => [],
                    'immutable' => false,
                ],
            ),
        );

        $mockAttributesService = $this->getMockBuilder(AttributesService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $expectation = $this->atLeast(2);
        $mockAttributesService->expects($expectation)
            ->method('get')
            ->willReturnCallBack(
                callback: function (AccountCredentials $accountCredentials) use ($expectation, $accountCredentials1, $accountCredentials2, $attributeIterator1, $attributeIterator2): AttributeIterator { // phpcs:ignore Generic.Files.LineLength.TooLong
                    $invocationCount = match (true) {
                        method_exists($expectation, 'getInvocationCount') => $expectation->getInvocationCount(),
                        method_exists($expectation, 'numberOfInvocations') => $expectation->numberOfInvocations(),
                        default => throw new \RuntimeException('Cannot determine invocation count from matcher'),
                    };

                    switch ($invocationCount) {
                        case 1:
                            $this->assertEquals(
                                expected: $accountCredentials1,
                                actual: $accountCredentials,
                            );
                            $return = $attributeIterator1;
                            break;

                        case 2:
                            $this->assertEquals(
                                expected: $accountCredentials2,
                                actual: $accountCredentials,
                            );
                            $return = $attributeIterator2;
                            break;

                        default:
                            $this->fail('AttributesService::get called more than expected');
                            break;
                    }

                    return $return;
                },
            );

        $attributesProvider = $this->objectManager->create(AttributesProviderInterface::class, [
            'attributesService' => $mockAttributesService,
        ]);

        $provider = $this->instantiateTestObject([
            'attributesProvider' => $attributesProvider,
        ]);
        $results = $provider->getAttributeCodesForAllApiKeys(includeAliases: true);

        $this->assertNotContains(needle: 'name', haystack: $results);
        $this->assertNotContains(needle: 'sku', haystack: $results);
        $this->assertContains(needle: 'my_standard_attribute_1', haystack: $results);
        $this->assertContains(needle: 'standard_att', haystack: $results);
        $this->assertContains(needle: 'my_standard_attribute_2', haystack: $results);
        $this->assertContains(needle: 'my_standard_attribute_3', haystack: $results);
        $this->assertNotContains(needle: 'my_custom_attribute_4', haystack: $results);
    }

    /**
     * @param mixed[]|null $arguments
     *
     * @return object
     * @throws \LogicException
     *
     * @todo Reinstate object instantiation and interface traits. Removed as causing serialization of Closure error
     *  in phpunit Standard input code
     */
    private function instantiateTestObject(
        ?array $arguments = null,
    ): object {
        if (!$this->implementationFqcn) {
            throw new \LogicException('Cannot instantiate test object: no implementationFqcn defined');
        }
        if (null === $arguments) {
            $arguments = $this->constructorArgumentDefaults;
        }

        return (null === $arguments)
            ? $this->objectManager->get($this->implementationFqcn)
            : $this->objectManager->create($this->implementationFqcn, $arguments);
    }

    /**
     * @return void
     */
    private function clearAttributesCache(): void
    {
        $cacheState = $this->objectManager->get(type: StateInterface::class);
        $cacheState->setEnabled(cacheType: AttributesCache::TYPE_IDENTIFIER, isEnabled: true);

        $typeList = $this->objectManager->get(TypeList::class);
        $typeList->cleanType(AttributesCache::TYPE_IDENTIFIER);
    }
}
