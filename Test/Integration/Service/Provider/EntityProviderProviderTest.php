<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\EntityProviderProvider;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers EntityProviderProvider::class
 * @method EntityProviderProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityProviderProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityProviderProviderTest extends TestCase
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

        $this->implementationFqcn = EntityProviderProvider::class;
        $this->interfaceFqcn = EntityProviderProviderInterface::class;
        $this->constructorArgumentDefaults = [
            'entityProviders' => [],
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGet_ReturnsEmptyArray_WhenNoProvidersSet(): void
    {
        $provider = $this->instantiateTestObject();
        $result = $provider->get();

        $this->assertEmpty($result);
    }

    public function testGet_ReturnsArrayOfProviders(): void
    {
        $provider = $this->instantiateTestObject([
            'entityProviders' => [
                'provider1' => $this->getMockBuilder(EntityProviderInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock(),
                'provider2' => $this->getMockBuilder(EntityProviderInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock(),
            ],
        ]);
        $result = $provider->get();

        $this->assertNotEmpty($result);
        $this->assertCount(expectedCount: 2, haystack: $result);
    }
}
