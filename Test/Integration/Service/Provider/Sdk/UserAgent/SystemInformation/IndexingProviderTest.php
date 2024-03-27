<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider\Sdk\UserAgent\SystemInformation;

use Klevu\Configuration\Service\Provider\Sdk\UserAgent\PlatformUserAgentProvider;
use Klevu\Configuration\Service\Provider\Sdk\UserAgentProvider as UserAgentProviderVirtualType;
use Klevu\Indexing\Service\Provider\Sdk\UserAgent\SystemInformation\IndexingProvider;
use Klevu\PhpSDK\Provider\UserAgentProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class IndexingProviderTest extends TestCase
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

        $this->implementationFqcn = IndexingProvider::class;
        $this->interfaceFqcn = UserAgentProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_ContainsProductName(): void
    {
        /** @var IndexingProvider $indexingProvider */
        $indexingProvider = $this->objectManager->get(IndexingProvider::class);

        $result = $indexingProvider->execute();

        $this->assertStringContainsString(
            needle: IndexingProvider::PRODUCT_NAME,
            haystack: $result,
        );
    }

    public function testPlatformProviderContainsSystemInformation(): void
    {
        /** @var PlatformUserAgentProvider $platformProvider */
        $platformProvider = $this->objectManager->get(PlatformUserAgentProvider::class);

        $result = $platformProvider->execute();

        $this->assertStringContainsString(
            needle: IndexingProvider::PRODUCT_NAME,
            haystack: $result,
        );
    }

    public function testUserAgentProviderContainsSystemInformation(): void
    {
        /** @var UserAgentProviderInterface $userAgentProvider */
        $userAgentProvider = $this->objectManager->get(UserAgentProviderVirtualType::class); // @phpstan-ignore-line

        $result = $userAgentProvider->execute();

        $this->assertStringContainsString(
            needle: IndexingProvider::PRODUCT_NAME,
            haystack: $result,
        );
    }
}
