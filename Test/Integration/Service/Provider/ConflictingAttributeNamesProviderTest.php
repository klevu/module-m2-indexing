<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\Provider\ConflictingAttributeNamesProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\ConflictingAttributeNamesProviderInterface;
use Klevu\IndexingCategories\Service\Mapper\MagentoToKlevuAttributeMapper as CategoryAttributeMapperVirtualType;
use Klevu\IndexingProducts\Service\Mapper\MagentoToKlevuAttributeMapper as ProductAttributeMapperVirtualType;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\AttributeApiCallTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers ConflictingAttributeNamesProvider::class
 * @method ConflictingAttributeNamesProviderInterface instantiateTestObject(?array $arguments = null)
 * @method ConflictingAttributeNamesProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ConflictingAttributeNamesProviderTest extends TestCase
{
    use AttributeApiCallTrait;
    use IndexingAttributesTrait;
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

        $this->implementationFqcn = ConflictingAttributeNamesProvider::class;
        $this->interfaceFqcn = ConflictingAttributeNamesProviderInterface::class;
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
        parent::tearDown();

        $this->storeFixturesPool->rollback();
        $this->removeSharedApiInstances();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testGetForApiKey(): void
    {
        $apiKey = 'klevu-1234567890';

        $this->createStore([
            'code' => 'klevu_indexing_test_store_1',
            'key' => 'klevu_indexing_test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('klevu_indexing_test_store_1');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'ABCDE1234567890',
        );

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'cat__klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        /** @var ConflictingAttributeNamesProviderInterface $conflictingAttributeNamesProvider */
        $conflictingAttributeNamesProvider = $this->instantiateTestObject();

        $result = $conflictingAttributeNamesProvider->getForApiKey(
            apiKey: 'klevu-1234567890',
        );

        $this->assertSame(
            expected: [
                'cat__klevu_test_attribute_1' => [
                    'KLEVU_CATEGORY',
                    'KLEVU_PRODUCT',
                ],
            ],
            actual: $result,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetForApiKey_DuplicatesCauseByPrefixInDiXml(): void
    {
        $apiKey = 'klevu-1234567890';

        $this->createStore([
            'code' => 'klevu_indexing_test_store_1',
            'key' => 'klevu_indexing_test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('klevu_indexing_test_store_1');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'ABCDE1234567890',
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
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => 'klevu-some-other-key',
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::TARGET_CODE => 'test_klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 5,
            IndexingAttribute::TARGET_CODE => 'test_klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 6,
            IndexingAttribute::TARGET_CODE => 'test_klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CMS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $attributeMapperProduct = $this->objectManager->get(ProductAttributeMapperVirtualType::class);
        $attributeMapperCategory = $this->objectManager->create(CategoryAttributeMapperVirtualType::class, [
            'prefix' => 'test_',
        ]);

        $conflictingAttributeNamesProvider = $this->instantiateTestObject([
            'attributeMappers' => [
                'KLEVU_PRODUCT' => $attributeMapperProduct,
                'KLEVU_CATEGORY' => $attributeMapperCategory,
            ],
        ]);
        $result = $conflictingAttributeNamesProvider->getForApiKey(apiKey: $apiKey);

        $this->assertIsArray(actual: $result);
        $this->assertArrayHasKey(key: 'test_klevu_test_attribute_1', array: $result);
        $this->assertIsArray(actual: $result['test_klevu_test_attribute_1']);
        $this->assertContains(needle: 'KLEVU_CATEGORY', haystack: $result['test_klevu_test_attribute_1']);
        $this->assertContains(needle: 'KLEVU_CMS', haystack: $result['test_klevu_test_attribute_1']);
        $this->assertContains(needle: 'KLEVU_PRODUCT', haystack: $result['test_klevu_test_attribute_1']);

        $this->assertCount(
            expectedCount: 1,
            haystack: array_filter(
                array: $result['test_klevu_test_attribute_1'],
                callback: static fn (string $attributeType): bool => $attributeType === 'KLEVU_PRODUCT',
            ),
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetForApiKey_DoesNotFlagAttributesOfSameType(): void
    {
        $apiKey = 'klevu-1234567890';

        $this->createStore([
            'code' => 'klevu_indexing_test_store_1',
            'key' => 'klevu_indexing_test_store_1',
        ]);
        $storeFixture = $this->storeFixturesPool->get('klevu_indexing_test_store_1');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'ABCDE1234567890',
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
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::TARGET_CODE => 'test_klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 5,
            IndexingAttribute::TARGET_CODE => 'test_klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);

        $attributeMapperProduct = $this->objectManager->get(ProductAttributeMapperVirtualType::class);
        $attributeMapperCategory = $this->objectManager->create(CategoryAttributeMapperVirtualType::class, [
            'prefix' => 'test_',
        ]);

        $conflictingAttributeNamesProvider = $this->instantiateTestObject([
            'attributeMappers' => [
                'KLEVU_PRODUCT' => $attributeMapperProduct,
                'KLEVU_CATEGORY' => $attributeMapperCategory,
            ],
        ]);
        $result = $conflictingAttributeNamesProvider->getForApiKey(apiKey: $apiKey);

        $this->assertIsArray(actual: $result);
        $this->assertCount(expectedCount: 0, haystack: $result);
    }
}
