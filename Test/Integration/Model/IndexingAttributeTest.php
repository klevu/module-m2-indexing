<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Model\ResourceModel\IndexingAttribute as IndexingAttributeResourceModel;
use Klevu\Indexing\Model\ResourceModel\IndexingAttribute\Collection as IndexingAttributeCollection;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
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
 * @method IndexingAttributeInterface instantiateTestObject(?array $arguments = null)
 * @method IndexingAttributeInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingAttributeTest extends TestCase
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

        $this->implementationFqcn = IndexingAttribute::class;
        $this->interfaceFqcn = IndexingAttributeInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testCanSaveAndLoad(): void
    {
        $indexingAttribute = $this->createIndexingAttribute();
        /** @var AbstractModel $indexingAttributeToLoad */
        $indexingAttributeToLoad = $this->instantiateTestObject();
        $resourceModel = $this->instantiateSyncResourceModel();
        $resourceModel->load(
            object: $indexingAttributeToLoad,
            value: $indexingAttribute->getId(),
        );

        $this->assertSame(
            expected: (int)$indexingAttribute->getId(),
            actual: $indexingAttributeToLoad->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $indexingAttributeToLoad->getTargetAttributeType(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getTargetAttributeType(),
            actual: $indexingAttributeToLoad->getTargetAttributeType(),
        );
        $this->assertSame(
            expected: 1,
            actual: $indexingAttribute->getTargetId(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getTargetId(),
            actual: $indexingAttributeToLoad->getTargetId(),
        );
        $this->assertSame(
            expected: 'attribute_code',
            actual: $indexingAttributeToLoad->getTargetCode(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getTargetCode(),
            actual: $indexingAttributeToLoad->getTargetCode(),
        );
        $this->assertStringContainsString(
            needle: 'klevu-js-api-key-',
            haystack: $indexingAttributeToLoad->getApiKey(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getApiKey(),
            actual: $indexingAttributeToLoad->getApiKey(),
        );
        $this->assertSame(
            expected: 'Update',
            actual: $indexingAttributeToLoad->getNextAction()->value,
        );
        $this->assertSame(
            expected: $indexingAttribute->getNextAction(),
            actual: $indexingAttributeToLoad->getNextAction(),
        );
        $this->assertNull(
            actual: $indexingAttribute->getLockTimestamp(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getLockTimestamp(),
            actual: $indexingAttributeToLoad->getLockTimestamp(),
        );
        $this->assertSame(
            expected: 'Add',
            actual: $indexingAttributeToLoad->getLastAction()->value,
        );
        $this->assertSame(
            expected: $indexingAttribute->getLastAction(),
            actual: $indexingAttributeToLoad->getLastAction(),
        );
        $this->assertNull(
            actual: $indexingAttribute->getLastActionTimestamp(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getLastActionTimestamp(),
            actual: $indexingAttributeToLoad->getLastActionTimestamp(),
        );
        $this->assertTrue(
            condition: $indexingAttribute->getIsIndexable(),
            message: 'Is Indexable',
        );
        $this->assertSame(
            expected: $indexingAttribute->getIsIndexable(),
            actual: $indexingAttributeToLoad->getIsIndexable(),
        );
    }

    public function testCanSaveAndLoad_WithTimestamps(): void
    {
        $indexingAttribute = $this->createIndexingAttribute(data: [
            'target_id' => 100,
            'target_code' => 'klevu_test_attribute',
            'lock_timestamp' => date(format: 'Y-m-d H:i:s', timestamp: time()),
            'last_action_timestamp' => date(format: 'Y-m-d H:i:s', timestamp: time() - 3600),
        ]);
        /** @var AbstractModel $indexingAttributeToLoad */
        $indexingAttributeToLoad = $this->instantiateTestObject();
        $resourceModel = $this->instantiateSyncResourceModel();
        $resourceModel->load(
            object: $indexingAttributeToLoad,
            value: $indexingAttribute->getId(),
        );

        $this->assertSame(
            expected: (int)$indexingAttribute->getId(),
            actual: $indexingAttributeToLoad->getId(),
        );
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $indexingAttribute->getTargetAttributeType(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getTargetAttributeType(),
            actual: $indexingAttributeToLoad->getTargetAttributeType(),
        );
        $this->assertSame(
            expected: 100,
            actual: $indexingAttribute->getTargetId(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getTargetId(),
            actual: $indexingAttributeToLoad->getTargetId(),
        );
        $this->assertSame(
            expected: 'klevu_test_attribute',
            actual: $indexingAttribute->getTargetCode(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getTargetCode(),
            actual: $indexingAttributeToLoad->getTargetCode(),
        );
        $this->assertStringContainsString(
            needle: 'klevu-js-api-key-',
            haystack: $indexingAttribute->getApiKey(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getApiKey(),
            actual: $indexingAttributeToLoad->getApiKey(),
        );
        $this->assertSame(
            expected: 'Update',
            actual: $indexingAttributeToLoad->getNextAction()->value,
        );
        $this->assertSame(
            expected: $indexingAttribute->getNextAction(),
            actual: $indexingAttributeToLoad->getNextAction(),
        );
        $this->assertNotNull(
            actual: $indexingAttribute->getLockTimestamp(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getLockTimestamp(),
            actual: $indexingAttributeToLoad->getLockTimestamp(),
        );
        $this->assertSame(
            expected: 'Add',
            actual: $indexingAttributeToLoad->getLastAction()->value,
        );
        $this->assertSame(
            expected: $indexingAttribute->getLastAction(),
            actual: $indexingAttributeToLoad->getLastAction(),
        );
        $this->assertNotNull(actual: $indexingAttribute->getLastActionTimestamp());
        $this->assertSame(
            expected: $indexingAttribute->getLastActionTimestamp(),
            actual: $indexingAttributeToLoad->getLastActionTimestamp(),
        );
        $this->assertTrue(
            condition: $indexingAttribute->getIsIndexable(),
        );
        $this->assertSame(
            expected: $indexingAttribute->getIsIndexable(),
            actual: $indexingAttributeToLoad->getIsIndexable(),
        );
    }

    public function testCanLoadMultipleIndexingEntities(): void
    {
        $indexingAttributeA = $this->createIndexingAttribute();
        $indexingAttributeB = $this->createIndexingAttribute(data: [
            'target_entity_type' => 'KLEVU_CATEGORY',
            'target_id' => 2,
            'target_code' => 'klevu_test_attribute',
            'next_action' => Actions::ADD,
            'lock_timestamp' => date(format: 'Y-m-d H:i:s', timestamp: time()),
            'last_action' => Actions::NO_ACTION,
            'last_action_timestamp' => date(format: 'Y-m-d H:i:s', timestamp: time() - 3600),
        ]);

        $collection = $this->objectManager->get(type: IndexingAttributeCollection::class);
        $items = $collection->getItems();
        $this->assertContains(
            needle: (int)$indexingAttributeA->getId(),
            haystack: array_keys($items),
        );
        $this->assertContains(
            needle: (int)$indexingAttributeB->getId(),
            haystack: array_keys($items),
        );
    }

    /**
     * @param mixed[] $data
     *
     * @return AbstractModel|IndexingAttributeInterface
     * @throws AlreadyExistsException
     */
    private function createIndexingAttribute(array $data = []): AbstractModel|IndexingAttributeInterface
    {
        $indexingAttribute = $this->instantiateTestObject([]);
        $indexingAttribute->setTargetAttributeType(attributeType: $data['target_attribute_type'] ?? 'KLEVU_PRODUCT');
        $indexingAttribute->setTargetId(targetId: $data['target_id'] ?? 1);
        $indexingAttribute->setTargetCode(targetCode: $data['target_code'] ?? 'attribute_code');
        $indexingAttribute->setApiKey(
            apiKey: $data['api_key'] ?? 'klevu-js-api-key-' . random_int(min: 0, max: 999999999),
        );
        $indexingAttribute->setNextAction(nextAction: $data['next_action'] ?? Actions::UPDATE);
        $indexingAttribute->setLockTimestamp(lockTimestamp: $data['lock_timestamp'] ?? null);
        $indexingAttribute->setLastAction(lastAction: $data['last_action'] ?? Actions::ADD);
        $indexingAttribute->setLastActionTimestamp(lastActionTimestamp: $data['last_action_timestamp'] ?? null);
        $indexingAttribute->setIsIndexable(isIndexable: $data['is_indexable'] ?? true);

        $resourceModel = $this->instantiateSyncResourceModel();
        /** @var AbstractModel $indexingAttribute */
        $resourceModel->save(object: $indexingAttribute);

        return $indexingAttribute;
    }

    /**
     * @return IndexingAttributeResourceModel
     */
    private function instantiateSyncResourceModel(): IndexingAttributeResourceModel
    {
        return $this->objectManager->get(type: IndexingAttributeResourceModel::class);
    }
}
