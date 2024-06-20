<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Ui\Component\Listing;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\Indexing\Ui\Component\Listing\EntitySyncNextActionDataProvider;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers EntitySyncNextActionDataProvider
 * @method DataProviderInterface instantiateTestObject(?array $arguments = null)
 * @method DataProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntitySyncNextActionDataProviderTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

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

        $this->implementationFqcn = EntitySyncNextActionDataProvider::class;
        $this->interfaceFqcn = DataProviderInterface::class;
        $this->constructorArgumentDefaults = [
            'name' => 'klevu_entity_sync_next_action_listing',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
            'entityType' => 'KLEVU_PRODUCT',
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGetData_ReturnsArray_WhenTargetIdNotSetInRequest(): void
    {
        $dataProvider = $this->instantiateTestObject();
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key: 'totalRecords', array: $result);
        $this->assertSame(expected: 0, actual: $result['totalRecords']);
        $this->assertArrayHasKey(key: 'items', array: $result);
        $this->assertCount(expectedCount: 0, haystack: $result['items']);
    }

    public function testGetData_ReturnsArray_WhenNoHistoryRecords(): void
    {
        $request = $this->objectManager->get(RequestInterface::class);
        $request->setParams(['target_id' => 1]);

        $dataProvider = $this->instantiateTestObject([
            'name' => 'klevu_entity_sync_next_action_listing',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
            'request' => $request,
            'entityType' => 'KLEVU_PRODUCT',
        ]);
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key: 'totalRecords', array: $result);
        $this->assertSame(expected: 0, actual: $result['totalRecords']);
        $this->assertArrayHasKey(key: 'items', array: $result);
        $this->assertCount(expectedCount: 0, haystack: $result['items']);
    }

    public function testGetData_ReturnsOnlyProductActionsTargetId(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->cleanIndexingEntities(apiKey: $apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::LAST_ACTION_TIMESTAMP => '2024-05-14 10:30:00',
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => 2,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::LAST_ACTION_TIMESTAMP => null,
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::LAST_ACTION_TIMESTAMP => '2024-05-14 10:32:00',
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::LAST_ACTION_TIMESTAMP => '2024-05-14 10:33:00',
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::LAST_ACTION_TIMESTAMP => '2024-05-14 10:34:00',
        ]);

        $request = $this->objectManager->get(RequestInterface::class);
        $request->setParams(['target_id' => 1]);

        $dataProvider = $this->instantiateTestObject([
            'name' => 'klevu_entity_sync_history',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
            'request' => $request,
            'entityType' => 'KLEVU_PRODUCT',
        ]);
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key: 'totalRecords', array: $result);
        $this->assertSame(expected: 2, actual: $result['totalRecords']);

        $this->assertArrayHasKey(key: 'items', array: $result);
        $this->assertCount(expectedCount: 2, haystack: $result['items']);

        $entity1Array = array_filter(
            array: $result['items'],
            callback: static fn (array $record): bool => (
                $record[IndexingEntity::TARGET_ID] === 1
                && $record[IndexingEntity::TARGET_PARENT_ID] === null
            ),
        );
        $entity1 = array_shift($entity1Array);
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $entity1[IndexingEntity::TARGET_ENTITY_TYPE] ?? null,
        );
        $this->assertSame(
            expected: 1,
            actual: $entity1[IndexingEntity::TARGET_ID] ?? null,
        );
        $this->assertNull(
            $entity1[IndexingEntity::TARGET_PARENT_ID] ?? null,
        );
        $this->assertSame(
            expected: Actions::UPDATE,
            actual: $entity1[IndexingEntity::NEXT_ACTION] ?? null,
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $entity1[IndexingEntity::LAST_ACTION] ?? null,
        );
        $this->assertSame(
            expected: '2024-05-14 10:30:00',
            actual: $entity1[IndexingEntity::LAST_ACTION_TIMESTAMP] ?? null,
        );
        $this->assertSame(
            expected: 1,
            actual: $entity1[IndexingEntity::IS_INDEXABLE] ?? null,
        );

        $entity2Array = array_filter(
            array: $result['items'],
            callback: static fn (array $record): bool => (
                $record[IndexingEntity::TARGET_ID] === 1
                && $record[IndexingEntity::TARGET_PARENT_ID] === 2
            ),
        );
        $entity2 = array_shift($entity2Array);
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $entity2[IndexingEntity::TARGET_ENTITY_TYPE] ?? null,
        );
        $this->assertSame(
            expected: 1,
            actual: $entity2[IndexingEntity::TARGET_ID] ?? null,
        );
        $this->assertSame(
            expected: 2,
            actual: $entity2[IndexingEntity::TARGET_PARENT_ID] ?? null,
        );
        $this->assertSame(
            expected: Actions::ADD,
            actual: $entity2[IndexingEntity::NEXT_ACTION] ?? null,
        );
        $this->assertSame(
            expected: Actions::NO_ACTION,
            actual: $entity2[IndexingEntity::LAST_ACTION] ?? null,
        );
        $this->assertNull(
            $entity2[IndexingEntity::LAST_ACTION_TIMESTAMP] ?? null,
        );
        $this->assertSame(
            expected: 0,
            actual: $entity2[IndexingEntity::IS_INDEXABLE] ?? null,
        );
    }
}
