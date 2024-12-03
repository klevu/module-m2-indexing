<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Service\RegenerateConfigurationOverrides;
use Klevu\IndexingApi\Service\RegenerateConfigurationOverridesInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Full tests implemented in dependent modules where virtual types are defined
 *
 * @covers RegenerateConfigurationOverrides::class
 * @method RegenerateConfigurationOverridesInterface instantiateTestObject(?array $arguments = null)
 * @method RegenerateConfigurationOverridesInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class RegenerateConfigurationOverridesTest extends TestCase
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

        $this->implementationFqcn = RegenerateConfigurationOverrides::class;
        $this->interfaceFqcn = RegenerateConfigurationOverridesInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
