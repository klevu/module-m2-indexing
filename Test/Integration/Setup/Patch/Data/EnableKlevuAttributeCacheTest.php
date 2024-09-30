<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Setup\Patch\Data;

use Klevu\Indexing\Cache\Attributes;
use Klevu\Indexing\Setup\Patch\Data\EnableKlevuAttributeCache;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers EnableKlevuAttributeCache::class
 * @method DataPatchInterface instantiateTestObject(?array $arguments = null)
 * @method DataPatchInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EnableKlevuAttributeCacheTest extends TestCase
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

        $this->implementationFqcn = EnableKlevuAttributeCache::class;
        $this->interfaceFqcn = DataPatchInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGetAliases_ReturnsEmptyArray(): void
    {
        $patch = $this->instantiateTestObject();
        $aliases = $patch->getAliases();

        $this->assertCount(expectedCount: 0, haystack: $aliases);
    }

    public function testGetDependencies_ReturnsEmptyArray(): void
    {
        $patch = $this->instantiateTestObject();
        $dependencies = $patch->getDependencies();

        $this->assertCount(expectedCount: 0, haystack: $dependencies);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testApply_EnablesCache(): void
    {
        $this->markTestSkipped('Causes "deployment configuration is corrupted" Error');
        $patch = $this->instantiateTestObject(); // @phpstan-ignore-line
        $patch->apply();

        $cacheState = $this->objectManager->create(StateInterface::class);
        $this->assertTrue(condition: $cacheState->isEnabled(cacheType: Attributes::TYPE_IDENTIFIER));
    }
}
