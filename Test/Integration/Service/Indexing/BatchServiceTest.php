<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Indexing;

use Klevu\Indexing\Service\Indexing\BatchService as IndexingBatchServiceVirtualType;
use Klevu\PhpSDK\Api\Service\Indexing\BatchServiceInterface;
use Klevu\PhpSDK\Provider\UserAgentProvider;
use Klevu\PhpSDK\Service\Indexing\BatchService;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers BatchService
 * @method BatchServiceInterface instantiateTestObject(?array $arguments = null)
 * @method BatchServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class BatchServiceTest extends TestCase
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

        $this->implementationFqcn = IndexingBatchServiceVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = BatchServiceInterface::class;
        $this->implementationForVirtualType = BatchService::class;
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
        $this->assertMatchesRegularExpression(
            pattern: '#^.*\(.*klevu-m2-indexing(/\d+\.\d+\.\d+\.\d+)?.*\).*$#',
            string: $userAgent,
        );
    }
}
