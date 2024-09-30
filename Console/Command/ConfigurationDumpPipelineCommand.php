<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Console\Command;

use Klevu\IndexingApi\Service\EntityIndexerServiceInterface;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ConfigurationDumpPipelineCommand extends Command
{
    public const COMMAND_NAME = 'klevu:indexing:configuration-dump-pipeline';
    public const ARGUMENT_PIPELINE_IDENTIFIER = 'pipelineIdentifier';

    /**
     * @var EntityIndexerServiceInterface[]
     */
    private array $entityIndexerServices = [];
    /**
     * @var array<string, ConfigurationOverridesHandlerInterface[]>
     */
    private array $configurationOverridesHandlers = [];

    /**
     * @param EntityIndexerServiceInterface[] $entityIndexerServices
     * @param ConfigurationOverridesHandlerInterface[] $configurationOverridesHandlers
     * @param string|null $name
     *
     * @throws LogicException
     */
    public function __construct(
        array $entityIndexerServices,
        array $configurationOverridesHandlers = [],
        ?string $name = null,
    ) {
        array_walk($entityIndexerServices, [$this, 'addEntityIndexerService']);

        foreach ($configurationOverridesHandlers as $entityType => $configurationOverridesHandlersForEntityType) {
            array_walk(
                $configurationOverridesHandlersForEntityType,
                function (ConfigurationOverridesHandlerInterface $configurationOverridesHandler) use ($entityType): void { // phpcs:ignore Generic.Files.LineLength.TooLong
                    $this->addConfigurationOverridesHandler(
                        configurationOverridesHandler: $configurationOverridesHandler,
                        entityType: $entityType,
                    );
                },
            );
        }

        parent::__construct($name);
    }

    /**
     * If you have a custom implementation of the EntityIndexerService which does not contain a buildPipeline method,
     *  you can plug into this method to return configuration from your class
     * Note: we use reflection within this class, which is inherently unreliable and not recommended
     *  It is used as the core Klevu classes are known, and the purpose of this tool is debugging
     *
     * @return mixed[]|null
     */
    public function getPipelineConfigurationForIdentifier(
        string $pipelineIdentifier,
    ): ?array {
        if (!$pipelineIdentifier) {
            return null;
        }

        $entityIndexerService = $this->entityIndexerServices[$pipelineIdentifier] ?? null;
        if (!$entityIndexerService) {
            return null;
        }

        [$entityType] = explode('::', $pipelineIdentifier);
        foreach ($this->configurationOverridesHandlers[$entityType] ?? [] as $configurationOverridesHandler) {
            $configurationOverridesHandler->execute();
        }

        $entityIndexerServiceReflection = new \ReflectionObject($entityIndexerService);
        try {
            // phpcs:disable Generic.Files.LineLength.TooLong
            $pipelineConfigurationFilepathProperty = $entityIndexerServiceReflection->getProperty('pipelineConfigurationFilepath');
            $pipelineConfigurationFilepath = $pipelineConfigurationFilepathProperty->getValue($entityIndexerService);
            $pipelineConfigurationOverridesFilepathsProperty = $entityIndexerServiceReflection->getProperty('pipelineConfigurationOverridesFilepaths');
            $pipelineConfigurationOverridesFilepaths = $pipelineConfigurationOverridesFilepathsProperty->getValue($entityIndexerService);
            // phpcs:enable Generic.Files.LineLength.TooLong

            $pipelineBuilderProperty = $entityIndexerServiceReflection->getProperty('pipelineBuilder');
            $pipelineBuilder = $pipelineBuilderProperty->getValue($entityIndexerService);

            $platformPipelinesPipelineBuilderReflection = new \ReflectionObject($pipelineBuilder);
            $sdkPipelinesPipelineBuilderReflection = $platformPipelinesPipelineBuilderReflection->getParentClass();
            $basePipelineBuilderReflection = $sdkPipelinesPipelineBuilderReflection->getParentClass();

            $configurationBuilderProperty = $basePipelineBuilderReflection->getProperty('configurationBuilder');
            $configurationBuilder = $configurationBuilderProperty->getValue($pipelineBuilder);

            $pipelineConfiguration = $configurationBuilder->buildFromFiles(
                pipelineDefinitionFile: $pipelineConfigurationFilepath,
                pipelineOverridesFiles: $pipelineConfigurationOverridesFilepaths,
            );
        } catch (\ReflectionException) {
            $pipelineConfiguration = null;
        }

        return $pipelineConfiguration;
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName(static::COMMAND_NAME);
        $this->addArgument(
            name: static::ARGUMENT_PIPELINE_IDENTIFIER,
            mode: InputArgument::REQUIRED,
        );
        $this->setDescription(
            description: __(
                'Dumps actual compiled YAML configuration for selected pipeline, with include directives and ' .
                    'configuration overrides files.',
            )->render(),
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws InvalidArgumentException
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $pipelineIdentifier = $input->getArgument(static::ARGUMENT_PIPELINE_IDENTIFIER);
        $pipelineConfiguration = $this->getPipelineConfigurationForIdentifier($pipelineIdentifier);
        if (null === $pipelineConfiguration) {
            $output->writeln(sprintf(
                '<error>%s</error>',
                __('Could not build configuration for identifier %1', $pipelineIdentifier),
            ));

            return Cli::RETURN_FAILURE;
        }

        $output->writeln(
            // Need to suppress errors so that potential deprecated warnings related to conversion of int to string
            //  do not throw exception in Magento
            @Yaml::dump( // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
                input: $pipelineConfiguration,
                inline: 100,
                indent: 2,
            ),
        );

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param EntityIndexerServiceInterface $entityIndexerService
     * @param string $identifier
     *
     * @return void
     */
    private function addEntityIndexerService(
        EntityIndexerServiceInterface $entityIndexerService,
        string $identifier,
    ): void {
        $this->entityIndexerServices[$identifier] = $entityIndexerService;
    }

    /**
     * @param ConfigurationOverridesHandlerInterface $configurationOverridesHandler
     * @param string $entityType
     *
     * @return void
     */
    private function addConfigurationOverridesHandler(
        ConfigurationOverridesHandlerInterface $configurationOverridesHandler,
        string $entityType,
    ): void {
        $this->configurationOverridesHandlers[$entityType] ??= [];
        $this->configurationOverridesHandlers[$entityType][] = $configurationOverridesHandler;
    }
}
