<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider\Cache;

use Klevu\Indexing\Service\Provider\Cache\CachedAttributesProvider;
use Klevu\IndexingApi\Service\Provider\Cache\CachedAttributesProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers CachedAttributesProvider::class
 * @method CachedAttributesProviderInterface instantiateTestObject(?array $arguments = null)
 * @method CachedAttributesProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CachedAttributesProviderTest extends TestCase
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

        $this->implementationFqcn = CachedAttributesProvider::class;
        $this->interfaceFqcn = CachedAttributesProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
