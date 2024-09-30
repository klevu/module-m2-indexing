<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Provider;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Provider\IndexingEntityProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\Provider\IndexingEntityProvider::class
 * @method IndexingEntityProviderInterface instantiateTestObject(?array $arguments = null)
 * @method IndexingEntityProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingEntityProviderTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = IndexingEntityProvider::class;
        $this->interfaceFqcn = IndexingEntityProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGet_ReturnsIndexingEntities_FilteredByType(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => 456,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 321,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 654,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 789,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 987,
            IndexingEntity::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 4, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingEntityInterface $indexingEntity): int => ($indexingEntity->getTargetId()),
            $results,
        );
        $this->assertContains(321, $targetIds);
        $this->assertContains(654, $targetIds);
        $this->assertContains(789, $targetIds);
        $this->assertContains(987, $targetIds);

        $this->cleanIndexingEntities($apiKey);
    }

    public function testGet_ReturnsIndexingEntities_FilteredBySubtype(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'bundle',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'grouped',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'downloadable',
            IndexingEntity::TARGET_ID => 5,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
            IndexingEntity::TARGET_ID => 6,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variant',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::TARGET_PARENT_ID => 6,
            IndexingEntity::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
            entitySubtypes: [
                'simple',
                'virtual',
                'downloadable',
                'configurable',
            ],
        );
        $this->assertCount(expectedCount: 4, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingEntityInterface $indexingEntity): string => (
                (int)$indexingEntity->getTargetId() . '-' . (int)$indexingEntity->getTargetParentId()
            ),
            $results,
        );
        $this->assertContains('3-0', $targetIds);
        $this->assertContains('4-0', $targetIds);
        $this->assertContains('5-0', $targetIds);
        $this->assertContains('6-0', $targetIds);

        $this->cleanIndexingEntities($apiKey);
    }

    public function testGet_ReturnsIndexingEntities_FilteredByApiKey(): void
    {
        $apiKey1 = 'klevu-js-api-key';
        $apiKey2 = 'klevu-js-api-key-filter-test';
        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::API_KEY => $apiKey2,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => 456,
            IndexingEntity::API_KEY => $apiKey1,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 321,
            IndexingEntity::API_KEY => $apiKey2,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 654,
            IndexingEntity::API_KEY => $apiKey1,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 789,
            IndexingEntity::API_KEY => $apiKey1,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 987,
            IndexingEntity::API_KEY => $apiKey2,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            apiKey: $apiKey2,
        );
        $this->assertCount(expectedCount: 3, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingEntityInterface $indexingEntity): int => ($indexingEntity->getTargetId()),
            $results,
        );
        $this->assertContains(123, $targetIds);
        $this->assertContains(321, $targetIds);
        $this->assertContains(987, $targetIds);

        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);
    }

    public function testGet_ReturnsIndexingEntities_FilteredByEntityTypeAndApiKey(): void
    {
        $apiKey1 = 'klevu-js-api-key';
        $apiKey2 = 'klevu-js-api-key-filter-test';
        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::API_KEY => $apiKey2,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => 456,
            IndexingEntity::API_KEY => $apiKey1,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 321,
            IndexingEntity::API_KEY => $apiKey1,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 101,
            IndexingEntity::API_KEY => $apiKey2,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 654,
            IndexingEntity::API_KEY => $apiKey1,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 789,
            IndexingEntity::API_KEY => $apiKey2,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 987,
            IndexingEntity::API_KEY => $apiKey2,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            entityType: 'KLEVU_CATEGORY',
            apiKey: 'klevu-js-api-key-filter-test',
        );
        $this->assertCount(expectedCount: 2, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingEntityInterface $indexingEntity): int => ($indexingEntity->getTargetId()),
            $results,
        );
        $this->assertContains(123, $targetIds);
        $this->assertContains(101, $targetIds);

        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);
    }

    public function testGet_ReturnsIndexingEntities_FilteredBy_EntityType_ApiKey_NextAction(): void
    {
        $apiKey1 = 'klevu-js-api-key';
        $apiKey2 = 'klevu-js-api-key-filter-test';
        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => 456,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 321,
            IndexingEntity::API_KEY => $apiKey1,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 101,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 654,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 789,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 987,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 1013,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => false,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            entityType: 'KLEVU_PRODUCT',
            apiKey: 'klevu-js-api-key-filter-test',
            nextAction: Actions::ADD,
            isIndexable: true,
        );

        $this->assertCount(expectedCount: 1, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingEntityInterface $indexingEntity): int => ($indexingEntity->getTargetId()),
            $results,
        );
        $this->assertContains(101, $targetIds);

        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);
    }

    public function testGetForTargetParentPairs_IndexingEntities_FilteredByParentAndChild(): void
    {
        $apiKey1 = 'klevu-js-api-key';
        $apiKey2 = 'klevu-js-api-key-filter-test';
        $this->cleanIndexingEntities($apiKey1);
        $this->cleanIndexingEntities($apiKey2);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 234,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ID => 456,
            IndexingEntity::TARGET_PARENT_ID => 567,
            IndexingEntity::API_KEY => $apiKey1,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $expectedEntity1 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 321,
            IndexingEntity::TARGET_PARENT_ID => 432,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 654,
            IndexingEntity::TARGET_PARENT_ID => 765,
            IndexingEntity::API_KEY => $apiKey1,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 987,
            IndexingEntity::TARGET_PARENT_ID => 789,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);
        $expectedEntity2 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 987,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::API_KEY => $apiKey2,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
        ]);

        $provider = $this->instantiateTestObject();
        $collection = $provider->getForTargetParentPairs(
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey2,
            pairs: [
                [IndexingEntity::TARGET_ID => 123, IndexingEntity::TARGET_PARENT_ID => 234], // wrong entity type
                [IndexingEntity::TARGET_ID => 321, IndexingEntity::TARGET_PARENT_ID => 432], // should be returned
                [IndexingEntity::TARGET_ID => 654, IndexingEntity::TARGET_PARENT_ID => 765], // wrong api key
                [IndexingEntity::TARGET_ID => 987, IndexingEntity::TARGET_PARENT_ID => null], // should be returned
            ],
        );
        /** @var IndexingEntityInterface[] $indexingEntities */
        $indexingEntities = $collection->getItems();

        $this->assertCount(expectedCount: 2, haystack: $indexingEntities);
        $entityIds = array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => (int)$indexingEntity->getId(),
            array: $indexingEntities,
        );
        $this->assertContains(needle: $expectedEntity1->getId(), haystack: $entityIds);
        $this->assertContains(needle: $expectedEntity2->getId(), haystack: $entityIds);
    }

    public function testGet_ReturnsIndexingEntities_SortedByProvidedOrder(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 321,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 789,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 654,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 987,
            IndexingEntity::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
            sorting: [
                SortOrder::FIELD => IndexingEntity::TARGET_ID,
                SortOrder::DIRECTION => SortOrder::SORT_DESC,
            ],
        );
        $this->assertCount(expectedCount: 4, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingEntityInterface $indexingEntity): int => ($indexingEntity->getTargetId()),
            $results,
        );
        $this->assertContains(321, $targetIds);
        $this->assertContains(654, $targetIds);
        $this->assertContains(789, $targetIds);
        $this->assertContains(987, $targetIds);

        /** @var IndexingEntity $firstItem */
        $firstItem = array_shift($results);
        $this->assertSame(expected: 987, actual: $firstItem->getTargetId());
        /** @var IndexingEntity $secondItem */
        $secondItem = array_shift($results);
        $this->assertSame(expected: 789, actual: $secondItem->getTargetId());
        /** @var IndexingEntity $thirdItem */
        $thirdItem = array_shift($results);
        $this->assertSame(expected: 654, actual: $thirdItem->getTargetId());
        /** @var IndexingEntity $forthItem */
        $forthItem = array_shift($results);
        $this->assertSame(expected: 321, actual: $forthItem->getTargetId());

        $this->cleanIndexingEntities($apiKey);
    }

    public function testGet_ReturnsIndexingEntities_ByPage(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 321,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 789,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 654,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 987,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 623532,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 359839,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 23431,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 1211,
            IndexingEntity::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            apiKey: $apiKey,
            sorting: [
                SortOrder::FIELD => IndexingEntity::TARGET_ID,
                SortOrder::DIRECTION => SortOrder::SORT_ASC,
            ],
            pageSize: 2,
            currentPage: 2,
        );
        $this->assertCount(expectedCount: 2, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingEntityInterface $indexingEntity): int => ($indexingEntity->getTargetId()),
            $results,
        );
        $this->assertContains(789, $targetIds);
        $this->assertContains(987, $targetIds);

        /** @var IndexingEntity $firstItem */
        $firstItem = array_shift($results);
        $this->assertSame(expected: 789, actual: $firstItem->getTargetId());
        /** @var IndexingEntity $secondItem */
        $secondItem = array_shift($results);
        $this->assertSame(expected: 987, actual: $secondItem->getTargetId());

        $results = $provider->get(
            apiKey: $apiKey,
            sorting: [
                SortOrder::FIELD => IndexingEntity::TARGET_ID,
                SortOrder::DIRECTION => SortOrder::SORT_ASC,
            ],
            pageSize: 2,
            currentPage: 4,
        );
        $this->assertCount(expectedCount: 2, haystack: $results);
        $targetIds = array_map(
            static fn (IndexingEntityInterface $indexingEntity): int => ($indexingEntity->getTargetId()),
            $results,
        );
        $this->assertContains(359839, $targetIds);
        $this->assertContains(623532, $targetIds);

        /** @var IndexingEntity $firstItem */
        $firstItem = array_shift($results);
        $this->assertSame(expected: 359839, actual: $firstItem->getTargetId());
        /** @var IndexingEntity $secondItem */
        $secondItem = array_shift($results);
        $this->assertSame(expected: 623532, actual: $secondItem->getTargetId());

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @dataProvider testGet_ReturnsEmptyArray_WhenPageNumberExceedsLimit_DataProvider
     */
    public function testGet_ReturnsEmptyArray_WhenPageNumberExceedsLimit(?int $pageSize, ?int $pageNumber): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 5,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 6,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 7,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 8,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 9,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 10,
            IndexingEntity::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            apiKey: $apiKey,
            pageSize: $pageSize,
            currentPage: $pageNumber,
        );
        $this->assertCount(expectedCount: 0, haystack: $results);

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @return int[][]
     */
    public function testGet_ReturnsEmptyArray_WhenPageNumberExceedsLimit_DataProvider(): array
    {
        return[
            [3, 999999],
            [3, 5],
            [10, 2],
        ];
    }

    /**
     * @dataProvider testGet_ReturnsIndexingEntities_ForFinalPage_DataProvider
     */
    public function testGet_ReturnsIndexingEntities_ForFinalPage(
        ?int $pageSize,
        ?int $pageNumber,
        int $expectedCount,
    ): void {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 5,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 6,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 7,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 8,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 9,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 10,
            IndexingEntity::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            apiKey: $apiKey,
            pageSize: $pageSize,
            currentPage: $pageNumber,
        );
        $this->assertCount(expectedCount: $expectedCount, haystack: $results);

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @return int[][]
     */
    public function testGet_ReturnsIndexingEntities_ForFinalPage_DataProvider(): array
    {
        return[
            [3, 4, 1],
            [10, 1, 10],
            [null, null, 10],
        ];
    }
}
