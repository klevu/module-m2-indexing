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
            apiKeys: [$apiKey],
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
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::TARGET_PARENT_ID => 6,
            IndexingEntity::API_KEY => $apiKey,
        ]);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            entityType: 'KLEVU_PRODUCT',
            apiKeys: [$apiKey],
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
            apiKeys: [$apiKey2],
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
            apiKeys: ['klevu-js-api-key-filter-test'],
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
            apiKeys: ['klevu-js-api-key-filter-test'],
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
            apiKeys: [$apiKey],
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
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 1211,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 23431,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 359839,
            IndexingEntity::API_KEY => $apiKey,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ID => 623532,
            IndexingEntity::API_KEY => $apiKey,
        ]);

        $indexingEntities = $this->getIndexingEntities(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $entityIds = array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => $indexingEntity->getId(),
            array: $indexingEntities,
        );
        sort($entityIds);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            apiKeys: [$apiKey],
            sorting: [
                SortOrder::FIELD => IndexingEntity::TARGET_ID,
                SortOrder::DIRECTION => SortOrder::SORT_ASC,
            ],
            pageSize: 2,
            startFrom: $entityIds[2],
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
            apiKeys: [$apiKey],
            sorting: [
                SortOrder::FIELD => IndexingEntity::TARGET_ID,
                SortOrder::DIRECTION => SortOrder::SORT_ASC,
            ],
            pageSize: 2,
            startFrom: $entityIds[6],
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

    public function testGet_ReturnsEmptyArray_WhenPageNumberExceedsLimit(): void
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

        $indexingEntities = $this->getIndexingEntities(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $entityIds = array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => $indexingEntity->getId(),
            array: $indexingEntities,
        );
        sort($entityIds);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            apiKeys: [$apiKey],
            pageSize: 20,
            startFrom: $entityIds[9] + 10000,
        );
        $this->assertCount(expectedCount: 0, haystack: $results);

        $this->cleanIndexingEntities($apiKey);
    }

    /**
     * @dataProvider testGet_ReturnsIndexingEntities_ForFinalPage_DataProvider
     */
    public function testGet_ReturnsIndexingEntities_ForFinalPage(
        ?int $pageSize,
        ?int $startFrom,
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

        $indexingEntities = $this->getIndexingEntities(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $entityIds = array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => $indexingEntity->getId(),
            array: $indexingEntities,
        );
        sort($entityIds);

        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            apiKeys: [$apiKey],
            pageSize: $pageSize,
            startFrom: $entityIds[$startFrom] ?? null,
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
            [3, 9, 1],
            [10, 0, 10],
            [null, null, 10],
        ];
    }

    public function testCount_ReturnsZero_WhenNotEntitiesFound(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

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
        ?string $entityType = null,
        ?string $apiKeyToFilter = null,
        ?string $nextAction = null,
        ?bool $isIndexable = null,
    ): void {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);
        $this->cleanIndexingEntities(apiKey: $apiKeyToFilter);

        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => false,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => false,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey . '2',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $provider = $this->instantiateTestObject();
        $result = $provider->count(
            entityType: $entityType,
            apiKey: $apiKeyToFilter,
            nextAction: $nextAction ? Actions::tryFrom($nextAction) : null,
            isIndexable: $isIndexable,
        );

        $this->assertSame(expected: $count, actual: $result);
    }

    public function testGetTypes_ReturnsEmptyArray_WhenNoEntitiesFound(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $provider = $this->instantiateTestObject();
        $result = $provider->getTypes(apiKey: $apiKey);

        $this->assertSame(expected: [], actual: $result);
    }

    public function testGetTypes_ReturnsArrayOfTypes(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'CUSTOM_TYPE',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => false,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => false,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey . '2',
            IndexingEntity::TARGET_ENTITY_TYPE => 'OTHER_CUSTOM_TYPE',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'category',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::IS_INDEXABLE => true,
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
