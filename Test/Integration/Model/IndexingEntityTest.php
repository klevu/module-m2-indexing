<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity as IndexingEntityResourceModel;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection as IndexingEntityCollection;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoDbIsolation enabled
 * @method IndexingEntityInterface instantiateTestObject(?array $arguments = null)
 * @method IndexingEntityInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingEntityTest extends TestCase
{
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

        $this->implementationFqcn = IndexingEntity::class;
        $this->interfaceFqcn = IndexingEntityInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testCanSaveAndLoad(): void
    {
        $indexingEntity = $this->createIndexingEntity();
        /** @var AbstractModel $indexingEntityToLoad */
        $indexingEntityToLoad = $this->instantiateTestObject();
        $resourceModel = $this->instantiateSyncResourceModel();
        $resourceModel->load(
            object: $indexingEntityToLoad,
            value: $indexingEntity->getId(),
        );

        $this->assertSame(
            expected: (int)$indexingEntity->getId(),
            actual: $indexingEntityToLoad->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $indexingEntityToLoad->getTargetEntityType(),
        );
        $this->assertSame(
            expected: $indexingEntity->getTargetEntityType(),
            actual: $indexingEntityToLoad->getTargetEntityType(),
        );
        $this->assertNull(
            actual: $indexingEntityToLoad->getTargetEntitySubtype(),
        );
        $this->assertSame(
            expected: $indexingEntity->getTargetEntitySubtype(),
            actual: $indexingEntityToLoad->getTargetEntitySubtype(),
        );
        $this->assertSame(
            expected: 1,
            actual: $indexingEntity->getTargetId(),
        );
        $this->assertSame(
            expected: $indexingEntity->getTargetId(),
            actual: $indexingEntityToLoad->getTargetId(),
        );
        $this->assertStringContainsString(
            needle: 'klevu-js-api-key-',
            haystack: $indexingEntityToLoad->getApiKey(),
        );
        $this->assertSame(
            expected: $indexingEntity->getApiKey(),
            actual: $indexingEntityToLoad->getApiKey(),
        );
        $this->assertSame(
            expected: 'Update',
            actual: $indexingEntityToLoad->getNextAction()->value,
        );
        $this->assertSame(
            expected: $indexingEntity->getNextAction(),
            actual: $indexingEntityToLoad->getNextAction(),
        );
        $this->assertNull(
            actual: $indexingEntity->getLockTimestamp(),
        );
        $this->assertSame(
            expected: $indexingEntity->getLockTimestamp(),
            actual: $indexingEntityToLoad->getLockTimestamp(),
        );
        $this->assertSame(
            expected: 'Add',
            actual: $indexingEntityToLoad->getLastAction()->value,
        );
        $this->assertSame(
            expected: $indexingEntity->getLastAction(),
            actual: $indexingEntityToLoad->getLastAction(),
        );
        $this->assertNull(
            actual: $indexingEntity->getLastActionTimestamp(),
        );
        $this->assertSame(
            expected: $indexingEntity->getLastActionTimestamp(),
            actual: $indexingEntityToLoad->getLastActionTimestamp(),
        );
        $this->assertTrue(
            condition: $indexingEntity->getIsIndexable(),
            message: 'Is Indexable',
        );
        $this->assertFalse(
            condition: $indexingEntity->getRequiresUpdate(),
            message: 'Requires Update',
        );
        $this->assertSame(
            expected: $indexingEntity->getIsIndexable(),
            actual: $indexingEntityToLoad->getIsIndexable(),
        );
    }

    public function testCanSaveAndLoad_WithTimestamps(): void
    {
        $indexingEntity = $this->createIndexingEntity(data: [
            'target_id' => 100,
            'target_parent_id' => 500,
            'target_entity_subtype' => 'virtual',
            'lock_timestamp' => date(format: 'Y-m-d H:i:s', timestamp: time()),
            'last_action_timestamp' => date(format: 'Y-m-d H:i:s', timestamp: time() - 3600),
            'requires_update' => true,
        ]);
        /** @var AbstractModel $indexingEntityToLoad */
        $indexingEntityToLoad = $this->instantiateTestObject();
        $resourceModel = $this->instantiateSyncResourceModel();
        $resourceModel->load(
            object: $indexingEntityToLoad,
            value: $indexingEntity->getId(),
        );

        $this->assertSame(
            expected: (int)$indexingEntity->getId(),
            actual: $indexingEntityToLoad->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $indexingEntity->getTargetEntityType(),
        );
        $this->assertSame(
            expected: $indexingEntity->getTargetEntityType(),
            actual: $indexingEntityToLoad->getTargetEntityType(),
        );
        $this->assertSame(
            expected: 'virtual',
            actual: $indexingEntity->getTargetEntitySubtype(),
        );
        $this->assertSame(
            expected: $indexingEntity->getTargetEntitySubtype(),
            actual: $indexingEntityToLoad->getTargetEntitySubtype(),
        );
        $this->assertSame(
            expected: 100,
            actual: $indexingEntity->getTargetId(),
        );
        $this->assertSame(
            expected: $indexingEntity->getTargetId(),
            actual: $indexingEntityToLoad->getTargetId(),
        );
        $this->assertSame(
            expected: 500,
            actual: $indexingEntity->getTargetParentId(),
        );
        $this->assertSame(
            expected: $indexingEntity->getTargetParentId(),
            actual: $indexingEntityToLoad->getTargetParentId(),
        );
        $this->assertStringContainsString(
            needle: 'klevu-js-api-key-',
            haystack: $indexingEntity->getApiKey(),
        );
        $this->assertSame(
            expected: $indexingEntity->getApiKey(),
            actual: $indexingEntityToLoad->getApiKey(),
        );
        $this->assertSame(
            expected: 'Update',
            actual: $indexingEntityToLoad->getNextAction()->value,
        );
        $this->assertSame(
            expected: $indexingEntity->getNextAction(),
            actual: $indexingEntityToLoad->getNextAction(),
        );
        $this->assertNotNull(
            actual: $indexingEntity->getLockTimestamp(),
        );
        $this->assertSame(
            expected: $indexingEntity->getLockTimestamp(),
            actual: $indexingEntityToLoad->getLockTimestamp(),
        );
        $this->assertSame(
            expected: 'Add',
            actual: $indexingEntityToLoad->getLastAction()->value,
        );
        $this->assertSame(
            expected: $indexingEntity->getLastAction(),
            actual: $indexingEntityToLoad->getLastAction(),
        );
        $this->assertNotNull(actual: $indexingEntity->getLastActionTimestamp());
        $this->assertSame(
            expected: $indexingEntity->getLastActionTimestamp(),
            actual: $indexingEntityToLoad->getLastActionTimestamp(),
        );
        $this->assertTrue(
            condition: $indexingEntity->getIsIndexable(),
            message: 'Is Indexable',
        );
        $this->assertTrue(
            condition: $indexingEntity->getRequiresUpdate(),
            message: 'Requires Update',
        );
        $this->assertSame(
            expected: $indexingEntity->getIsIndexable(),
            actual: $indexingEntityToLoad->getIsIndexable(),
        );
    }

    public function testCanLoadMultipleIndexingEntities(): void
    {
        $indexingEntityA = $this->createIndexingEntity();
        $indexingEntityB = $this->createIndexingEntity(data: [
            'target_entity_type' => 'KLEVU_CATEGORY',
            'target_entity_subtype' => null,
            'target_id' => 2,
            'target_parent_id' => 3,
            'next_action' => Actions::ADD,
            'lock_timestamp' => date(format: 'Y-m-d H:i:s', timestamp: time()),
            'last_action' => Actions::NO_ACTION,
            'last_action_timestamp' => date(format: 'Y-m-d H:i:s', timestamp: time() - 3600),
            'requires_update' => true,
        ]);

        $collection = $this->objectManager->get(type: IndexingEntityCollection::class);
        $items = $collection->getItems();
        $this->assertContains(
            needle: (int)$indexingEntityA->getId(),
            haystack: array_keys($items),
        );
        $this->assertContains(
            needle: (int)$indexingEntityB->getId(),
            haystack: array_keys($items),
        );
    }

    /**
     * @param mixed[] $data
     *
     * @return AbstractModel|IndexingEntityInterface
     * @throws AlreadyExistsException
     */
    private function createIndexingEntity(array $data = []): AbstractModel|IndexingEntityInterface
    {
        $indexingEntity = $this->instantiateTestObject([]);
        $indexingEntity->setTargetEntityType(entityType: $data['target_entity_type'] ?? 'KLEVU_PRODUCT');
        $indexingEntity->setTargetEntitySubtype(entitySubtype: $data['target_entity_subtype'] ?? null);
        $indexingEntity->setTargetId(targetId: $data['target_id'] ?? 1);
        $indexingEntity->setTargetParentId(targetParentId: $data['target_parent_id'] ?? null);
        $indexingEntity->setApiKey(
            apiKey: $data['api_key'] ?? 'klevu-js-api-key-' . random_int(min: 0, max: 999999999),
        );
        $indexingEntity->setNextAction(nextAction: $data['next_action'] ?? Actions::UPDATE);
        $indexingEntity->setLockTimestamp(lockTimestamp: $data['lock_timestamp'] ?? null);
        $indexingEntity->setLastAction(lastAction: $data['last_action'] ?? Actions::ADD);
        $indexingEntity->setLastActionTimestamp(lastActionTimestamp: $data['last_action_timestamp'] ?? null);
        $indexingEntity->setIsIndexable(isIndexable: $data['is_indexable'] ?? true);
        $indexingEntity->setRequiresUpdate(requiresUpdate: $data['requires_update'] ?? false);

        $resourceModel = $this->instantiateSyncResourceModel();
        /** @var AbstractModel $indexingEntity */
        $resourceModel->save(object: $indexingEntity);

        return $indexingEntity;
    }

    /**
     * @return IndexingEntityResourceModel
     */
    private function instantiateSyncResourceModel(): IndexingEntityResourceModel
    {
        return $this->objectManager->get(type: IndexingEntityResourceModel::class);
    }
}
