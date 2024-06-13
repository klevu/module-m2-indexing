<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider\Sync;

use Klevu\Indexing\Service\Provider\Sync\AttributeIndexingRecordProvider;
use Klevu\IndexingApi\Service\AttributeIndexingDeleteRecordCreatorServiceInterface;
use Klevu\IndexingApi\Service\AttributeIndexingRecordCreatorServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributeProviderProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sync\AttributeIndexingRecordProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class AttributeIndexingRecordProviderTest extends TestCase
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

        $mockIndexingAttributeProvider = $this->getMockBuilder(IndexingAttributeProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributeProviderProvider = $this->getMockBuilder(AttributeProviderProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockIndexingRecordCreatorService = $this->getMockBuilder(AttributeIndexingRecordCreatorServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        // phpcs:ignore Generic.Files.LineLength.TooLong
        $mockIndexingDeleteRecordCreatorService = $this->getMockBuilder(AttributeIndexingDeleteRecordCreatorServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->implementationFqcn = AttributeIndexingRecordProvider::class;
        $this->interfaceFqcn = AttributeIndexingRecordProviderInterface::class;
        $this->constructorArgumentDefaults = [
            'indexingAttributeProvider' => $mockIndexingAttributeProvider,
            'attributeProvidersProvider' => $mockAttributeProviderProvider,
            'indexingRecordCreatorService' => $mockIndexingRecordCreatorService,
            'indexingDeleteRecordCreatorService' => $mockIndexingDeleteRecordCreatorService,
            'action' => 'Add',
            'entityType' => 'KLEVU_PRODUCT',
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }
}
