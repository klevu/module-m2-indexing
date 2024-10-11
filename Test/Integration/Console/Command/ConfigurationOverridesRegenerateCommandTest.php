<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Console\Command;

use Klevu\Indexing\Console\Command\ConfigurationOverridesRegenerateCommand;
use Klevu\IndexingApi\Service\Provider\PipelineConfigurationOverridesHandlerProviderInterface;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers \Klevu\Indexing\Console\Command\ConfigurationOverridesRegenerateCommand::class
 * @method ConfigurationOverridesRegenerateCommand instantiateTestObject(?array $arguments = null)
 */
class ConfigurationOverridesRegenerateCommandTest extends TestCase
{
    use ObjectInstantiationTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line Used by traits

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = ObjectManager::getInstance();

        $this->implementationFqcn = ConfigurationOverridesRegenerateCommand::class;
        // newrelic-describe-commands globs onto Console commands
        $this->expectPlugins = true;
    }

    public function testExecute_PerformsNoAction_WhenGenerationDisabled(): void
    {
        ConfigFixture::setGlobal(
            path: 'klevu/platform_pipelines/configuration_overrides_generation_enabled',
            value: 0,
        );

        $handlerProvider = $this->objectManager->create(PipelineConfigurationOverridesHandlerProviderInterface::class, [
            'configurationOverridesHandlers' => [
                'foo' => [
                    $this->getMockConfigurationOverridesHandlerExpectsNotToExecute(),
                    $this->getMockConfigurationOverridesHandlerExpectsNotToExecute(),
                ],
                'KLEVU_PRODUCT' => [
                    $this->getMockConfigurationOverridesHandlerExpectsNotToExecute(),
                    $this->getMockConfigurationOverridesHandlerExpectsNotToExecute(),
                ],
                'bar' => [
                    $this->getMockConfigurationOverridesHandlerExpectsNotToExecute(),
                    $this->getMockConfigurationOverridesHandlerExpectsNotToExecute(),
                ],
            ],
        ]);

        $configurationOverridesRegenerateCommand = $this->instantiateTestObject([
            'pipelineConfigurationOverridesHandlerProvider' => $handlerProvider,
        ]);

        $tester = new CommandTester(
            command: $configurationOverridesRegenerateCommand,
        );
        $responseCode = $tester->execute(
            input: [
                '--entity-type' => [
                    'foo',
                    'bar',
                ],
            ],
        );

        $this->assertSame(0, $responseCode);
    }

    public function testExecute(): void
    {
        ConfigFixture::setGlobal(
            path: 'klevu/platform_pipelines/configuration_overrides_generation_enabled',
            value: 1,
        );

        $handlerProvider = $this->objectManager->create(PipelineConfigurationOverridesHandlerProviderInterface::class, [
            'configurationOverridesHandlers' => [
                'foo' => [
                    $this->getMockConfigurationOverridesHandlerExpectsExecute(),
                    $this->getMockConfigurationOverridesHandlerExpectsExecute(),
                ],
                'KLEVU_PRODUCT' => [
                    $this->getMockConfigurationOverridesHandlerExpectsNotToExecute(),
                    $this->getMockConfigurationOverridesHandlerExpectsNotToExecute(),
                ],
                'bar' => [
                    $this->getMockConfigurationOverridesHandlerExpectsExecute(),
                    $this->getMockConfigurationOverridesHandlerExpectsExecute(),
                ],
            ],
        ]);

        $configurationOverridesRegenerateCommand = $this->instantiateTestObject([
            'pipelineConfigurationOverridesHandlerProvider' => $handlerProvider,
        ]);

        $tester = new CommandTester(
            command: $configurationOverridesRegenerateCommand,
        );
        $responseCode = $tester->execute(
            input: [
                '--entity-type' => [
                    'foo',
                    'bar',
                ],
            ],
        );

        $this->assertSame(0, $responseCode);
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

    /**
     * @return MockObject
     */
    private function getMockConfigurationOverridesHandlerExpectsNotToExecute(): MockObject
    {
        $mockConfigurationOverridesHandler = $this->getMockConfigurationOverridesHandler();

        $mockConfigurationOverridesHandler->expects($this->never())
            ->method('execute');

        return $mockConfigurationOverridesHandler;
    }

    /**
     * @return MockObject
     */
    private function getMockConfigurationOverridesHandlerExpectsExecute(): MockObject
    {
        $mockConfigurationOverridesHandler = $this->getMockConfigurationOverridesHandler();

        $mockConfigurationOverridesHandler->expects($this->once())
            ->method('execute');

        return $mockConfigurationOverridesHandler;
    }
}
