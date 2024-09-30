<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Analytics;

use Klevu\Analytics\Service\Analytics\CollectService as AnalyticsCollectServiceVirtualType;
use Klevu\PhpSDK\Api\Service\Analytics\CollectServiceInterface;
use Klevu\PhpSDK\Provider\UserAgentProvider;
use Klevu\PhpSDK\Service\Analytics\CollectService;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class CollectServiceTest extends TestCase
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

        $this->implementationFqcn = AnalyticsCollectServiceVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = CollectServiceInterface::class;
        $this->implementationForVirtualType = CollectService::class;
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
