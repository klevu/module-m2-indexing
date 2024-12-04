<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\ViewModel\Config\Information;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\Indexing\ViewModel\Config\Information\IndexingAttributes;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\ViewModel\Config\Information\IndexingAttributesInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers IndexingAttributes
 * @method IndexingAttributesInterface instantiateTestObject(?array $arguments = null)
 * @method IndexingAttributesInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingAttributesTest extends TestCase
{
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = IndexingAttributes::class;
        $this->interfaceFqcn = IndexingAttributesInterface::class;
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

    /**
     * @magentoAppIsolation enabled
     */
    public function testHasAttributes_ReturnsFalse_WhenNotIntegrated(): void
    {
        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->hasAttributes();

        $this->assertFalse(condition: $result);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetAttributes_ReturnsEmptyArray_WhenNotIntegrated(): void
    {
        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->getAttributes();

        $this->assertCount(expectedCount: 0, haystack: $result);
    }

    public function testHasAttributes_ReturnsTrue_WhenStoreIntegrated(): void
    {
        $apiKey = 'klevu_js_api_key';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu_rest_auth_key',
        );
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->hasAttributes();

        $this->assertTrue(condition: $result);
    }

    public function testGetAttributes_ReturnsEmptyArray_WhenStoreIntegrated(): void
    {
        $apiKey = 'klevu_js_api_key';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu_rest_auth_key',
        );
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->getAttributes();

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        $this->assertCount(expectedCount: 0, haystack: $result[$apiKey]);
    }

    public function testHasAttributes_ReturnsTrue_WhenIntegratedAndEntityIndexingEntitiesPresent(): void
    {
        $apiKey = 'klevu_js_api_key';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu_rest_auth_key',
        );

        $this->cleanIndexingAttributes(apiKey: $apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->hasAttributes();

        $this->assertTrue(condition: $result);
    }

    public function testGetAttributes_ReturnsArray_WhenIntegratedAndEntityIndexingEntitiesPresent(): void
    {
        $apiKey = 'klevu_js_api_key';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu_rest_auth_key',
        );
        $this->cleanIndexingAttributes(apiKey: $apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 5,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 6,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->getAttributes();

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        $this->assertCount(expectedCount: 2, haystack: $result[$apiKey]);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result[$apiKey]);
        $productResult = $result[$apiKey]['KLEVU_PRODUCT'];
        $this->assertArrayHasKey(key: 'total', array: $productResult);
        $this->assertSame(expected: '6', actual: $productResult['total']);
        $this->assertArrayHasKey(key: 'indexable', array: $productResult);
        $this->assertSame(expected: '5', actual: $productResult['indexable']);
        $this->assertArrayHasKey(key: Actions::ADD->value, array: $productResult);
        $this->assertSame(expected: '1', actual: $productResult[Actions::ADD->value]);
        $this->assertArrayHasKey(key: Actions::DELETE->value, array: $productResult);
        $this->assertSame(expected: '1', actual: $productResult[Actions::DELETE->value]);
        $this->assertArrayHasKey(key: Actions::UPDATE->value, array: $productResult);
        $this->assertSame(expected: '2', actual: $productResult[Actions::UPDATE->value]);
        $this->assertArrayHasKey(key: Actions::NO_ACTION->value, array: $productResult);
        $this->assertSame(expected: '1', actual: $productResult[Actions::NO_ACTION->value]);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY', array: $result[$apiKey]);
        $categoryResult = $result[$apiKey]['KLEVU_CATEGORY'];
        $this->assertArrayHasKey(key: 'total', array: $categoryResult);
        $this->assertSame(expected: '4', actual: $categoryResult['total']);
        $this->assertArrayHasKey(key: 'indexable', array: $categoryResult);
        $this->assertSame(expected: '4', actual: $categoryResult['indexable']);
        $this->assertArrayHasKey(key: Actions::ADD->value, array: $categoryResult);
        $this->assertSame(expected: '2', actual: $categoryResult[Actions::ADD->value]);
        $this->assertArrayHasKey(key: Actions::DELETE->value, array: $categoryResult);
        $this->assertSame(expected: '1', actual: $categoryResult[Actions::DELETE->value]);
        $this->assertArrayHasKey(key: Actions::UPDATE->value, array: $categoryResult);
        $this->assertSame(expected: '1', actual: $categoryResult[Actions::UPDATE->value]);
        $this->assertArrayHasKey(key: Actions::NO_ACTION->value, array: $productResult);
        $this->assertSame(expected: '0', actual: $categoryResult[Actions::NO_ACTION->value]);
    }
}
