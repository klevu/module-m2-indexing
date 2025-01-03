<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Console\Command;

use Klevu\IndexingApi\Service\Provider\PipelineConfigurationOverridesHandlerProviderInterface;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;
use Klevu\PlatformPipelines\Exception\CouldNotGenerateConfigurationOverridesException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigurationOverridesRegenerateCommand extends Command
{
    public const COMMAND_NAME = 'klevu:indexing:configuration-overrides-regenerate';
    public const OPTION_ENTITY_TYPE = 'entity-type';

    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var PipelineConfigurationOverridesHandlerProviderInterface
     */
    private readonly PipelineConfigurationOverridesHandlerProviderInterface $pipelineConfigurationOverridesHandlerProvider; // phpcs:ignore Generic.Files.LineLength.TooLong

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param PipelineConfigurationOverridesHandlerProviderInterface $pipelineConfigurationOverridesHandlerProvider
     * @param string|null $name
     *
     * @throws LogicException
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        PipelineConfigurationOverridesHandlerProviderInterface $pipelineConfigurationOverridesHandlerProvider,
        ?string $name = null,
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->pipelineConfigurationOverridesHandlerProvider = $pipelineConfigurationOverridesHandlerProvider;

        parent::__construct($name);
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName(static::COMMAND_NAME);
        $this->setDescription(
            __(
                'Regenerates indexing pipeline overrides. '
                    . 'Warning: this will overwrite any modifications made to existing versions of these files',
            )->render(),
        );
        $this->addOption(
            name: static::OPTION_ENTITY_TYPE,
            mode: InputOption::VALUE_OPTIONAL + InputOption::VALUE_IS_ARRAY,
            description: __(
                'Regenerate overrides for this entity type only',
            )->render(),
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws InvalidArgumentException
     * @throws CouldNotGenerateConfigurationOverridesException
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    ): int {
        $isEnabled = $this->scopeConfig->isSetFlag(
            ConfigurationOverridesHandlerInterface::XML_PATH_CONFIGURATION_OVERRIDES_GENERATION_ENABLED,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0,
        );
        if (!$isEnabled) {
            $output->writeln(sprintf(
                '<info>%s</info>',
                __('Autogeneration of configuration overrides is disabled')->render(),
            ));
            $output->writeln(
                __('Please enable via Klevu developer settings in Stores > Configuration and retry')->render(),
            );

            return Cli::RETURN_SUCCESS;
        }

        $return = Cli::RETURN_SUCCESS;
        $entityTypes = $input->getOption(static::OPTION_ENTITY_TYPE);

        $output->writeln(
            __('Starting regeneration of pipelines configuration overrides')->render(),
        );
        foreach ($this->pipelineConfigurationOverridesHandlerProvider->get() as $entityType => $configurationOverridesHandlersForEntityType) { // phpcs:ignore Generic.Files.LineLength.TooLong
            if ($entityTypes && !in_array($entityType, $entityTypes, true)) {
                continue;
            }

            $output->write(sprintf(
                '* <info>%s</info>... ',
                $entityType,
            ));
            foreach ($configurationOverridesHandlersForEntityType as $configurationOverridesHandler) {
                $configurationOverridesHandler->execute();
            }
            $output->writeln(__('Complete')->render());
        }

        return $return;
    }
}
