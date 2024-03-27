<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Service\AttributeIndexerService;
use Klevu\IndexingApi\Service\Action\Sdk\Attribute\ActionInterface;
use Klevu\IndexingApi\Service\AttributeIndexerServiceInterface;
use Klevu\IndexingApi\Service\Provider\Sync\AttributeIndexingRecordProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers AttributeIndexerService
 * @method AttributeIndexerServiceInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeIndexerServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeIndexerServiceTest extends TestCase
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

        $mockRecordProvider = $this->getMockBuilder(AttributeIndexingRecordProviderInterface::class)
            ->getMock();
        $mockAction = $this->getMockBuilder(ActionInterface::class)->getMock();

        $this->implementationFqcn = AttributeIndexerService::class;
        $this->interfaceFqcn = AttributeIndexerServiceInterface::class;
        $this->constructorArgumentDefaults = [
            'attributeIndexingRecordProvider' => $mockRecordProvider,
            'action' => $mockAction,
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
