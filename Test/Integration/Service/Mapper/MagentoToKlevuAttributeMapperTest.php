<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Mapper;

use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\Indexing\Service\Mapper\MagentoToKlevuAttributeMapper;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Catalog\Api\Data\CategoryAttributeInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers MagentoToKlevuAttributeMapper
 * @method MagentoToKlevuAttributeMapperInterface instantiateTestObject(?array $arguments = null)
 * @method MagentoToKlevuAttributeMapperInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class MagentoToKlevuAttributeMapperTest extends TestCase
{
    use AttributeTrait;
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

        $this->implementationFqcn = MagentoToKlevuAttributeMapper::class;
        $this->interfaceFqcn = MagentoToKlevuAttributeMapperInterface::class;
        $this->constructorArgumentDefaults = [
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
        ];
        $this->objectManager = Bootstrap::getObjectManager();
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
    }

    public function testGet_ReturnsOriginalAttributeCode_WhenNoMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $result = $mapper->get($magentoAttribute);

        $this->assertSame(expected: $magentoAttribute->getAttributeCode(), actual: $result);
    }

    public function testGet_ReturnsNewAttributeCode_WhenMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->get($magentoAttribute);

        $this->assertSame(expected: 'another_name', actual: $result);
    }

    public function testGet_ThrowsAttributeMappingMissingException_WhenMappingIsMissing_withoutPrefix(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);

        $attributeRepository = $this->objectManager->get(AttributeRepositoryInterface::class);
        $descriptionAttribute = $attributeRepository->get(
            entityTypeCode: ProductAttributeInterface::ENTITY_TYPE_CODE,
            attributeCode: 'description',
        );

        $this->expectException(AttributeMappingMissingException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Attribute mapping for Magento attribute %s is missing. '
                . 'Klevu attribute %s is mapped to Magento attribute %s. '
                . '2 Magento attributes can not be mapped to the same Klevu attribute. '
                . 'Either add mapping for Magento attribute %s or set it not to be indexable.',
                'description',
                'description',
                'klevu_test_attribute',
                'description',
            ),
        );

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'attributeMapping' => [
                'klevu_test_attribute' => 'description',
            ],
        ]);
        $mapper->get($descriptionAttribute);
    }

    public function testGet_ThrowsAttributeMappingMissingException_WhenMappingIsMissing_withPrefix(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);

        $attributeRepository = $this->objectManager->get(AttributeRepositoryInterface::class);
        $descriptionAttribute = $attributeRepository->get(
            entityTypeCode: CategoryAttributeInterface::ENTITY_TYPE_CODE,
            attributeCode: 'description',
        );

        $this->expectException(AttributeMappingMissingException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Attribute mapping for Magento attribute %s is missing. '
                . 'Klevu attribute %s is mapped to Magento attribute %s. '
                . '2 Magento attributes can not be mapped to the same Klevu attribute. '
                . 'Either add mapping for Magento attribute %s or set it not to be indexable.',
                'description',
                'cat-description',
                'klevu_test_attribute',
                'description',
            ),
        );

        $mapper = $this->instantiateTestObject([
            'entityType' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
            'prefix' => 'cat-',
            'attributeMapping' => [
                'klevu_test_attribute' => 'cat-description',
            ],
        ]);
        $mapper->get($descriptionAttribute);
    }

    public function testGetReturnsNewAttributeCode_WhenPrefixSet(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'prefix' => 'prod-',
        ]);
        $result = $mapper->get($magentoAttribute);

        $this->assertSame(expected: 'prod-klevu_test_attribute', actual: $result);
    }

    public function testGetReturnsNewAttributeCode_WhenMappingAndPrefixSet(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'prefix' => 'prod-',
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->get($magentoAttribute);

        $this->assertSame(expected: 'another_name', actual: $result);
    }

    public function testReverse_ReturnsOriginalAttributeCode_WhenNoMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $result = $mapper->reverse($magentoAttribute->getAttributeCode());

        $this->assertSame(expected: $magentoAttribute->getAttributeId(), actual: $result->getAttributeId());
    }

    public function testReverse_ReturnsNewAttributeCode_WhenMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->reverse('another_name');

        $this->assertSame(expected: $magentoAttribute->getAttributeId(), actual: $result->getAttributeId());
    }

    public function testReverse_ThrowsException_WhenAttributeNameIsNotMappedAndDoesNotExist(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $mapper->reverse('_IH*£END');
    }

    public function testReverse_ReturnsNewAttributeCode_WhenPrefixSet(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'prefix' => 'prod-',
        ]);
        $result = $mapper->reverse('prod-klevu_test_attribute');

        $this->assertSame(expected: $magentoAttribute->getAttributeId(), actual: $result->getAttributeId());
    }

    public function testReverse_ReturnsNewAttributeCode_WhenMappingAndPrefixSet(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'prefix' => 'prod-',
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->reverse('another_name');

        $this->assertSame(expected: $magentoAttribute->getAttributeId(), actual: $result->getAttributeId());
    }

    public function testGetByCode_ReturnsOriginalAttributeCode_WhenNoMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $result = $mapper->getByCode($magentoAttribute->getAttributeCode());

        $this->assertSame(expected: $magentoAttribute->getAttributeCode(), actual: $result);
    }

    public function testGetByCode_ReturnsNewAttributeCode_WhenMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->getByCode($magentoAttribute->getAttributeCode());

        $this->assertSame(expected: 'another_name', actual: $result);
    }

    public function testGetByCode_ThrowsAttributeMappingMissingException_WhenMappingIsMissing_withoutPrefix(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);

        $attributeRepository = $this->objectManager->get(AttributeRepositoryInterface::class);
        $descriptionAttribute = $attributeRepository->get(
            entityTypeCode: ProductAttributeInterface::ENTITY_TYPE_CODE,
            attributeCode: 'description',
        );

        $this->expectException(AttributeMappingMissingException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Attribute mapping for Magento attribute %s is missing. '
                . 'Klevu attribute %s is mapped to Magento attribute %s. '
                . '2 Magento attributes can not be mapped to the same Klevu attribute. '
                . 'Either add mapping for Magento attribute %s or set it not to be indexable.',
                'description',
                'description',
                'klevu_test_attribute',
                'description',
            ),
        );

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'attributeMapping' => [
                'klevu_test_attribute' => 'description',
            ],
        ]);
        $mapper->getByCode($descriptionAttribute->getAttributeCode());
    }

    public function testGetByCode_ThrowsAttributeMappingMissingException_WhenMappingIsMissing_withPrefix(): void
    {
        $attributeCode = 'klevu_test_attribute';
        $this->createAttribute([
            'code' => $attributeCode,
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);

        $attributeRepository = $this->objectManager->get(AttributeRepositoryInterface::class);
        $descriptionAttribute = $attributeRepository->get(
            entityTypeCode: CategoryAttributeInterface::ENTITY_TYPE_CODE,
            attributeCode: CategoryInterface::KEY_NAME,
        );

        $this->expectException(AttributeMappingMissingException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Attribute mapping for Magento attribute %s is missing. '
                . 'Klevu attribute %s is mapped to Magento attribute %s. '
                . '2 Magento attributes can not be mapped to the same Klevu attribute. '
                . 'Either add mapping for Magento attribute %s or set it not to be indexable.',
                CategoryInterface::KEY_NAME,
                'cat-' . CategoryInterface::KEY_NAME,
                $attributeCode,
                CategoryInterface::KEY_NAME,
            ),
        );

        $mapper = $this->instantiateTestObject([
            'entityType' => CategoryAttributeInterface::ENTITY_TYPE_CODE,
            'prefix' => 'cat-',
            'attributeMapping' => [
                $attributeCode => 'cat-' . CategoryInterface::KEY_NAME,
            ],
        ]);
        $mapper->getByCode($descriptionAttribute->getAttributeCode());
    }

    public function testGetByCode_ReturnsNewAttributeCode_WhenPrefixSet(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'prefix' => 'prod-',
        ]);
        $result = $mapper->getByCode($magentoAttribute->getAttributeCode());

        $this->assertSame(expected: 'prod-klevu_test_attribute', actual: $result);
    }

    public function testGetByCode_ReturnsNewAttributeCode_WhenMappingAndPrefixSet(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'prefix' => 'prod-',
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->getByCode($magentoAttribute->getAttributeCode());

        $this->assertSame(expected: 'another_name', actual: $result);
    }

    public function testReverseForCode_ReturnsOriginalAttribute_WhenNoMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
        ]);
        $result = $mapper->reverseForCode($magentoAttribute->getAttributeCode());

        $this->assertSame(expected: $magentoAttribute->getAttributeCode(), actual: $result);
    }

    public function testReverseForCode_ReturnsNewAttributeCode_WhenMappingSetUp(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->reverseForCode('another_name');

        $this->assertSame(expected: $magentoAttribute->getAttributeCode(), actual: $result);
    }

    public function testReverseForCode_ReturnsNewAttributeCode_WhenPrefixSet(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'prefix' => 'prod-',
        ]);
        $result = $mapper->reverseForCode('prod-klevu_test_attribute');

        $this->assertSame(expected: $magentoAttribute->getAttributeCode(), actual: $result);
    }

    public function testReverseForCode_ReturnsNewAttributeCode_WhenMappingAndPrefixSet(): void
    {
        $this->createAttribute([
            'code' => 'klevu_test_attribute',
            'label' => 'TEST TEXT ATTRIBUTE',
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $magentoAttribute = $attributeFixture->getAttribute();

        $mapper = $this->instantiateTestObject([
            'entityType' => ProductAttributeInterface::ENTITY_TYPE_CODE,
            'prefix' => 'prod-',
            'attributeMapping' => [
                'klevu_test_attribute' => 'another_name',
            ],
        ]);
        $result = $mapper->reverseForCode('another_name');

        $this->assertSame(expected: $magentoAttribute->getAttributeCode(), actual: $result);
    }
}
