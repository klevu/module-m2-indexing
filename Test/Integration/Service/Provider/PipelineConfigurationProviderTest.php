<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Service\EntityIndexerService;
use Klevu\Indexing\Service\Provider\PipelineConfigurationProvider;
use Klevu\IndexingApi\Service\Provider\PipelineConfigurationProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sync\EntityIndexingRecordProviderInterface;
use Klevu\PlatformPipelines\Service\Provider\PipelineConfigurationOverridesFilepathsProvider;
use Klevu\PlatformPipelines\Service\Provider\PipelineConfigurationProvider as PlatformsPipelineConfigurationProvider;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Module\Dir as ModuleDir;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers PipelineConfigurationProvider::class
 * @method PipelineConfigurationProviderInterface instantiateTestObject(?array $arguments = null)
 * @method PipelineConfigurationProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class PipelineConfigurationProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;
    /**
     * @var ModuleDir|null
     */
    private ?ModuleDir $moduleDir = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = PipelineConfigurationProvider::class;
        $this->interfaceFqcn = PipelineConfigurationProviderInterface::class;
        $this->constructorArgumentDefaults = [
            'entityIndexerServices' => [],
        ];

        $this->objectManager = Bootstrap::getObjectManager();
        $this->moduleDir = $this->objectManager->get(ModuleDir::class);
    }

    /**
     * @testWith [""]
     *           [" "]
     */
    public function testGet_ReturnsNull_WhenNoIdentifierSupplied(string $identifier): void
    {
        $provider = $this->instantiateTestObject();
        $this->assertNull(actual: $provider->get($identifier));
    }

    public function testGet_ReturnsNull_WhenNoRegisteredPipelineConfiguration(): void
    {
        $provider = $this->instantiateTestObject();
        $this->assertNull(actual: $provider->get('foo'));
    }

    /**
     * @group wip
     */
    public function testGet_ReturnsPipelineConfiguration(): void
    {
        $provider = $this->instantiateTestObject([
            'entityIndexerServices' => [
                'foo' => $this->getEntityIndexerService(),
            ],
        ]);
        $this->assertEquals(
            expected: [
                'stages' => [
                    'createRecord' => [
                        'pipeline' => 'Pipeline\CreateRecord',
                        'stages' => [
                            'foo' => [
                                'pipeline' => 'Stage\Extract',
                                'args' => [
                                    'extraction' => 'getFoo()',
                                ],
                                'stages' => [],
                            ],
                            'bar' => [
                                'pipeline' => 'Stage\Extract',
                                'args' => [
                                    'extraction' => 'bar',
                                ],
                                'stages' => [],
                            ],
                        ],
                    ],
                ],
            ],
            actual: $provider->get('foo'),
        );
    }

    /**
     * @return EntityIndexerService
     */
    private function getEntityIndexerService(): EntityIndexerService
    {
        $fixturesDirectory = $this->moduleDir->getDir('Klevu_Indexing')
            . DIRECTORY_SEPARATOR
            . 'Test'
            . DIRECTORY_SEPARATOR
            . 'fixtures'
            . DIRECTORY_SEPARATOR
            . 'etc'
            . DIRECTORY_SEPARATOR
            . 'pipeline';

        $pipelineConfigurationOverridesFilepathsProvider = $this->objectManager->create(
            type: PipelineConfigurationOverridesFilepathsProvider::class,
            arguments: [
                'pipelineConfigurationOverrideFilepaths' => [
                    $fixturesDirectory . DIRECTORY_SEPARATOR . 'foo.overrides.yml',
                ],
            ],
        );

        $pipelineConfigurationProvider = $this->objectManager->create(
            type: PlatformsPipelineConfigurationProvider::class,
            arguments: [
                'pipelineConfigurationFilepaths' => [
                    'foo' => $fixturesDirectory . DIRECTORY_SEPARATOR . 'foo.yml',
                ],
                'pipelineConfigurationOverridesFilepathsProviders' => [
                    'foo' => $pipelineConfigurationOverridesFilepathsProvider,
                ],
            ],
        );

        return $this->objectManager->create(
            type: EntityIndexerService::class,
            arguments: [
                'entityIndexingRecordProvider' => $this->getMockBuilder(
                    className: EntityIndexingRecordProviderInterface::class,
                )->disableOriginalConstructor()
                    ->getMock(),
                'pipelineIdentifier' => 'foo',
                'pipelineConfigurationProvider' => $pipelineConfigurationProvider,
            ],
        );
    }
}
