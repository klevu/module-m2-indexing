<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Model\MagentoEntity;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Model\MagentoEntity::class
 * @method MagentoEntityInterface instantiateTestObject(?array $arguments = null)
 * @method MagentoEntityInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class MagentoEntityTest extends TestCase
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

        $this->implementationFqcn = MagentoEntity::class;
        $this->interfaceFqcn = MagentoEntityInterface::class;
        $this->constructorArgumentDefaults = [
            'entityId' => 1,
            'apiKey' => 'klevu-js-api-key',
            'isIndexable' => true,
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testMagentoEntity_ReturnsExpectedValues(): void
    {
        $entity = $this->instantiateTestObject([
            'entityId' => 123,
            'entityParentId' => 456,
            'apiKey' => 'klevu-js-api-key-test',
            'isIndexable' => false,
        ]);

        $this->assertSame(expected: 123, actual: $entity->getEntityId());
        $this->assertSame(expected: 456, actual: $entity->getEntityParentId());
        $this->assertSame(expected: 'klevu-js-api-key-test', actual: $entity->getApiKey());
        $this->assertFalse(condition: $entity->isIndexable());
    }

    public function testSetIsIndexable_SetsNewValue(): void
    {
        $entity = $this->instantiateTestObject([
            'entityId' => 2,
            'entityParentId' => null,
            'apiKey' => 'klevu-js-api-key-test',
            'isIndexable' => false,
        ]);

        $this->assertSame(expected: 2, actual: $entity->getEntityId());
        $this->assertNull($entity->getEntityParentId());
        $this->assertSame(expected: 'klevu-js-api-key-test', actual: $entity->getApiKey());
        $this->assertFalse(condition: $entity->isIndexable());

        $entity->setIsIndexable(true);

        $this->assertSame(expected: 2, actual: $entity->getEntityId());
        $this->assertNull($entity->getEntityParentId());
        $this->assertSame(expected: 'klevu-js-api-key-test', actual: $entity->getApiKey());
        $this->assertTrue(condition: $entity->isIndexable());
    }
}
