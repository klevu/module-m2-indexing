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
}
