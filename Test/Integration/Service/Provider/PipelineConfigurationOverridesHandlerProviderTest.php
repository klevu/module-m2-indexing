<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\Provider\PipelineConfigurationOverridesHandlerProvider;
use Klevu\IndexingApi\Service\Provider\PipelineConfigurationOverridesHandlerProviderInterface;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers PipelineConfigurationOverridesHandlerProvider::class
 * @method PipelineConfigurationOverridesHandlerProviderInterface instantiateTestObject(?array $arguments = null)
 * @method PipelineConfigurationOverridesHandlerProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class PipelineConfigurationOverridesHandlerProviderTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

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

        $this->implementationFqcn = PipelineConfigurationOverridesHandlerProvider::class;
        $this->interfaceFqcn = PipelineConfigurationOverridesHandlerProviderInterface::class;
        $this->constructorArgumentDefaults = [];

        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGet_ReturnsEmptyArray_WhenNoHandlersAdded(): void
    {
        $provider = $this->instantiateTestObject([
            'configurationOverridesHandlers' => [],
        ]);
        $this->assertCount(expectedCount: 0, haystack: $provider->get());
    }

    public function testGet_ReturnsArrayOfHandlers(): void
    {
        $provider = $this->instantiateTestObject([
            'configurationOverridesHandlers' => [
                'foo' => [
                    $this->getMockConfigurationOverridesHandler(),
                    $this->getMockConfigurationOverridesHandler(),
                ],
                'KLEVU_PRODUCT' => [
                    $this->getMockConfigurationOverridesHandler(),
                ],
                'bar' => [
                    $this->getMockConfigurationOverridesHandler(),
                    $this->getMockConfigurationOverridesHandler(),
                    $this->getMockConfigurationOverridesHandler(),
                ],
            ],
        ]);

        $result = $provider->get();
        $this->assertArrayHasKey(key: 'foo', array: $result);
        $this->assertCount(expectedCount: 2, haystack: $result['foo']);
        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result);
        $this->assertCount(expectedCount: 1, haystack: $result['KLEVU_PRODUCT']);
        $this->assertArrayHasKey(key: 'bar', array: $result);
        $this->assertCount(expectedCount: 3, haystack: $result['bar']);
    }

    /**
     * @return MockObject
     */
    private function getMockConfigurationOverridesHandler(): MockObject
    {
        return $this->getMockBuilder(ConfigurationOverridesHandlerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
