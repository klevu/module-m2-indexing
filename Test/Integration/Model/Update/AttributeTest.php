<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model\Update;

use Klevu\Indexing\Model\Update\Attribute as AttributeUpdate;
use Klevu\Indexing\Model\Update\AttributeFactory;
use Klevu\IndexingApi\Model\Update\AttributeInterface as AttributeUpdateInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Model\Update\Attribute::class
 * @method AttributeUpdateInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeUpdateInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeTest extends TestCase
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

        $this->implementationFqcn = AttributeUpdate::class;
        $this->interfaceFqcn = AttributeUpdateInterface::class;
        $this->constructorArgumentDefaults = [
            'data' => [
                'attributeType' => 'KLEVU_PRODUCT',
                'attributeIds' => [1, 2, 3],
                'storeIds' => [1, 2],
            ],
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith ["attributeType", "attribute_type"]
     *           ["attributeIds", "attribute_ids"]
     *           ["storeIds", "stores"]
     */
    public function testObjectInstantiationFails_ForInvalidDataKeys(string $key, string $invalidKey): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid key provided in creation of %s. Key %s',
                AttributeUpdate::class,
                $invalidKey,
            ),
        );

        $data = [
            'attributeType' => 'KLEVU_CMS',
            'attributeIds' => [1, 2, 3],
            'storeIds' => [1, 2],
        ];
        $data[$invalidKey] = $data[$key];
        unset($data[$key]);

        $modelFactory = $this->objectManager->get(AttributeFactory::class);
        $modelFactory->create([
            'data' => $data,
        ]);
    }

    /**
     * @testWith ["attributeIds", [1, "2", 3]]
     *           ["storeIds", [1, "default"]]
     *
     * @param string $key
     * @param mixed[] $invalidValues
     */
    public function testObjectInstantiationFails_ForValidationCheckOnInt(string $key, array $invalidValues): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid value supplied for %s at position %s. Expects int, received %s',
                $key,
                1,
                get_debug_type($invalidValues[1]),
            ),
        );

        $data = [
            'attributeType' => 'KLEVU_CMS',
            'attributeIds' => [1, 2, 3],
            'storeIds' => [1, 2],
        ];
        $data[$key] = $invalidValues;

        $modelFactory = $this->objectManager->get(AttributeFactory::class);
        $modelFactory->create([
            'data' => $data,
        ]);
    }

    public function testObjectReturnsValuesSet(): void
    {
        $data = [
            'attributeType' => 'KLEVU_PRODUCT',
            'attributeIds' => [1, 2, 3],
            'storeIds' => [1, 2],
        ];

        $modelFactory = $this->objectManager->get(AttributeFactory::class);
        /** @var AttributeUpdate $attributeUpdate */
        $attributeUpdate = $modelFactory->create([
            'data' => $data,
        ]);

        $this->assertSame(expected: 'KLEVU_PRODUCT', actual: $attributeUpdate->getAttributeType());
        $this->assertSame(expected: [1, 2, 3], actual: $attributeUpdate->getAttributeIds());
        $this->assertSame(expected: [1, 2], actual: $attributeUpdate->getStoreIds());
    }
}
