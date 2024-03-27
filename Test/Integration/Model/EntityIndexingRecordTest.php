<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Model\EntityIndexingRecord;
use Klevu\IndexingApi\Model\EntityIndexingRecordInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

/**
 * @covers EntityIndexingRecord::class
 * @method EntityIndexingRecordInterface instantiateTestObject(?array $arguments = null)
 * @method EntityIndexingRecordInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityIndexingRecordTest extends TestCase
{
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;
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

        $this->implementationFqcn = EntityIndexingRecord::class;
        $this->interfaceFqcn = EntityIndexingRecordInterface::class;
        $this->constructorArgumentDefaults = [
            'recordId' => 1,
            'entity' => $this->getMockBuilder(ProductInterface::class)->getMock(),
        ];
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGetRecordId_ReturnsInt(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $model = $this->instantiateTestObject([
            'recordId' => 1,
            'entity' => $productFixture->getProduct(),
        ]);
        $recordId = $model->getRecordId();
        $this->assertSame(expected: 1, actual: $recordId);

        $parent = $model->getParent();
        $this->assertNull(actual: $parent);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGetEntity_ReturnsProductInterface(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');

        $model = $this->instantiateTestObject([
            'recordId' => 1,
            'entity' => $productFixture->getProduct(),
        ]);
        $entity = $model->getEntity();
        $this->assertSame(expected: (int)$productFixture->getId(), actual: (int)$entity->getId());

        $parent = $model->getParent();
        $this->assertNull(actual: $parent);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testGetParent_ReturnsProductInterface(): void
    {
        $this->createProduct();
        $productFixture1 = $this->productFixturePool->get('test_product');

        $this->createProduct([
            'key' => 'test_parent_product',
        ]);
        $productFixture2 = $this->productFixturePool->get('test_parent_product');

        $model = $this->instantiateTestObject([
            'recordId' => 1,
            'entity' => $productFixture1->getProduct(),
            'parent' => $productFixture2->getProduct(),
        ]);
        $result = $model->getParent();

        $this->assertSame(expected: (int)$productFixture2->getId(), actual: (int)$result->getId());
    }
}
