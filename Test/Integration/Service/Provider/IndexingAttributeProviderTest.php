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
}
