<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Configuration\Test\Integration\ViewModel\Config\Information;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\Indexing\ViewModel\Config\Information\IndexingEntities;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\ViewModel\Config\Information\IndexingEntitiesInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers IndexingEntities
 * @method IndexingEntitiesInterface instantiateTestObject(?array $arguments = null)
 * @method IndexingEntitiesInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingEntitiesTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface
     */
    private ObjectManagerInterface $objectManager; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = IndexingEntities::class;
        $this->interfaceFqcn = IndexingEntitiesInterface::class;
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

    public function testHasEntities_ReturnsFalse_WhenNotIntegrated(): void
    {
        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->hasEntities();

        $this->assertFalse(condition: $result);
    }

    public function testGetEntities_ReturnsEmptyArray_WhenNotIntegrated(): void
    {
        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->getEntities();

        $this->assertCount(expectedCount: 0, haystack: $result);
    }

    public function testHasEntities_ReturnsTrue_WhenStoreIntegrated(): void
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
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->hasEntities();

        $this->assertTrue(condition: $result);
    }

    public function testGetEntities_ReturnsEmptyArray_WhenStoreIntegrated(): void
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
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->getEntities();

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        $this->assertCount(expectedCount: 0, haystack: $result[$apiKey]);
    }

    public function testHasEntities_ReturnsTrue_WhenIntegratedAndEntityIndexingEntitiesPresent(): void
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

        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->hasEntities();

        $this->assertTrue(condition: $result);
    }

    public function testGetEntities_ReturnsArray_WhenIntegratedAndEntityIndexingEntitiesPresent(): void
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
        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 5,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 6,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => false,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d h:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $viewModel = $this->instantiateTestObject([]);
        $result = $viewModel->getEntities();

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertArrayHasKey(key: $apiKey, array: $result);

        $this->assertCount(expectedCount: 3, haystack: $result[$apiKey]);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result[$apiKey]);
        $productResult = $result[$apiKey]['KLEVU_PRODUCT'];
        $this->assertArrayHasKey(key: 'total', array: $productResult);
        $this->assertSame(expected: 6, actual: $productResult['total']);
        $this->assertArrayHasKey(key: Actions::ADD->value, array: $productResult);
        $this->assertSame(expected: 1, actual: $productResult[Actions::ADD->value]);
        $this->assertArrayHasKey(key: Actions::DELETE->value, array: $productResult);
        $this->assertSame(expected: 1, actual: $productResult[Actions::DELETE->value]);
        $this->assertArrayHasKey(key: Actions::UPDATE->value, array: $productResult);
        $this->assertSame(expected: 2, actual: $productResult[Actions::UPDATE->value]);
        $this->assertArrayHasKey(key: Actions::NO_ACTION->value, array: $productResult);
        $this->assertSame(expected: 1, actual: $productResult[Actions::NO_ACTION->value]);

        $this->assertArrayHasKey(key: 'KLEVU_CMS', array: $result[$apiKey]);
        $cmsResult = $result[$apiKey]['KLEVU_CMS'];
        $this->assertArrayHasKey(key: 'total', array: $cmsResult);
        $this->assertSame(expected: 4, actual: $cmsResult['total']);
        $this->assertArrayHasKey(key: Actions::ADD->value, array: $cmsResult);
        $this->assertSame(expected: 1, actual: $cmsResult[Actions::ADD->value]);
        $this->assertArrayHasKey(key: Actions::DELETE->value, array: $cmsResult);
        $this->assertSame(expected: 1, actual: $cmsResult[Actions::DELETE->value]);
        $this->assertArrayHasKey(key: Actions::UPDATE->value, array: $cmsResult);
        $this->assertSame(expected: 1, actual: $cmsResult[Actions::UPDATE->value]);
        $this->assertArrayHasKey(key: Actions::NO_ACTION->value, array: $productResult);
        $this->assertSame(expected: 1, actual: $cmsResult[Actions::NO_ACTION->value]);

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY', array: $result[$apiKey]);
        $categoryResult = $result[$apiKey]['KLEVU_CATEGORY'];
        $this->assertArrayHasKey(key: 'total', array: $categoryResult);
        $this->assertSame(expected: 4, actual: $categoryResult['total']);
        $this->assertArrayHasKey(key: Actions::ADD->value, array: $categoryResult);
        $this->assertSame(expected: 2, actual: $categoryResult[Actions::ADD->value]);
        $this->assertArrayHasKey(key: Actions::DELETE->value, array: $categoryResult);
        $this->assertSame(expected: 1, actual: $categoryResult[Actions::DELETE->value]);
        $this->assertArrayHasKey(key: Actions::UPDATE->value, array: $categoryResult);
        $this->assertSame(expected: 1, actual: $categoryResult[Actions::UPDATE->value]);
        $this->assertArrayHasKey(key: Actions::NO_ACTION->value, array: $productResult);
        $this->assertSame(expected: 0, actual: $categoryResult[Actions::NO_ACTION->value]);
    }
}
