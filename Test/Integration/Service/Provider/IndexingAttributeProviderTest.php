<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\Provider\IndexingAttributeProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\Provider\IndexingAttributeProvider::class
 * @method IndexingAttributeProviderInterface instantiateTestObject(?array $arguments = null)
 * @method IndexingAttributeProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingAttributeProviderTest extends TestCase
{
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = IndexingAttributeProvider::class;
        $this->interfaceFqcn = IndexingAttributeProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->cleanIndexingAttributes('klevu-js-api-key');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanIndexingAttributes('klevu-js-api-key');
    }

    public function testGet_ReturnsIndexingAttributes_FilteredByType(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 123,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CMS',
            IndexingAttribute::TARGET_ID => 456,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 321,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 654,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 789,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 987,
            IndexingAttribute::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            attributeType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 4, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingAttributeInterface $indexingAttribute): int => (int)$indexingAttribute->getTargetId(),
            $results,
        );
        $this->assertContains(321, $targetIds);
        $this->assertContains(654, $targetIds);
        $this->assertContains(789, $targetIds);
        $this->assertContains(987, $targetIds);

        $this->cleanIndexingAttributes($apiKey);
    }

    public function testGet_ReturnsIndexingAttributes_FilteredByApiKey(): void
    {
        $apiKey = 'klevu-js-api-key-filter-test';
        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 123,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CMS',
            IndexingAttribute::TARGET_ID => 456,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 321,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 654,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 789,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 987,
            IndexingAttribute::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            apiKey: 'klevu-js-api-key-filter-test',
        );
        $this->assertCount(expectedCount: 3, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingAttributeInterface $indexingAttribute): int => (int)$indexingAttribute->getTargetId(),
            $results,
        );
        $this->assertContains(123, $targetIds);
        $this->assertContains(321, $targetIds);
        $this->assertContains(987, $targetIds);

        $this->cleanIndexingAttributes($apiKey);
    }

    public function testGet_ReturnsIndexingAttributes_FilteredByEntityTypeAndApiKey(): void
    {
        $apiKey = 'klevu-js-api-key-filter-test';
        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 123,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CMS',
            IndexingAttribute::TARGET_ID => 456,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 321,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 101,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 654,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 789,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 987,
            IndexingAttribute::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            attributeType: 'KLEVU_CATEGORY',
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 2, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingAttributeInterface $indexingAttribute): int => (int)$indexingAttribute->getTargetId(),
            $results,
        );
        $this->assertContains(123, $targetIds);
        $this->assertContains(101, $targetIds);

        $this->cleanIndexingAttributes($apiKey);
    }

    public function testGet_ReturnsIndexingAttributes_FilteredByEntityType_ApiKey_Ids(): void
    {
        $apiKey = 'klevu-js-api-key-filter-test';
        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 123,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CMS',
            IndexingAttribute::TARGET_ID => 456,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 321,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 101,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 654,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 789,
            IndexingAttribute::API_KEY => $apiKey,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 987,
            IndexingAttribute::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            attributeType: 'KLEVU_CATEGORY',
            apiKey: $apiKey,
            attributeIds: [123],
        );
        $this->assertCount(expectedCount: 1, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingAttributeInterface $indexingAttribute): int => (int)$indexingAttribute->getTargetId(),
            $results,
        );
        $this->assertContains(123, $targetIds);
        $this->assertNotContains(101, $targetIds);
        $this->assertNotContains(456, $targetIds);
        $this->assertNotContains(321, $targetIds);
        $this->assertNotContains(654, $targetIds);
        $this->assertNotContains(789, $targetIds);
        $this->assertNotContains(987, $targetIds);

        $this->cleanIndexingAttributes($apiKey);
    }

    public function testGet_ReturnsIndexingAttributes_FilteredIsIndexable(): void
    {
        $apiKey = 'klevu-js-api-key-filter-test';
        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 123,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CMS',
            IndexingAttribute::TARGET_ID => 456,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 321,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 101,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 654,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 789,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 987,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            attributeType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
            isIndexable: true,
        );
        $this->assertCount(expectedCount: 2, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingAttributeInterface $indexingAttribute): int => (int)$indexingAttribute->getTargetId(),
            $results,
        );
        $this->assertContains(654, $targetIds);
        $this->assertContains(789, $targetIds);
        $this->assertNotContains(101, $targetIds);
        $this->assertNotContains(123, $targetIds);
        $this->assertNotContains(456, $targetIds);
        $this->assertNotContains(321, $targetIds);
        $this->assertNotContains(987, $targetIds);

        $this->cleanIndexingAttributes($apiKey);
    }

    public function testCount_ReturnsZero_WhenNotAttributesFound(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $provider = $this->instantiateTestObject();
        $result = $provider->count(apiKey: $apiKey);

        $this->assertSame(expected: 0, actual: $result);
    }

    /**
     * @testWith [8, null, "klevu-test-api-key", null, null]
     *           [1, null, "klevu-test-api-key2", null, null]
     *           [6, null, "klevu-test-api-key", null, true]
     *           [2, null, "klevu-test-api-key", null, false]
     *           [4, "KLEVU_PRODUCT", "klevu-test-api-key", null, null]
     *           [1, "KLEVU_PRODUCT", "klevu-test-api-key", "Add", null]
     *           [1, "KLEVU_CATEGORY", "klevu-test-api-key", "Delete", true]
     *           [2, null, "klevu-test-api-key", "Add", null]
     *           [0, null, "klevu-test-api-key", "Update", false]
     */
    public function testCount_ReturnsCount_WhenFiltered(
        int $count,
        ?string $attributeType = null,
        ?string $apiKeyToFilter = null,
        ?string $nextAction = null,
        ?bool $isIndexable = null,
    ): void {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingAttributes(apiKey: $apiKey);
        $this->cleanIndexingAttributes(apiKey: $apiKeyToFilter);

        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey . '2',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $provider = $this->instantiateTestObject();
        $result = $provider->count(
            attributeType: $attributeType,
            apiKey: $apiKeyToFilter,
            nextAction: $nextAction ? Actions::tryFrom($nextAction) : null,
            isIndexable: $isIndexable,
        );

        $this->assertSame(expected: $count, actual: $result);
    }

    public function testGetTypes_ReturnsEmptyArray_WhenNoAttributesFound(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $provider = $this->instantiateTestObject();
        $result = $provider->getTypes(apiKey: $apiKey);

        $this->assertSame(expected: [], actual: $result);
    }

    public function testGetTypes_ReturnsArrayOfTypes(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $this->createIndexingAttribute([
            indexingAttribute::API_KEY => $apiKey,
            indexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            indexingAttribute::TARGET_ID => 1,
            indexingAttribute::NEXT_ACTION => Actions::ADD,
            indexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            indexingAttribute::API_KEY => $apiKey,
            indexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            indexingAttribute::TARGET_ID => 2,
            indexingAttribute::NEXT_ACTION => Actions::UPDATE,
            indexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            indexingAttribute::API_KEY => $apiKey,
            indexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            indexingAttribute::TARGET_ID => 3,
            indexingAttribute::NEXT_ACTION => Actions::DELETE,
            indexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            indexingAttribute::API_KEY => $apiKey,
            indexingAttribute::TARGET_ATTRIBUTE_TYPE => 'CUSTOM_TYPE',
            indexingAttribute::TARGET_ID => 4,
            indexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            indexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            indexingAttribute::API_KEY => $apiKey,
            indexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            indexingAttribute::TARGET_ID => 1,
            indexingAttribute::NEXT_ACTION => Actions::ADD,
            indexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            indexingAttribute::API_KEY => $apiKey,
            indexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            indexingAttribute::TARGET_ID => 2,
            indexingAttribute::NEXT_ACTION => Actions::UPDATE,
            indexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            indexingAttribute::API_KEY => $apiKey,
            indexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CMS',
            indexingAttribute::TARGET_ID => 3,
            indexingAttribute::NEXT_ACTION => Actions::DELETE,
            indexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            indexingAttribute::API_KEY => $apiKey,
            indexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            indexingAttribute::TARGET_ID => 4,
            indexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            indexingAttribute::IS_INDEXABLE => false,
        ]);
        $this->createIndexingAttribute([
            indexingAttribute::API_KEY => $apiKey . '2',
            indexingAttribute::TARGET_ATTRIBUTE_TYPE => 'OTHER_CUSTOM_TYPE', indexingAttribute::TARGET_ID => 1,
            indexingAttribute::NEXT_ACTION => Actions::UPDATE,
            indexingAttribute::IS_INDEXABLE => true,
        ]);

        $provider = $this->instantiateTestObject();

        $result = $provider->getTypes(apiKey: $apiKey);
        $this->assertContains(needle: 'KLEVU_CATEGORY', haystack: $result);
        $this->assertContains(needle: 'KLEVU_CMS', haystack: $result);
        $this->assertContains(needle: 'KLEVU_PRODUCT', haystack: $result);
        $this->assertContains(needle: 'CUSTOM_TYPE', haystack: $result);
        $this->assertNotContains(needle: 'OTHER_CUSTOM_TYPE', haystack: $result);

        $result = $provider->getTypes(apiKey: $apiKey . '2');
        $this->assertNotContains(needle: 'KLEVU_CATEGORY', haystack: $result);
        $this->assertNotContains(needle: 'KLEVU_CMS', haystack: $result);
        $this->assertNotContains(needle: 'KLEVU_PRODUCT', haystack: $result);
        $this->assertNotContains(needle: 'CUSTOM_TYPE', haystack: $result);
        $this->assertContains(needle: 'OTHER_CUSTOM_TYPE', haystack: $result);
    }
}
