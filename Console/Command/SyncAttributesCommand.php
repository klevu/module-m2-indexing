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
    public const OPTION_API_KEYS = 'api-keys';
    public const OPTION_ATTRIBUTE_TYPES = 'attribute-types';

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

        $this->setName(name: static::COMMAND_NAME);
        $this->setDescription(
            description: (string)__('Sync attributes with Klevu.'),
        );
        $this->addOption(
            name: static::OPTION_API_KEYS,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Sync Attributes only for these API Keys (optional). Comma separated list '
                . 'e.g. --api-keys api-key-1,api-key-2',
            ),
        );
        $this->addOption(
            name: static::OPTION_ATTRIBUTE_TYPES,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Sync attributes only for these attribute types (optional). '
                . 'Comma separated list e.g. --attribute-types KLEVU_CMS,KLEVU_PRODUCTS',
            ),
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
        $attributeTypes = $this->getAttributeTypes(input: $input);
        $apiKeys = $this->getApiKeys(input: $input);
        $filters = [];
        if ($attributeTypes) {
            $filters[] = __('Attribute Types = %1', implode(separator: ', ', array: $attributeTypes));
        }
        if ($apiKeys) {
            $filters[] = __('API Keys = %1', implode(separator: ', ', array: $apiKeys));
        }
        $output->writeln('');
        $output->writeln(
            messages: sprintf(
                '<comment>%s</comment>',
                __('Begin Attribute Sync with filters: %1.', implode(separator: ', ', array: $filters)),
            ),
        );
        $results = $this->syncOrchestratorService->execute(
            attributeTypes: $attributeTypes,
            apiKeys: $apiKeys,
        );

        if (!$results) {
            $output->writeln(
                messages: sprintf(
                    '<comment>%s</comment>',
                    __('No attributes were found that require syncing.'),
                ),
            );

            return Cli::RETURN_FAILURE;
        }

        foreach ($results as $apiKey => $actions) {
            if ($verbosity >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(
                    messages: sprintf(
                        '<comment>%s</comment>',
                        __('Attribute Sync for API Key: %1.', $apiKey),
                    ),
                );
            }
            foreach ($actions as $action => $attributes) {
                if ($verbosity >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
                    $output->writeln(
                        messages: sprintf(
                            '<comment>%s</comment>',
                            __('Attribute Sync for Action: %1.', $action),
                        ),
                    );
                }
                foreach ($attributes as $attributeCode => $syncResult) {
                    if ($syncResult->isSuccess()) {
                        if ($verbosity >= OutputInterface::VERBOSITY_DEBUG) {
                            $output->writeln(
                                messages: sprintf(
                                    '<comment>%s</comment>',
                                    __(
                                        'Attribute Sync for Attribute: "%1" Completed Successfully.',
                                        $attributeCode,
                                    ),
                                ),
                            );
                        }
                        continue;
                    }
                    if ($verbosity >= OutputInterface::VERBOSITY_DEBUG) {
                        $output->writeln(
                            messages: sprintf(
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
                messages: sprintf(
                    '<comment>%s</comment>',
                    __('Attribute Sync Completed Successfully.'),
                ),
            );
        } else {
            $output->writeln(
                messages: sprintf(
                    '<error>%s</error>',
                    __('All or part of Attribute Sync Failed. See Logs for more details.'),
                ),
            );
        }

        return $return;
    }

    /**
     * @param InputInterface $input
     *
     * @return string[]
     */
    private function getApiKeys(InputInterface $input): array
    {
        $apiKeys = $input->getOption(static::OPTION_API_KEYS);

        return $apiKeys
            ? array_map(callback: 'trim', array: explode(',', $apiKeys))
            : [];
    }

    /**
     * @param InputInterface $input
     *
     * @return string[]
     */
    private function getAttributeTypes(InputInterface $input): array
    {
        $attributeTypes = $input->getOption(static::OPTION_ATTRIBUTE_TYPES);

        return $attributeTypes
            ? array_map(callback: 'trim', array: explode(',', $attributeTypes))
            : [];
    }
}
