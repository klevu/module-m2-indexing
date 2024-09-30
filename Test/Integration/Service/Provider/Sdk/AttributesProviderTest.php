<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider\Sdk;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Configuration\Service\Provider\Sdk\BaseUrlsProvider;
use Klevu\Indexing\Cache\Attributes as AttributesCache;
use Klevu\Indexing\Exception\StoreApiKeyException;
use Klevu\Indexing\Service\Provider\Sdk\AttributesProvider;
use Klevu\IndexingApi\Model\Source\StandardAttribute;
use Klevu\IndexingApi\Service\Provider\Sdk\AttributesProviderInterface;
use Klevu\PhpSDK\Exception\Api\BadRequestException;
use Klevu\PhpSDK\Exception\Api\BadResponseException;
use Klevu\PhpSDK\Model\AccountCredentials;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\AttributeIterator;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\PhpSDK\Service\Indexing\AttributesService;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeList;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers AttributesProvider::class
 * @method AttributesProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AttributesProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributesProviderTest extends TestCase
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

        $this->implementationFqcn = AttributesProvider::class;
        $this->interfaceFqcn = AttributesProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->clearCache();
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
            restAuthKey: 'ABCDEFGHI1234567890',
        );

        $provider = $this->instantiateTestObject();
        $provider->get(apiKey: $jsApiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ThrowsException_OnApiResponseFailure(): void
    {
        $this->expectException(BadResponseException::class);
        $this->expectExceptionMessage('Something went wrong');

        $jsApiKey = 'klevu-123456789';
        $restAuthKey = 'ABCDEFGHI1234567890';

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

        $provider = $this->instantiateTestObject([
            'attributesService' => $mockAttributesService,
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

        $jsApiKey = 'klevu-1234567890';
        $restAuthKey = 'ABCDEFGHI1234567890';

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

        $provider = $this->instantiateTestObject([
            'attributesService' => $mockAttributesService,
        ]);
        $provider->get(apiKey: $jsApiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ReturnsAttributes_FirstFromApiThenFromCache(): void
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

        $attributeIterator = $this->objectManager->get(AttributeIterator::class);
        $attributeIterator->addItem(
            item: $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'description',
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
                    'attributeName' => 'name',
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

        $provider = $this->instantiateTestObject([
            'attributesService' => $mockAttributesService,
        ]);

        $results = $provider->get(apiKey: $jsApiKey);
        $attributeCodes = [];
        foreach ($results as $attribute) {
            $attributeCodes[] = $attribute->getAttributeName();
        }
        $this->assertContains(needle: 'description', haystack: $attributeCodes);
        $this->assertContains(needle: 'name', haystack: $attributeCodes);
        $this->assertContains(needle: 'sku', haystack: $attributeCodes);
        $this->assertContains(needle: 'my_custom_attribute', haystack: $attributeCodes);

        $resultsFromCache = $provider->get(apiKey: $jsApiKey);
        $attributeCodesFromCache = [];
        foreach ($resultsFromCache as $attributeFromCache) {
            $attributeCodesFromCache[] = $attributeFromCache->getAttributeName();
        }
        $this->assertContains(needle: 'description', haystack: $attributeCodesFromCache);
        $this->assertContains(needle: 'name', haystack: $attributeCodesFromCache);
        $this->assertContains(needle: 'sku', haystack: $attributeCodesFromCache);
        $this->assertContains(needle: 'my_custom_attribute', haystack: $attributeCodesFromCache);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_ReturnsAttributes_forRealApiCall(): void
    {
        /**
         * This test requires your Klevu API keys
         * These API keys can be set in dev/tests/integration/phpunit.xml
         * <phpunit>
         *     <testsuites>
         *      ...
         *     </testsuites>
         *     <php>
         *         ...
         *         <env name="KLEVU_JS_API_KEY" value="" force="true" />
         *         <env name="KLEVU_REST_API_KEY" value="" force="true" />
         *         <env name="KLEVU_INDEXING_URL" value="indexing.ksearchnet.com" force="true" />
         *     </php>
         */
        $restApiKey = getenv('KLEVU_REST_API_KEY');
        $jsApiKey = getenv('KLEVU_JS_API_KEY');
        $indexingApiUrl = getenv('KLEVU_INDEXING_URL');
        if (!$restApiKey || !$jsApiKey || !$indexingApiUrl) {
            $this->markTestSkipped('Klevu API keys are not set in `dev/tests/integration/phpunit.xml`. Test Skipped');
        }
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $store = $storeManager->getDefaultStoreView();

        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $store);
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $jsApiKey,
            restAuthKey: $restApiKey,
        );
        ConfigFixture::setForStore(
            path: BaseUrlsProvider::CONFIG_XML_PATH_URL_INDEXING,
            value: $indexingApiUrl,
            storeCode: $store->getCode(),
        );

        $provider = $this->instantiateTestObject();
        $results = $provider->get(apiKey: $jsApiKey);

        $attributeCodes = [];
        foreach ($results as $attribute) {
            $attributeCodes[] = $attribute->getAttributeName();
        }

        foreach (StandardAttribute::values() as $attributeCode) {
            $this->assertContains(needle: $attributeCode, haystack: $attributeCodes);
        }
    }

    /**
     * @return void
     */
    private function clearCache(): void
    {
        $cacheState = $this->objectManager->get(type: StateInterface::class);
        $cacheState->setEnabled(cacheType: AttributesCache::TYPE_IDENTIFIER, isEnabled: true);

        $typeList = $this->objectManager->get(TypeList::class);
        $typeList->cleanType(AttributesCache::TYPE_IDENTIFIER);
    }
}
