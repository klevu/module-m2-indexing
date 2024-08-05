<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ApiKeyProvider;
use Klevu\Configuration\Service\Provider\AuthKeyProvider;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\Provider\DuplicateAttributeMappingProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\DuplicateAttributeMappingProviderInterface;
use Klevu\IndexingCategories\Service\Mapper\MagentoToKlevuAttributeMapper as CategoryAttributeMapperVirtualType;
use Klevu\IndexingProducts\Service\Mapper\MagentoToKlevuAttributeMapper as ProductAttributeMapperVirtualType;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers DuplicateAttributeMappingProvider::class
 * @method DuplicateAttributeMappingProviderInterface instantiateTestObject(?array $arguments = null)
 * @method DuplicateAttributeMappingProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DuplicateAttributeMappingProviderTest extends TestCase
{
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = DuplicateAttributeMappingProvider::class;
        $this->interfaceFqcn = DuplicateAttributeMappingProviderInterface::class;

        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
    }

    public function testGet_whenNoDuplicates(): void
    {
        $apiKey = 'klevu-1234567890';

        $this->createStore([
            'code' => 'klevu_indexing_test_store_1',
            'key' => 'klevu_indexing_test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('klevu_indexing_test_store_1');
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: $apiKey,
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: 'ABCDE1234567890',
            storeCode: $storeFixture->getCode(),
        );

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_3',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 5,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_3',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 6,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_4',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $duplicateAttributeMappingProvider = $this->instantiateTestObject();
        $result = $duplicateAttributeMappingProvider->get(apiKey: $apiKey);

        $this->assertIsArray($result);
        $this->assertCount(expectedCount: 0, haystack: $result);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_DuplicateAttributesExistInKlevuDbTable(): void
    {
        $apiKey = 'klevu-1234567890';

        $this->createStore([
            'code' => 'klevu_indexing_test_store_1',
            'key' => 'klevu_indexing_test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('klevu_indexing_test_store_1');
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: $apiKey,
            storeCode: $storeFixture->getCode(),
        );
        ConfigFixture::setForStore(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: 'ABCDE1234567890',
            storeCode: $storeFixture->getCode(),
        );

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 7,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 8,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 5,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_3',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 6,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_3',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $duplicateAttributeMappingProvider = $this->instantiateTestObject();
        $result = $duplicateAttributeMappingProvider->get(apiKey: $apiKey);

        $this->assertIsArray($result);

        $this->assertArrayHasKey('KLEVU_CATEGORY', $result);
        $this->assertArrayNotHasKey('cat__klevu_test_attribute_1', $result['KLEVU_CATEGORY']);
        $this->assertArrayNotHasKey('cat__klevu_test_attribute_2', $result['KLEVU_CATEGORY']);
        $this->assertArrayHasKey('cat__klevu_test_attribute_3', $result['KLEVU_CATEGORY']);
        $this->assertSame(2, $result['KLEVU_CATEGORY']['cat__klevu_test_attribute_3']);

        $this->assertArrayHasKey('KLEVU_PRODUCT', $result);
        $this->assertArrayHasKey('klevu_test_attribute_1', $result['KLEVU_PRODUCT']);
        $this->assertSame(2, $result['KLEVU_PRODUCT']['klevu_test_attribute_1']);
        $this->assertArrayNotHasKey('klevu_test_attribute_2', $result['KLEVU_PRODUCT']);
        $this->assertArrayNotHasKey('klevu_test_attribute_3', $result['KLEVU_PRODUCT']);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_DuplicatesCreatedViaXMLAttributeMapping(): void
    {
        $apiKey = 'klevu-1234567890';

        $this->createStore([
            'code' => 'klevu_indexing_test_store_1',
            'key' => 'klevu_indexing_test_store_1',
        ]);
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: $apiKey,
            storeCode: 'klevu_indexing_test_store_1',
        );
        ConfigFixture::setForStore(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: 'ABCDE1234567890',
            storeCode: 'klevu_indexing_test_store_1',
        );

        // Note, the same attribute code (klevu_test_attribute_1) is intentionally used for
        //  both KLEVU_PRODUCT and KLEVU_CATEGORY to test checks occur within - and _only_
        //  within - an entity type (eg, we're not reporting KLEVU_PRODUCT mapping 3 times)
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $attributeMapperProduct = $this->objectManager->create(ProductAttributeMapperVirtualType::class, [
            'attributeMapping' => [
                'klevu_test_attribute_1' => 'description',
            ],
        ]);
        $attributeMapperCategory = $this->objectManager->create(CategoryAttributeMapperVirtualType::class, [
            'attributeMapping' => [
                'klevu_test_attribute_1' => 'name',
            ],
        ]);

        $duplicateAttributeMappingProvider = $this->instantiateTestObject([
            'attributeMappers' => [
                'KLEVU_PRODUCT' => $attributeMapperProduct,
                'KLEVU_CATEGORY' => $attributeMapperCategory,
            ],
        ]);
        $result = $duplicateAttributeMappingProvider->get(apiKey: $apiKey);

        $this->assertArrayHasKey('KLEVU_CATEGORY', $result);
        $this->assertArrayNotHasKey('description', $result['KLEVU_CATEGORY']);
        $this->assertArrayHasKey('name', $result['KLEVU_CATEGORY']);
        $this->assertSame(2, $result['KLEVU_CATEGORY']['name']);

        $this->assertArrayHasKey('KLEVU_PRODUCT', $result);
        $this->assertArrayHasKey('description', $result['KLEVU_PRODUCT']);
        $this->assertSame(2, $result['KLEVU_PRODUCT']['description']);
        $this->assertArrayNotHasKey('name', $result['KLEVU_PRODUCT']);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGet_DoesNotFlagConflictsBetweenEntityTypes(): void
    {
        $apiKey = 'klevu-1234567890';

        $this->createStore([
            'code' => 'klevu_indexing_test_store_1',
            'key' => 'klevu_indexing_test_store_1',
        ]);
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: $apiKey,
            storeCode: 'klevu_indexing_test_store_1',
        );
        ConfigFixture::setForStore(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: 'ABCDE1234567890',
            storeCode: 'klevu_indexing_test_store_1',
        );

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'test_klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $attributeMapperProduct = $this->objectManager->create(ProductAttributeMapperVirtualType::class);
        $attributeMapperCategory = $this->objectManager->create(CategoryAttributeMapperVirtualType::class, [
            'prefix' => 'test_',
        ]);

        $duplicateAttributeMappingProvider = $this->instantiateTestObject([
            'attributeMappers' => [
                'KLEVU_PRODUCT' => $attributeMapperProduct,
                'KLEVU_CATEGORY' => $attributeMapperCategory,
            ],
        ]);
        $result = $duplicateAttributeMappingProvider->get(apiKey: $apiKey);

        $this->assertCount(expectedCount: 0, haystack: $result);
    }
}
