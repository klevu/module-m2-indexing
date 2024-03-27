<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider\Sync;

use Klevu\Indexing\Service\Provider\Sync\EntityIndexingRecordProvider;
use Klevu\IndexingApi\Service\EntityIndexingRecordCreatorServiceInterface;
use Klevu\IndexingApi\Service\Provider\EntityProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sync\EntityIndexingRecordProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers EntityIndexingRecordProvider::class
 * @method EntityIndexingRecordProviderInterface instantiateTestObject(?array $arguments = null)
 * @method EntityIndexingRecordProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityIndexingRecordProviderTest extends TestCase
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

        $mockIndexingEntityProvider = $this->getMockBuilder(IndexingEntityProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEntityProvider = $this->getMockBuilder(EntityProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIndexingRecordCreatorService = $this->getMockBuilder(EntityIndexingRecordCreatorServiceInterface::class)
        ->disableOriginalConstructor()
        ->getMock();
        $this->implementationFqcn = EntityIndexingRecordProvider::class;
        $this->interfaceFqcn = EntityIndexingRecordProviderInterface::class;
        $this->constructorArgumentDefaults = [
            'indexingEntityProvider' => $mockIndexingEntityProvider,
            'entityProviders' => ['KLEVU_PRODUCT' => $mockEntityProvider],
            'indexingRecordCreatorService' => $mockIndexingRecordCreatorService,
            'action' => 'Add',
            'entityType' => 'KLEVU_PRODUCT',
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
