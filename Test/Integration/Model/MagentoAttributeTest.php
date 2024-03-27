<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Model\MagentoAttribute;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Model\MagentoAttribute::class
 * @method MagentoAttributeInterface instantiateTestObject(?array $arguments = null)
 * @method MagentoAttributeInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class MagentoAttributeTest extends TestCase
{
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

        $this->implementationFqcn = MagentoAttribute::class;
        $this->interfaceFqcn = MagentoAttributeInterface::class;
        $this->constructorArgumentDefaults = [
            'attributeId' => 1,
            'attributeCode' => 'klevu_test_attribute_1',
            'apiKey' => 'klevu-js-api-key',
            'isIndexable' => true,
            'klevuAttributeName' => 'klevuAttributeName',
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testMagentoEntity_ReturnsExpectedValues(): void
    {
        $attribute = $this->instantiateTestObject([
            'attributeId' => 123,
            'attributeCode' => 'klevu_test_attribute_123',
            'apiKey' => 'klevu-js-api-key-test',
            'isIndexable' => false,
            'klevuAttributeName' => 'klevuAttributeName',
        ]);

        $this->assertSame(expected: 123, actual: $attribute->getAttributeId());
        $this->assertSame(expected: 'klevu_test_attribute_123', actual: $attribute->getAttributeCode());
        $this->assertSame(expected: 'klevu-js-api-key-test', actual: $attribute->getApiKey());
        $this->assertFalse(condition: $attribute->isIndexable());
        $this->assertSame(expected: 'klevuAttributeName', actual: $attribute->getKlevuAttributeName());
    }

    public function testSetIsIndexable_SetsNewValue(): void
    {
        $attribute = $this->instantiateTestObject([
            'attributeId' => 456,
            'attributeCode' => 'klevu_test_attribute_456',
            'apiKey' => 'klevu-js-api-key-test',
            'isIndexable' => false,
            'klevuAttributeName' => 'klevuAttributeName',
        ]);

        $this->assertSame(expected: 456, actual: $attribute->getAttributeId());
        $this->assertSame(expected: 'klevu_test_attribute_456', actual: $attribute->getAttributeCode());
        $this->assertSame(expected: 'klevu-js-api-key-test', actual: $attribute->getApiKey());
        $this->assertFalse(condition: $attribute->isIndexable(), message: 'IsIndexable before Change');
        $this->assertSame(expected: 'klevuAttributeName', actual: $attribute->getKlevuAttributeName());

        $attribute->setIsIndexable(isIndexable: true);

        $this->assertSame(expected: 456, actual: $attribute->getAttributeId());
        $this->assertSame(expected: 'klevu-js-api-key-test', actual: $attribute->getApiKey());
        $this->assertTrue(condition: $attribute->isIndexable(), message: 'IsIndexable after Change');
        $this->assertSame(expected: 'klevuAttributeName', actual: $attribute->getKlevuAttributeName());
    }
}
