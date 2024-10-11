<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Console\Command;

use Klevu\Indexing\Console\Command\ConfigurationDumpPipelineCommand;
use Klevu\Indexing\Service\EntityIndexerService;
use Klevu\IndexingApi\Service\Provider\PipelineConfigurationProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sync\EntityIndexingRecordProviderInterface;
use Klevu\PlatformPipelines\Service\Provider\PipelineConfigurationOverridesFilepathsProvider;
use Klevu\PlatformPipelines\Service\Provider\PipelineConfigurationProvider;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Framework\Module\Dir as ModuleDir;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \Klevu\Indexing\Console\Command\ConfigurationDumpPipelineCommand::class
 * @method ConfigurationDumpPipelineCommand instantiateTestObject(?array $arguments = null)
 */
class ConfigurationDumpPipelineCommandTest extends TestCase
{
    use ObjectInstantiationTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line Used by traits
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

        $this->objectManager = ObjectManager::getInstance();

        $this->implementationFqcn = ConfigurationDumpPipelineCommand::class;
        // newrelic-describe-commands globs onto Console commands
        $this->expectPlugins = true;

        $this->moduleDir = $this->objectManager->get(ModuleDir::class);
    }

    public function testExecute_ReturnsFailure_WhenNoRegisteredPipelineConfiguration(): void
    {
        $configurationDumpPipelineCommand = $this->instantiateTestObject();

        $tester = new CommandTester(
            command: $configurationDumpPipelineCommand,
        );
        $responseCode = $tester->execute(
            input: [
                'pipelineIdentifier' => 'foo',
            ],
        );

        $this->assertSame(1, $responseCode);

        $output = $tester->getDisplay();
        $this->assertStringContainsString(
            needle: 'Could not build configuration for identifier foo',
            haystack: $output,
        );
    }

    public function testExecute(): void
    {
        $pipelineConfigurationProvider = $this->objectManager->create(PipelineConfigurationProviderInterface::class, [
            'entityIndexerServices' => [
                'foo' => $this->getEntityIndexerService(),
            ],
        ]);

        $configurationDumpPipelineCommand = $this->instantiateTestObject([
            'pipelineConfigurationProvider' => $pipelineConfigurationProvider,
        ]);

        $tester = new CommandTester(
            command: $configurationDumpPipelineCommand,
        );
        $responseCode = $tester->execute(
            input: [
                'pipelineIdentifier' => 'foo',
            ],
        );

        $this->assertSame(0, $responseCode);

        $output = $tester->getDisplay();

        $this->assertSame(
            expected: <<<'YAML'
pipeline: Pipeline
args: {  }
stages:
  createRecord:
    pipeline: Pipeline\CreateRecord
    args: {  }
    stages:
      foo:
        pipeline: Stage\Extract
        args:
          extraction: getFoo()
        stages: {  }
      bar:
        pipeline: Stage\Extract
        args:
          extraction: bar
        stages: {  }
YAML,
            actual: rtrim($output),
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
            type: PipelineConfigurationProvider::class,
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
