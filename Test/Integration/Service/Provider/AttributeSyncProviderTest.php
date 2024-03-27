<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\AttributeSyncProvider;
use Klevu\IndexingApi\Service\Provider\AttributesToSyncProviderInterface;
use Klevu\IndexingApi\Service\Provider\AttributeSyncProviderInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface;
use Klevu\PhpSDK\Model\Indexing\AttributeFactory;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers AttributeSyncProvider
 * @method AttributeSyncProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeSyncProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeSyncProviderTest extends TestCase
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

        $this->implementationFqcn = AttributeSyncProvider::class;
        $this->interfaceFqcn = AttributeSyncProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGet_WithoutAttributeType_ReturnsAllTypes(): void
    {
        $attributeFactory = $this->objectManager->get(AttributeFactory::class);
        $categoryAttribute = $attributeFactory->create(data: [
            'attributeName' => 'cat_attribute_name',
            'datatype' => DataType::STRING,
            'label' => [
                'default' => 'CATEGORY ATTRIBUTE LABEL',
            ],
            'searchable' => false,
            'filterable' => true,
            'returnable' => false,
        ]);

        $mockCategoryProvider = $this->getMockBuilder(AttributesToSyncProviderInterface::class)
            ->getMock();
        $mockCategoryProvider->expects($this->once())
            ->method('get')
            ->willReturn([$categoryAttribute]);

        $productAttribute = $attributeFactory->create(data: [
            'attributeName' => 'attribute_name',
            'datatype' => DataType::STRING,
            'label' => [
                'default' => 'PRODUCT ATTRIBUTE LABEL',
            ],
            'searchable' => true,
            'filterable' => true,
            'returnable' => true,
        ]);

        $mockProductProvider = $this->getMockBuilder(AttributesToSyncProviderInterface::class)
            ->getMock();
        $mockProductProvider->expects($this->once())
            ->method('get')
            ->willReturn([$productAttribute]);

        $provider = $this->instantiateTestObject([
            'attributeProviders' => [
                'KLEVU_CATEGORIES' => $mockCategoryProvider,
                'KLEVU_PRODUCTS' => $mockProductProvider,
            ],
        ]);
        $result = $provider->get();

        $filteredResults = array_filter(
            array: $result,
            callback: static fn (AttributeInterface $attribute): bool => (
                $attribute->getAttributeName() === 'cat_attribute_name'
            ),
        );
        $categoryAttribute = array_shift($filteredResults);
        $this->assertInstanceOf(expected: AttributeInterface::class, actual: $categoryAttribute);
        $this->assertSame(expected: 'cat_attribute_name', actual: $categoryAttribute->getAttributeName());
        $this->assertSame(expected: DataType::STRING->value, actual: $categoryAttribute->getDatatype());
        $this->assertFalse(condition: $categoryAttribute->isSearchable());
        $this->assertTrue(condition: $categoryAttribute->isFilterable());
        $this->assertFalse(condition: $categoryAttribute->isReturnable());
        $label = $categoryAttribute->getLabel();
        $this->assertIsArray($label);
        $this->assertArrayHasKey(key: 'default', array: $label);
        $this->assertSame(expected: 'CATEGORY ATTRIBUTE LABEL', actual: $label['default']);
    }

    public function testGet_WithAttributeType_OnlyReturnsThatType(): void
    {
        $attributeFactory = $this->objectManager->get(AttributeFactory::class);
        $categoryAttribute = $attributeFactory->create(data: [
            'attributeName' => 'cat_attribute_name',
            'datatype' => DataType::STRING,
            'label' => [
                'default' => 'CATEGORY ATTRIBUTE LABEL',
            ],
            'searchable' => true,
            'filterable' => true,
            'returnable' => true,
        ]);

        $mockCategoryProvider = $this->getMockBuilder(AttributesToSyncProviderInterface::class)
            ->getMock();
        $mockCategoryProvider->expects($this->once())
            ->method('get')
            ->willReturn([$categoryAttribute]);

        $mockProductProvider = $this->getMockBuilder(AttributesToSyncProviderInterface::class)
            ->getMock();
        $mockProductProvider->expects($this->never())
            ->method('get');

        $provider = $this->instantiateTestObject([
            'attributeProviders' => [
                'KLEVU_CATEGORIES' => $mockCategoryProvider,
                'KLEVU_PRODUCTS' => $mockProductProvider,
            ],
        ]);
        $result = $provider->get('KLEVU_CATEGORIES');

        $filteredResults = array_filter(
            array: $result,
            callback: static fn (AttributeInterface $attribute): bool => (
                $attribute->getAttributeName() === 'cat_attribute_name'
            ),
        );
        $categoryAttribute = array_shift($filteredResults);
        $this->assertInstanceOf(expected: AttributeInterface::class, actual: $categoryAttribute);
        $this->assertSame(expected: 'cat_attribute_name', actual: $categoryAttribute->getAttributeName());
        $this->assertSame(expected: DataType::STRING->value, actual: $categoryAttribute->getDatatype());
        $this->assertTrue(condition: $categoryAttribute->isSearchable());
        $this->assertTrue(condition: $categoryAttribute->isFilterable());
        $this->assertTrue(condition: $categoryAttribute->isReturnable());
        $label = $categoryAttribute->getLabel();
        $this->assertIsArray($label);
        $this->assertArrayHasKey(key: 'default', array: $label);
        $this->assertSame(expected: 'CATEGORY ATTRIBUTE LABEL', actual: $label['default']);
    }
}
