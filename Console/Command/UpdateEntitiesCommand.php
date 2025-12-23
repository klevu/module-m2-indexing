<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Console\Command;

use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityTargetIdsRequireUpdateProviderInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateEntitiesCommand extends Command
{
    public const COMMAND_NAME = 'klevu:indexing:entity-update';
    public const ENTITY_IDS_ALL = 'all';
    public const ENTITY_IDS_REQUIRE_UPDATE = 'require-update';
    public const OPTION_API_KEYS = 'api-keys';
    public const OPTION_ENTITY_TYPES = 'entity-types';
    public const OPTION_ENTITY_IDS = 'entity-ids';

    /**
     * @var EntityDiscoveryOrchestratorServiceInterface
     */
    private EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;
    /**
     * @var IndexingEntityTargetIdsRequireUpdateProviderInterface|mixed
     */
    private IndexingEntityTargetIdsRequireUpdateProviderInterface $indexingEntityTargetIdsRequireUpdateProvider;

    /**
     * @param EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     * @param string|null $name
     * @param IndexingEntityTargetIdsRequireUpdateProviderInterface|null $indexingEntityTargetIdsRequireUpdateProvider
     */
    public function __construct(
        EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService,
        ?string $name = null,
        ?IndexingEntityTargetIdsRequireUpdateProviderInterface $indexingEntityTargetIdsRequireUpdateProvider = null,
    ) {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;

        $objectManager = ObjectManager::getInstance();
        $this->indexingEntityTargetIdsRequireUpdateProvider = $indexingEntityTargetIdsRequireUpdateProvider
            ?? $objectManager->get(IndexingEntityTargetIdsRequireUpdateProviderInterface::class);

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
                'Update only these entities. Comma separate list e.g. --%1 1,2,3, --%1 %2, or --%1 %3',
                static::OPTION_ENTITY_IDS,
                static::ENTITY_IDS_ALL,
                static::ENTITY_IDS_REQUIRE_UPDATE,
            ),
        );
        $this->addOption(
            name: static::OPTION_API_KEYS,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Update Entities only for these API Keys (optional). Comma separated list '
                . 'e.g. --%1 api-key-1,api-key-2',
                static::OPTION_API_KEYS,
            ),
        );
        $this->addOption(
            name: static::OPTION_ENTITY_TYPES,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Update entities only for these Entity Types (optional). '
                . 'Comma separated list e.g. --%1 KLEVU_CMS,KLEVU_PRODUCTS',
                static::OPTION_ENTITY_TYPES,
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
            return Cli::RETURN_FAILURE;
        }

        $entityTypes = $this->getEntityTypes($input);
        $apiKeys = $this->getApiKeys($input);
        foreach ($this->getChunkedEntityIds($input) as $entityIdsChunk) {
            $return = $this->processEntityIdsChunk(
                entityTypes: $entityTypes,
                apiKeys: $apiKeys,
                entityIds: $entityIdsChunk,
                output: $output,
            );
        }
        $output->writeln(
            messages: sprintf(
                '<comment>%s</comment>',
                __('Entity Update Completed.'),
            ),
        );

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
     * @param string[] $entityTypes
     * @param string[] $apiKeys
     * @param int[] $entityIds
     * @param OutputInterface $output
     *
     * @return void
     */
    private function processEntityIdsChunk(
        array $entityTypes,
        array $apiKeys,
        array $entityIds,
        OutputInterface $output,
    ): int {
        $responsesGenerator = $this->discoveryOrchestratorService->execute(
            entityTypes: $entityTypes,
            apiKeys: $apiKeys,
            entityIds: $entityIds,
        );

        $return = Cli::RETURN_SUCCESS;
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
            if ($output->getVerbosity() === OutputInterface::VERBOSITY_NORMAL) {
                $output->writeln(messages: '');
            } elseif ($output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL) {
                $output->writeln(messages: '  ...');
            }
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
     * @param InputInterface $input
     *
     * @return \Generator<array<int>>
     */
    private function getChunkedEntityIds(
        InputInterface $input,
    ): \Generator {
        $entityIds = $input->getOption(static::OPTION_ENTITY_IDS);

        switch (true) {
            case $entityIds === static::ENTITY_IDS_ALL:
                $chunkedEntityIds = [
                    [],
                ];
                break;

            case $entityIds === static::ENTITY_IDS_REQUIRE_UPDATE:
                $entityTypes = $this->getEntityTypes(input: $input);
                $apiKeys = $this->getApiKeys(input: $input) ?: null;

                $allEntityIds = [];
                foreach ($entityTypes ?: [null] as $entityType) {
                    $allEntityIds[] = $this->indexingEntityTargetIdsRequireUpdateProvider->get(
                        entityType: $entityType,
                        apiKeys: $apiKeys,
                    );
                }
                $allEntityIds = array_unique(
                    array: array_merge([], ...$allEntityIds),
                );

                $chunkedEntityIds = array_chunk(
                    array: $allEntityIds,
                    length: 100,
                );
                break;

            default:
                $entityIdsArray = array_map(
                    callback: 'trim',
                    array: explode(separator: ',', string: $entityIds),
                );
                $chunkedEntityIds = array_chunk(
                    array: $entityIdsArray,
                    length: 100,
                );
                break;
        }

        foreach ($chunkedEntityIds as $entityIdsChunk) {
            yield array_map(
                callback: 'intval',
                array: $entityIdsChunk,
            );
        }
    }
}
