<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Console\Command;

use Klevu\IndexingApi\Service\AttributeSyncOrchestratorServiceInterface;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncAttributesCommand extends Command
{
    public const COMMAND_NAME = 'klevu:indexing:attribute-sync';
    public const OPTION_API_KEY = 'api-key';
    public const OPTION_ATTRIBUTE_TYPE = 'attribute-type';

    /**
     * @var AttributeSyncOrchestratorServiceInterface
     */
    private AttributeSyncOrchestratorServiceInterface $syncOrchestratorService;

    /**
     * @param AttributeSyncOrchestratorServiceInterface $syncOrchestratorService
     * @param string|null $name
     */
    public function __construct(
        AttributeSyncOrchestratorServiceInterface $syncOrchestratorService,
        ?string $name = null,
    ) {
        $this->syncOrchestratorService = $syncOrchestratorService;

        parent::__construct($name);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName(static::COMMAND_NAME);
        $this->setDescription(
            (string)__('Sync attributes with Klevu.'),
        );
        $this->addOption(
            name: static::OPTION_API_KEY,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__('Sync attributes only for this API key (optional).'),
        );
        $this->addOption(
            name: static::OPTION_ATTRIBUTE_TYPE,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__('Sync attributes only for this attribute type (optional).'),
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $verbosity = $output->getVerbosity();
        $return = Cli::RETURN_SUCCESS;
        $attributeType = $input->getOption(static::OPTION_ATTRIBUTE_TYPE);
        $apiKey = $input->getOption(static::OPTION_API_KEY);
        $filters = [];
        if ($attributeType) {
            $filters[] = __('Attribute Type = %1', $attributeType);
        }
        if ($apiKey) {
            $filters[] = __('API Key = %1', $apiKey);
        }
        $output->writeln('');
        $output->writeln(
            sprintf(
                '<comment>%s</comment>',
                __('Begin Attribute Sync with filters: %1.', implode(', ', $filters)),
            ),
        );
        $results = $this->syncOrchestratorService->execute(
            attributeType: $attributeType,
            apiKey: $apiKey,
        );

        if (!$results) {
            $output->writeln(
                sprintf(
                    '<comment>%s</comment>',
                    __('No attributes were found that require syncing.'),
                ),
            );

            return Cli::RETURN_FAILURE;
        }

        foreach ($results as $apiKey => $actions) {
            if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(
                    sprintf(
                        '<comment>%s</comment>',
                        __('Attribute Sync for API Key: %1.', $apiKey),
                    ),
                );
            }
            foreach ($actions as $action => $attributes) {
                if ($verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
                    $output->writeln(
                        sprintf(
                            '<comment>%s</comment>',
                            __('Attribute Sync for Action: %1.', $action),
                        ),
                    );
                }
                foreach ($attributes as $attributeCode => $syncResult) {
                    if ($syncResult->isSuccess()) {
                        if ($verbosity >= OutputInterface::VERBOSITY_DEBUG) {
                            $output->writeln(
                                sprintf(
                                    '<comment>%s</comment>',
                                    __('Attribute Sync for Attribute: "%1" Completed Successfully.', $attributeCode),
                                ),
                            );
                        }
                        continue;
                    }
                    if ($verbosity >= OutputInterface::VERBOSITY_DEBUG) {
                        $output->writeln(
                            sprintf(
                                '<error>%s</error>',
                                __(
                                    'Attribute Sync for Attribute: "%1" Failed. Errors: %2',
                                    $attributeCode,
                                    implode('; ', $syncResult->getMessages()),
                                ),
                            ),
                        );
                    }
                    $return = Cli::RETURN_FAILURE;
                }
            }
        }
        if ($return === Cli::RETURN_SUCCESS) {
            $output->writeln(
                sprintf(
                    '<comment>%s</comment>',
                    __('Attribute Sync Completed Successfully.'),
                ),
            );
        } else {
            $output->writeln(
                sprintf(
                    '<error>%s</error>',
                    __('All or part of Attribute Sync Failed. See Logs for more details.'),
                ),
            );
        }

        return $return;
    }
}
