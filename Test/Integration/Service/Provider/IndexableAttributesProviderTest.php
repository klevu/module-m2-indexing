<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\IndexableAttributesProvider;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexableAttributesProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers IndexableAttributesProvider::class
 * @method IndexableAttributesProviderInterface instantiateTestObject(?array $arguments = null)
 * @method IndexableAttributesProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexableAttributesProviderTest extends TestCase
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

        $mockDefaultIndexingAttributesProvider = $this->getMockBuilder(
            className: DefaultIndexingAttributesProviderInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->implementationFqcn = IndexableAttributesProvider::class;
        $this->interfaceFqcn = IndexableAttributesProviderInterface::class;
        $this->constructorArgumentDefaults = [
            'defaultIndexingAttributesProvider' => $mockDefaultIndexingAttributesProvider,
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
