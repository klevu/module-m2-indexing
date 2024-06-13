<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Indexing\Batch;

use Klevu\Indexing\Service\Indexing\Batch\DeleteService as IndexingBatchDeleteServiceVirtualType;
use Klevu\PhpSDK\Api\Service\Indexing\BatchDeleteServiceInterface;
use Klevu\PhpSDK\Provider\UserAgentProvider;
use Klevu\PhpSDK\Service\Indexing\Batch\DeleteService;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers DeleteService
 * @method BatchDeleteServiceInterface instantiateTestObject(?array $arguments = null)
 * @method BatchDeleteServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DeleteServiceTest extends TestCase
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

        $this->implementationFqcn = IndexingBatchDeleteServiceVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = BatchDeleteServiceInterface::class;
        $this->implementationForVirtualType = DeleteService::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGetUserAgentProvider_ReturnsUserAgentProvider(): void
    {
        $service = $this->instantiateTestObject();
        $provider = $service->getUserAgentProvider();

        $this->assertSame(
            expected: UserAgentProvider::class,
            actual: $provider::class,
        );

        $userAgent = $provider->execute();
        $this->assertStringMatchesFormat(
            format: '%A(%Aklevu-m2-indexing/%d.%d.%d.%d%A)%A',
            string: $userAgent,
        );
    }
}
