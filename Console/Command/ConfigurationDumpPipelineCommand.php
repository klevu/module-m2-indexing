<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Console\Command;

use Klevu\Indexing\Service\Provider\PipelineConfigurationProvider;
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
     * @var PipelineConfigurationProvider
     */
    private readonly PipelineConfigurationProvider $pipelineConfigurationProvider;

    /**
     * @param PipelineConfigurationProvider $pipelineConfigurationProvider
     * @param string|null $name
     *
     * @throws LogicException
     */
    public function __construct(
       PipelineConfigurationProvider $pipelineConfigurationProvider,
        ?string $name = null,
    ) {
        $this->pipelineConfigurationProvider = $pipelineConfigurationProvider;

        parent::__construct($name);
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
        $pipelineConfiguration = $this->pipelineConfigurationProvider->get($pipelineIdentifier);
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
}
