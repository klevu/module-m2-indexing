<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Determiner;

use Klevu\Indexing\Service\Determiner\IsAttributeIndexableDeterminer;
use Klevu\IndexingApi\Service\Determiner\IsAttributeIndexableDeterminerInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\Determiner\IsAttributeIndexableDeterminer::class
 * @method IsAttributeIndexableDeterminerInterface instantiateTestObject(?array $arguments = null)
 * @method IsAttributeIndexableDeterminerInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IsAttributeIndexableDeterminerTest extends TestCase
{
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = IsAttributeIndexableDeterminer::class;
        $this->interfaceFqcn = IsAttributeIndexableDeterminerInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
    }

    public function testExecute_ReturnsTrue_NoDeterminersDefined(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $mockAttribute = $this->getMockBuilder(ProductAttributeInterface::class)
            ->getMock();

        $determiner = $this->instantiateTestObject();
        $this->assertTrue(
            condition: $determiner->execute(
                attribute: $mockAttribute,
                store: $storeFixture->get(),
            ),
            message: 'Is Indexable',
        );
    }

    public function testExecute_ReturnsFalse_DeterminersReturnsFalse(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $mockAttribute = $this->getMockBuilder(ProductAttributeInterface::class)
            ->getMock();

        $mockDeterminer = $this->getMockBuilder(IsAttributeIndexableDeterminerInterface::class)
            ->getMock();
        $mockDeterminer->expects($this->once())
            ->method('execute')
            ->with($mockAttribute, $storeFixture->get())
            ->willReturn(false);

        $determiner = $this->instantiateTestObject([
            'isIndexableDeterminers' => [
                'oosProductsIsIndexableDeterminer' => $mockDeterminer,
            ],
        ]);
        $this->assertFalse(
            condition: $determiner->execute(
                attribute: $mockAttribute,
                store: $storeFixture->get(),
            ),
            message: 'Is Indexable',
        );
    }
}
