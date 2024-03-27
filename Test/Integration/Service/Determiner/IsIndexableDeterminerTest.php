<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Determiner;

use Klevu\Indexing\Service\Determiner\IsIndexableDeterminer;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
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
 * @covers \Klevu\Indexing\Service\Determiner\IsIndexableDeterminer::class
 * @method IsIndexableDeterminerInterface instantiateTestObject(?array $arguments = null)
 * @method IsIndexableDeterminerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IsIndexableDeterminerTest extends TestCase
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

        $this->implementationFqcn = IsIndexableDeterminer::class;
        $this->interfaceFqcn = IsIndexableDeterminerInterface::class;
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

    public function testExecute_ReturnsTrue_NoDeterminersDefined(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $mockEntity = $this->getMockBuilder(ProductInterface::class)
            ->getMock();

        $determiner = $this->instantiateTestObject();
        $this->assertTrue(
            condition: $determiner->execute(
                entity: $mockEntity,
                store: $storeFixture->get(),
            ),
            message: 'Is Indexable',
        );
    }

    public function testExecute_ReturnsFalse_DeterminersReturnsFalse(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $mockEntity = $this->getMockBuilder(ProductInterface::class)
            ->getMock();

        $mockDeterminer = $this->getMockBuilder(IsIndexableDeterminerInterface::class)
            ->getMock();
        $mockDeterminer->expects($this->once())
            ->method('execute')
            ->with($mockEntity, $storeFixture->get())
            ->willReturn(false);

        $determiner = $this->instantiateTestObject([
            'isIndexableDeterminers' => [
                'oosProductsIsIndexableDeterminer' => $mockDeterminer,
            ],
        ]);
        $this->assertFalse(
            condition: $determiner->execute(
                entity: $mockEntity,
                store: $storeFixture->get(),
            ),
            message: 'Is Indexable',
        );
    }
}
