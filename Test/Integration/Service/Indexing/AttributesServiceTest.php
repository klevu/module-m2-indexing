<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Indexing;

use Klevu\Indexing\Service\Indexing\AttributesService as IndexingAttributesServiceVirtualType;
use Klevu\PhpSDK\Api\Service\Indexing\AttributesServiceInterface;
use Klevu\PhpSDK\Provider\UserAgentProvider;
use Klevu\PhpSDK\Service\Indexing\AttributesService;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers AttributesService
 * @method AttributesServiceInterface instantiateTestObject(?array $arguments = null)
 * @method AttributesServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributesServiceTest extends TestCase
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

        $this->implementationFqcn = IndexingAttributesServiceVirtualType::class; // @phpstan-ignore-line
        $this->interfaceFqcn = AttributesServiceInterface::class;
        $this->implementationForVirtualType = AttributesService::class;
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
