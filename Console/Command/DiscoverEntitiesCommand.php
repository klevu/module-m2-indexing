<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Console\Command;

use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiscoverEntitiesCommand extends Command
{
    public const COMMAND_NAME = 'klevu:indexing:entity-discovery';
    public const OPTION_API_KEYS = 'api-keys';
    public const OPTION_ENTITY_TYPES = 'entity-types';

    /**
     * @var EntityDiscoveryOrchestratorServiceInterface
     */
    private EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;

    /**
     * @param EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     * @param string|null $name
     */
    public function __construct(
        EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService,
        ?string $name = null,
    ) {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;

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
            description: (string)__(
                'Find Entities and add them to "klevu_indexing_entity" table so they can be indexed.',
            ),
        );
        $this->addOption(
            name: static::OPTION_API_KEYS,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Discover Entities only for these API Keys (optional). Comma separated list '
                . 'e.g. --api-keys api-key-1,api-key-2',
            ),
        );
        $this->addOption(
            name: static::OPTION_ENTITY_TYPES,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Discover Entities only for these Entity Types (optional). '
                . 'Comma separated list e.g. --entity-types KLEVU_CMS,KLEVU_PRODUCTS',
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
        $startTime = microtime(true);

        $return = Cli::RETURN_SUCCESS;
        $output->writeln(
            messages: sprintf(
                '<comment>%s</comment>',
                __('Begin Entity Discovery.'),
            ),
        );

        $responsesGenerator = $this->discoveryOrchestratorService->execute(
            entityTypes: $this->getEntityTypes(input: $input),
            apiKeys: $this->getApiKeys(input: $input),
        );

        foreach ($responsesGenerator as $responses) {
            $count = 1;
            foreach ($responses as $response) {
                if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
                    $output->write(messages: '.');
                }
                if ($response->isSuccess()) {
                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $output->writeln(
                            messages: sprintf(
                                '<comment>  %s</comment>',
                                __(
                                    'Discover %1 to %2 Batch %3 Completed Successfully.',
                                    $response->getEntityType(),
                                    $response->getAction(),
                                    $count,
                                ),
                            ),
                        );
                    }
                } else {
                    $return = Cli::RETURN_FAILURE;
                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $output->writeln(
                            messages: sprintf(
                                '<error>  %s</error>',
                                __(
                                    'Discover %1 to %2 batch %3 Failed. See Logs for more details.',
                                    $response->getEntityType(),
                                    $response->getAction(),
                                    $count,
                                ),
                            ),
                        );
                    }
                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                        $output->writeln(
                            messages: sprintf('<error>   %s</error>',
                                __(
                                    "Error Messages: %1",
                                    implode(',' . PHP_EOL, $response->getMessages()),
                                ),
                            ),
                        );
                    }
                }
                $count++;
            }
            if ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
                $output->writeln(messages: ' ...');
            }
        }

        $output->writeln(
            messages: sprintf(
                '<comment>%s</comment>',
                __('Entity Discovery Completed.'),
            ),
        );
        $endTime = microtime(true);
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln(
                messages: sprintf('<comment>%s</comment>',
                    __(
                        'Discovery operations complete in %1 seconds.',
                        number_format($endTime - $startTime, 2),
                    )),
            );
        }
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $output->writeln(
                messages: sprintf('<comment>%s</comment>',
                    __(
                        "Peak memory usage during discovery: %1Mb",
                        number_format(
                            num: memory_get_peak_usage(real_usage: true) / (1024 * 1024),
                            decimals: 2,
                        ),
                    ),
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
    private function getEntityTypes(InputInterface $input): array
    {
        $entityTypes = $input->getOption(static::OPTION_ENTITY_TYPES);

        return $entityTypes
            ? array_map(callback: 'trim', array: explode(',', $entityTypes))
            : [];
    }
}
