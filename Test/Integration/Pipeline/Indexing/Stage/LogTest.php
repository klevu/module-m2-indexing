<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Pipeline\Indexing\Stage;

use Klevu\Indexing\Pipeline\Indexing\Stage\Log;
use Klevu\Pipelines\Pipeline\PipelineInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers Log
 * @method Log instantiateTestObject(?array $arguments = null)
 * @method Log instantiateTestObjectFromInterface(?array $arguments = null)
 */
class LogTest extends TestCase
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

        $this->implementationFqcn = Log::class;
        $this->interfaceFqcn = PipelineInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
