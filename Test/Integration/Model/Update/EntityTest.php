<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model\Update;

use Klevu\Indexing\Model\Update\Entity as EntityUpdate;
use Klevu\Indexing\Model\Update\EntityFactory;
use Klevu\IndexingApi\Model\Update\EntityInterface as EntityUpdateInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Model\Update\Entity::class
 * @method EntityUpdateInterface instantiateTestObject(?array $arguments = null)
 * @method EntityUpdateInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityTest extends TestCase
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

        $this->implementationFqcn = EntityUpdate::class;
        $this->interfaceFqcn = EntityUpdateInterface::class;
        $this->constructorArgumentDefaults = [
            'data' => [
                'entityType' => 'KLEVU_CMS',
                'entityIds' => [1, 2, 3],
                'storeIds' => [1, 2],
                'customerGroupIds' => [],
                'attributes' => [],
            ],
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith ["entityType", "entity_type"]
     *           ["entityIds", "entity_ids"]
     *           ["storeIds", "stores"]
     *           ["customerGroupIds", "cusGroups"]
     *           ["attributes", "attribute_list"]
     */
    public function testObjectInstantiationFails_ForInvalidDataKeys(string $key, string $invalidKey): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid key provided in creation of %s. Key %s',
                EntityUpdate::class,
                $invalidKey,
            ),
        );

        $data = [
            'entityType' => 'KLEVU_CMS',
            'entityIds' => [1, 2, 3],
            'storeIds' => [1, 2],
            'customerGroupIds' => [1, 2],
            'attributes' => ["price", "stock", "categories"],
        ];
        $data[$invalidKey] = $data[$key];
        unset($data[$key]);

        $modelFactory = $this->objectManager->get(EntityFactory::class);
        $modelFactory->create([
            'data' => $data,
        ]);
    }

    /**
     * @testWith ["entityIds", [1, "2", 3]]
     *           ["storeIds", [1, "default"]]
     *           ["customerGroupIds", [1, [2]]]
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
            'entityType' => 'KLEVU_CMS',
            'entityIds' => [1, 2, 3],
            'storeIds' => [1, 2],
            'customerGroupIds' => [1, 2],
            'attributes' => ["price", "stock", "categories"],
        ];
        $data[$key] = $invalidValues;

        $modelFactory = $this->objectManager->get(EntityFactory::class);
        $modelFactory->create([
            'data' => $data,
        ]);
    }

    /**
     * @testWith ["attributes", ["price", 1, "stock"]]
     *           ["attributes", ["stock", ["categories"]]]
     *
     * @param string $key
     * @param mixed[] $invalidValues
     */
    public function testObjectInstantiationFails_ForValidationCheckOnString(string $key, array $invalidValues): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid value supplied for %s at position %s. Expects string, received %s',
                $key,
                1,
                get_debug_type($invalidValues[1]),
            ),
        );

        $data = [
            'entityType' => 'KLEVU_CMS',
            'entityIds' => [1, 2, 3],
            'storeIds' => [1, 2],
            'customerGroupIds' => [1, 2],
            'attributes' => ["price", "stock", "categories"],
        ];
        $data[$key] = $invalidValues;

        $modelFactory = $this->objectManager->get(EntityFactory::class);
        $modelFactory->create([
            'data' => $data,
        ]);
    }

    public function testObjectReturnsValuesSet(): void
    {
        $data = [
            'entityType' => 'KLEVU_PRODUCT',
            'entityIds' => [1, 2, 3],
            'storeIds' => [1, 2],
            'customerGroupIds' => [10, 20],
            'attributes' => ["price", "stock", "categories"],
        ];

        $modelFactory = $this->objectManager->get(EntityFactory::class);
        /** @var EntityUpdate $entityUpdate */
        $entityUpdate = $modelFactory->create([
            'data' => $data,
        ]);

        $this->assertSame(expected: 'KLEVU_PRODUCT', actual: $entityUpdate->getEntityType());
        $this->assertSame(expected: [1, 2, 3], actual: $entityUpdate->getEntityIds());
        $this->assertSame(expected: [1, 2], actual: $entityUpdate->getStoreIds());
        $this->assertSame(expected: [10, 20], actual: $entityUpdate->getCustomerGroupIds());
        $this->assertSame(expected: ["price", "stock", "categories"], actual: $entityUpdate->getAttributes());
    }
}
