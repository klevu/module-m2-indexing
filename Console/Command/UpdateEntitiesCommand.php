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
    public const OPTION_API_KEY = 'api-key';
    public const OPTION_ENTITY_TYPE = 'entity-type';
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

        $this->setName(static::COMMAND_NAME);
        $this->setDescription(
            (string)__('Recalculate entities next action and set in "klevu_indexing_entity" table.'),
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
            name: static::OPTION_API_KEY,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__('Update entities only for this API Key (optional).'),
        );
        $this->addOption(
            name: static::OPTION_ENTITY_TYPE,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__('Update entities only for this Entity Type (optional).'),
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
        $return = Cli::RETURN_SUCCESS;
        $output->writeln(
            sprintf(
                '<comment>%s</comment>',
                __('Begin Entity Update.'),
            ),
        );

        $entityIds = $input->getOption(static::OPTION_ENTITY_IDS);
        if (!$entityIds) {
            $output->writeln(
                sprintf(
                    '<error>%s</error>',
                    __('Entity IDs are required.'),
                ),
            );
            $return = Cli::RETURN_FAILURE;
        } else {
            $apiKey = $input->getOption(static::OPTION_API_KEY);
            $success = $this->discoveryOrchestratorService->execute(
                entityType: $input->getOption(static::OPTION_ENTITY_TYPE),
                apiKeys: $apiKey ? [$apiKey] : null,
                entityIds: $entityIds === static::ENTITY_IDS_ALL
                    ? []
                    : array_map('intval', explode(',', $entityIds)),
            );
            if ($success->isSuccess()) {
                $output->writeln(
                    sprintf(
                        '<comment>%s</comment>',
                        __('Entity Update Completed Successfully.'),
                    ),
                );
            } else {
                $return = Cli::RETURN_FAILURE;
                $output->writeln(
                    sprintf(
                        '<error>%s</error>',
                        __('Entity Update Failed. See Logs for more details.'),
                    ),
                );
            }
        }

        return $return;
    }
}
