<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Action\SetIndexingEntitiesToRequireUpdateAction;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToRequireUpdateActionInterface;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\GeneratorTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SetIndexingEntitiesToRequireUpdateActionTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
    use GeneratorTrait;
    use IndexingEntitiesTrait;
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

        $this->implementationFqcn = SetIndexingEntitiesToRequireUpdateAction::class;
        $this->interfaceFqcn = SetIndexingEntitiesToRequireUpdateActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith ["KLEVU_CATEGORY"]
     *           ["KLEVU_CMS"]
     *           ["KLEVU_PRODUCT"]
     */
    public function testExecute_SetsIndexingEntityToRequiresUpdate(string $type): void
    {
        $apiKey = 'klevu-9' . str_repeat((string)random_int(0, 9), 10);
        $this->cleanIndexingEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::UPDATE,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::REQUIRES_UPDATE => true,
            IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::REQUIRES_UPDATE => false,
            IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::REQUIRES_UPDATE => true,
            IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
        ]);
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => $type,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::REQUIRES_UPDATE => false,
            IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [],
        ]);

        $indexingEntitiesBefore = $this->getIndexingEntities(
            type: $type,
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 4, haystack: $indexingEntitiesBefore);

        /** @var SetIndexingEntitiesToRequireUpdateActionInterface $action */
        $action = $this->instantiateTestObject();
        $action->execute(
            entityType: $type,
            apiKey: $apiKey,
            targetIds: [1, 2, 3, 4],
            origValues: [
                'foo' => 'bar',
            ],
        );

        $indexingEntitiesAfter = $this->getIndexingEntities(
            type: $type,
            apiKey: $apiKey,
        );

        $indexingEntityArray1 = $this->filterIndexEntities($indexingEntitiesAfter, 1);
        $indexingEntity1 = array_shift($indexingEntityArray1);
        $this->assertTrue($indexingEntity1->getIsIndexable());
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingEntity1->getNextAction());
        $this->assertTrue(condition: $indexingEntity1->getRequiresUpdate());
        $this->assertSame(
            expected: [
                'foo' => 'bar',
            ],
            actual: $indexingEntity1->getRequiresUpdateOrigValues(),
        );

        $indexingEntityArray2 = $this->filterIndexEntities($indexingEntitiesAfter, 2);
        $indexingEntity2 = array_shift($indexingEntityArray2);
        $this->assertTrue($indexingEntity2->getIsIndexable());
        $this->assertSame(expected: Actions::ADD, actual: $indexingEntity2->getNextAction());
        $this->assertTrue(condition: $indexingEntity2->getRequiresUpdate());
        $this->assertSame(
            expected: [
                'foo' => 'bar',
            ],
            actual: $indexingEntity2->getRequiresUpdateOrigValues(),
        );

        $indexingEntityArray3 = $this->filterIndexEntities($indexingEntitiesAfter, 3);
        $indexingEntity3 = array_shift($indexingEntityArray3);
        $this->assertFalse($indexingEntity3->getIsIndexable());
        $this->assertSame(expected: Actions::DELETE, actual: $indexingEntity3->getNextAction());
        $this->assertTrue(condition: $indexingEntity3->getRequiresUpdate());
        $this->assertSame(
            expected: [
                'foo' => 'bar',
            ],
            actual: $indexingEntity3->getRequiresUpdateOrigValues(),
        );

        $indexingEntityArray4 = $this->filterIndexEntities($indexingEntitiesAfter, 4);
        $indexingEntity4 = array_shift($indexingEntityArray4);
        $this->assertFalse($indexingEntity4->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingEntity4->getNextAction());
        $this->assertTrue(condition: $indexingEntity4->getRequiresUpdate());
        $this->assertSame(
            expected: [
                'foo' => 'bar',
            ],
            actual: $indexingEntity4->getRequiresUpdateOrigValues(),
        );

        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param int $entityId
     *
     * @return IndexingEntityInterface[]
     */
    private function filterIndexEntities(array $indexingEntities, int $entityId): array
    {
        return array_filter(
            array: $indexingEntities,
            callback: static function (IndexingEntityInterface $indexingEntity) use ($entityId) {
                return $entityId === (int)$indexingEntity->getTargetId();
            },
        );
    }
}
