<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Service\EntityIndexingDeleteRecordCreatorService;
use Klevu\IndexingApi\Service\EntityIndexingDeleteRecordCreatorServiceInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Catalog\ProductFixturePool;

// phpcs:disabled Generic.Files.LineLength.TooLong
/**
 * @covers EntityIndexingDeleteRecordCreatorService::class
 * @method EntityIndexingDeleteRecordCreatorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityIndexingDeleteRecordCreatorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityIndexingDeleteRecordCreatorServiceTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
    use ObjectInstantiationTrait;
    use PageTrait;
    use ProductTrait;
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

        $this->implementationFqcn = EntityIndexingDeleteRecordCreatorService::class;
        $this->interfaceFqcn = EntityIndexingDeleteRecordCreatorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);
        $this->pageFixturesPool = $this->objectManager->get(PageFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pageFixturesPool->rollback();
        $this->productFixturePool->rollback();
    }

    public function testExecute_ReturnsIndexingRecord_WithEntity(): void
    {
        $this->createProduct();
        $productFixture = $this->productFixturePool->get('test_product');
        $product = $productFixture->getProduct();

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            recordId: 1,
            entityId: (int)$productFixture->getId(),
        );

        $this->assertSame(
            expected: (int)$product->getId(),
            actual: (int)$result->getEntityId(),
        );
        $this->assertNull(actual: $result->getParentId());
    }

    /**
     * @magentoDbIsolation disabled
     */
    public function testExecute_ReturnsIndexingRecord_WithAllData(): void
    {
        $this->createProduct();
        $productFixture1 = $this->productFixturePool->get('test_product');
        $product1 = $productFixture1->getProduct();

        $this->createProduct([
            'key' => 'test_parent_product',
        ]);
        $productFixture2 = $this->productFixturePool->get('test_parent_product');
        $product2 = $productFixture2->getProduct();

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            recordId: 1,
            entityId: (int)$product1->getId(),
            parentId: (int)$product2->getId(),
        );

        $this->assertSame(
            expected: (int)$product1->getId(),
            actual: (int)$result->getEntityId(),
        );
        $this->assertSame(
            expected: (int)$product2->getId(),
            actual: (int)$result->getParentId(),
        );
    }
}
