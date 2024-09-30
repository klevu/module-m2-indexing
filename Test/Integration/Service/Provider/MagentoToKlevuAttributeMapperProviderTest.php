<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Mapper\MagentoToKlevuAttributeMapper;
use Klevu\Indexing\Service\Provider\MagentoToKlevuAttributeMapperProvider;
use Klevu\IndexingApi\Service\Provider\MagentoToKlevuAttributeMapperProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers MagentoToKlevuAttributeMapperProvider::class
 * @method MagentoToKlevuAttributeMapperProviderInterface instantiateTestObject(?array $arguments = null)
 * @method MagentoToKlevuAttributeMapperProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class MagentoToKlevuAttributeMapperProviderTest extends TestCase
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

        $this->implementationFqcn = MagentoToKlevuAttributeMapperProvider::class;
        $this->interfaceFqcn = MagentoToKlevuAttributeMapperProviderInterface::class;
        $mockMapper = $this->getMockBuilder(MagentoToKlevuAttributeMapper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->constructorArgumentDefaults = [
            'magentoToKlevuAttributeMappers' => [$mockMapper],
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testConstructor_ThrowsRuntimeException_WhenIncorrectTypeProvidedForMapper(): void
    {
        $this->expectException(RuntimeException::class);

        $this->instantiateTestObject([
            'magentoToKlevuAttributeMappers' => [
                'KLEVU_CMS' => new DataObject(),
            ],
        ]);
    }

    public function testGetByType_ReturnsAttributeMapper(): void
    {
        $categoryMapper = $this->objectManager->create(
            type: MagentoToKlevuAttributeMapper::class,
            arguments: [
                'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
                'prefix' => 'cat__',
                'attributeMapping' => [
                    'description' => 'desc',
                    'path' => 'listCategory',
                    'url_key' => 'url',
                ],
            ],
        );
        $productMapper = $this->objectManager->create(
            type: MagentoToKlevuAttributeMapper::class,
            arguments: [
                'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
                'attributeMapping' => [
                    'category_ids' => 'listCategory',
                    'description' => 'desc',
                    'image' => null,
                    'klevu_image' => 'image',
                    'klevu_rating' => 'rating',
                    'klevu_rating_count' => 'ratingCount',
                    'quantity_and_stock_status' => 'inStock',
                    'short_description' => 'shortDesc',
                    'url_key' => 'url',
                ],
            ],
        );

        $provider = $this->instantiateTestObject([
            'magentoToKlevuAttributeMappers' => [
                'KLEVU_CATEGORY' => $categoryMapper,
                'KLEVU_PRODUCT' => $productMapper,
            ],
        ]);
        $mapper = $provider->getByType(entityType: 'KLEVU_CATEGORY');
        $this->assertSame(
            expected: 'name',
            actual: $mapper->getByCode('name'),
        );
        $this->assertSame(
            expected: 'listCategory',
            actual: $mapper->getByCode('path'),
        );
        $this->assertSame(
            expected: 'desc',
            actual: $mapper->getByCode('description'),
        );
        $this->assertSame(
            expected: 'url',
            actual: $mapper->getByCode('url_key'),
        );
        $this->assertSame(
            expected: 'cat__klevu_image',
            actual: $mapper->getByCode('klevu_image'),
        );

        $mapper = $provider->getByType(entityType: 'KLEVU_PRODUCT');
        $this->assertSame(
            expected: 'name',
            actual: $mapper->getByCode('name'),
        );
        $this->assertSame(
            expected: 'listCategory',
            actual: $mapper->getByCode('category_ids'),
        );
        $this->assertSame(
            expected: 'desc',
            actual: $mapper->getByCode('description'),
        );
        $this->assertSame(
            expected: 'url',
            actual: $mapper->getByCode('url_key'),
        );
        $this->assertSame(
            expected: 'image',
            actual: $mapper->getByCode('klevu_image'),
        );
    }

    public function testGet_ReturnsAllMappers(): void
    {
        $categoryMapper = $this->objectManager->create(
            type: MagentoToKlevuAttributeMapper::class,
            arguments: [
                'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
                'prefix' => 'cat__',
                'attributeMapping' => [
                    'description' => 'desc',
                    'path' => 'listCategory',
                    'url_key' => 'url',
                ],
            ],
        );
        $productMapper = $this->objectManager->create(
            type: MagentoToKlevuAttributeMapper::class,
            arguments: [
                'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
                'attributeMapping' => [
                    'category_ids' => 'listCategory',
                    'description' => 'desc',
                    'image' => null,
                    'klevu_image' => 'image',
                    'klevu_rating' => 'rating',
                    'klevu_rating_count' => 'ratingCount',
                    'quantity_and_stock_status' => 'inStock',
                    'short_description' => 'shortDesc',
                    'url_key' => 'url',
                ],
            ],
        );

        $provider = $this->instantiateTestObject([
            'magentoToKlevuAttributeMappers' => [
                'KLEVU_CATEGORY' => $categoryMapper,
                'KLEVU_PRODUCT' => $productMapper,
            ],
        ]);
        $result = $provider->get();

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY', array: $result);
        $categoryMapper = $result['KLEVU_CATEGORY'];
        $this->assertSame(
            expected: 'name',
            actual: $categoryMapper->getByCode('name'),
        );
        $this->assertSame(
            expected: 'listCategory',
            actual: $categoryMapper->getByCode('path'),
        );
        $this->assertSame(
            expected: 'desc',
            actual: $categoryMapper->getByCode('description'),
        );
        $this->assertSame(
            expected: 'url',
            actual: $categoryMapper->getByCode('url_key'),
        );
        $this->assertSame(
            expected: 'cat__klevu_image',
            actual: $categoryMapper->getByCode('klevu_image'),
        );

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result);
        $productMapper = $result['KLEVU_PRODUCT'];
        $this->assertSame(
            expected: 'name',
            actual: $productMapper->getByCode('name'),
        );
        $this->assertSame(
            expected: 'listCategory',
            actual: $productMapper->getByCode('category_ids'),
        );
        $this->assertSame(
            expected: 'desc',
            actual: $productMapper->getByCode('description'),
        );
        $this->assertSame(
            expected: 'url',
            actual: $productMapper->getByCode('url_key'),
        );
        $this->assertSame(
            expected: 'image',
            actual: $productMapper->getByCode('klevu_image'),
        );
    }

    public function testGetByType_ReturnsDefaultMapper_WhenRequestKeyDoesNotExist(): void
    {
        $productMapper = $this->objectManager->create(
            type: MagentoToKlevuAttributeMapper::class,
            arguments: [
                'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
                'attributeMapping' => [
                    'category_ids' => 'listCategory',
                    'description' => 'desc',
                    'image' => null,
                    'klevu_image' => 'image',
                    'klevu_rating' => 'rating',
                    'klevu_rating_count' => 'ratingCount',
                    'quantity_and_stock_status' => 'inStock',
                    'short_description' => 'shortDesc',
                    'url_key' => 'url',
                ],
            ],
        );

        $provider = $this->instantiateTestObject([
            'magentoToKlevuAttributeMappers' => [
                'KLEVU_PRODUCT' => $productMapper,
            ],
        ]);
        $result = $provider->getByType(entityType: 'KLEVU_CATEGORY');

        // There is no mapping in the default mapper
        $this->assertSame(
            expected: 'name',
            actual: $result->getByCode('name'),
        );
        $this->assertSame(
            expected: 'path',
            actual: $result->getByCode('path'),
        );
        $this->assertSame(
            expected: 'description',
            actual: $result->getByCode('description'),
        );
        $this->assertSame(
            expected: 'url_key',
            actual: $result->getByCode('url_key'),
        );
        $this->assertSame(
            expected: 'klevu_image',
            actual: $result->getByCode('klevu_image'),
        );
    }
}
