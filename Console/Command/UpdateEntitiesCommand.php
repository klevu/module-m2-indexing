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

class UpdateEntitiesCommand extends Command
{
    public const COMMAND_NAME = 'klevu:indexing:entity-update';
    public const ENTITY_IDS_ALL = 'all';
    public const OPTION_API_KEYS = 'api-keys';
    public const OPTION_ENTITY_TYPES = 'entity-types';
    public const OPTION_ENTITY_IDS = 'entity-ids';

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
                'Recalculate entities next action and set in "klevu_indexing_entity" table.',
            ),
        );
        $this->addOption(
            name: static::OPTION_ENTITY_IDS,
            mode: InputOption::VALUE_REQUIRED,
            description: (string)__(
                'Update only these entities. Comma separate list e.g. --entity-ids 1,2,3 or --entity-ids %1',
                static::ENTITY_IDS_ALL,
            ),
        );
        $this->addOption(
            name: static::OPTION_API_KEYS,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Update Entities only for these API Keys (optional). Comma separated list '
                . 'e.g. --api-keys api-key-1,api-key-2',
            ),
        );
        $this->addOption(
            name: static::OPTION_ENTITY_TYPES,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Update entities only for these Entity Types (optional). '
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
                __('Begin Entity Update.'),
            ),
        );

        $entityIds = $input->getOption(static::OPTION_ENTITY_IDS);
        if (!$entityIds) {
            $output->writeln(
                messages: sprintf(
                    '<error>%s</error>',
                    __('Entity IDs are required.'),
                ),
            );
            $return = Cli::RETURN_FAILURE;
        } else {
            $success = $this->discoveryOrchestratorService->execute(
                entityTypes: $this->getEntityTypes($input),
                apiKeys: $this->getApiKeys($input),
                entityIds: $this->formatEntityIds($entityIds),
            );
            if ($success->isSuccess()) {
                $output->writeln(
                    messages: sprintf(
                        '<comment>%s</comment>',
                        __('Entity Update Completed Successfully.'),
                    ),
                );
            } else {
                $return = Cli::RETURN_FAILURE;
                $output->writeln(
                    messages: sprintf(
                        '<error>%s</error>',
                        __('Entity Update Failed. See Logs for more details.'),
                    ),
                );
            }
        }
        $endTime = microtime(true);
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln(
                messages: sprintf('<comment>%s</comment>',
                    __(
                        'Update operations complete in %1 seconds.',
                        number_format($endTime - $startTime, 2),
                    )),
            );
        }
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $output->writeln(
                messages: sprintf('<comment>%s</comment>',
                    __(
                        "Peak memory usage during update: %1Mb",
                        number_format(num: memory_get_peak_usage() / (1000 * 1000), decimals: 2),
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

    /**
     * @param mixed $entityIds
     *
     * @return int[]
     */
    private function formatEntityIds(mixed $entityIds): array
    {
        if ($entityIds === static::ENTITY_IDS_ALL) {
            return [];
        }

        return array_map(
            callback: 'intval',
            array: array_map(
                callback: 'trim',
                array: explode(separator: ',', string: $entityIds),
            ),
        );
    }
}
