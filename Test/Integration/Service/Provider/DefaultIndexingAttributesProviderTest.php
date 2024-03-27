<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\DefaultIndexingAttributesProvider;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers DefaultIndexingAttributesProvider::class
 * @method DefaultIndexingAttributesProviderInterface instantiateTestObject(?array $arguments = null)
 * @method DefaultIndexingAttributesProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DefaultIndexingAttributesProviderTest extends TestCase
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

        $mockMagentoToKlevuAttributeMapper = $this->getMockBuilder(
            className: MagentoToKlevuAttributeMapperInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->implementationFqcn = DefaultIndexingAttributesProvider::class;
        $this->interfaceFqcn = DefaultIndexingAttributesProviderInterface::class;
        $this->constructorArgumentDefaults = [
            'entityType' => 'KLEVU_CMS',
            'attributeToNameMapper' => $mockMagentoToKlevuAttributeMapper,
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
