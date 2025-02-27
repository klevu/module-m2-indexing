<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Console\Command;

use Klevu\Indexing\Service\EntitySyncOrchestratorService;
use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;
use Klevu\IndexingApi\Service\EntitySyncOrchestratorServiceInterface;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncEntitiesCommand extends Command
{
    public const COMMAND_NAME = 'klevu:indexing:entity-sync';
    public const OPTION_API_KEYS = 'api-keys';
    public const OPTION_ENTITY_TYPES = 'entity-types';

    /**
     * @var EntitySyncOrchestratorServiceInterface
     */
    private readonly EntitySyncOrchestratorServiceInterface $syncOrchestratorService;

    /**
     * @param EntitySyncOrchestratorServiceInterface $syncOrchestratorService
     * @param string|null $name
     */
    public function __construct(
        EntitySyncOrchestratorServiceInterface $syncOrchestratorService,
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
            (string)__('Sync entities with Klevu.'),
        );
        $this->addOption(
            name: static::OPTION_API_KEYS,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Sync Entities only for these API Keys (optional). Comma separated list '
                . 'e.g. --api-keys api-key-1,api-key-2',
            ),
        );
        $this->addOption(
            name: static::OPTION_ENTITY_TYPES,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Sync entities only for this Entity Type (optional). '
                . 'Comma separated list e.g. --entity-types KLEVU_CMS, KLEVU_PRODUCTS',
            ),
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws LocalizedException
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $startTime = microtime(true);

        $entityTypes = $this->getEntityTypes(input: $input);
        $apiKeys = $this->getApiKeys(input: $input);
        $filters = [];
        if ($entityTypes) {
            $filters[] = __('Entity Types = %1', implode(', ', $entityTypes));
        }
        if ($apiKeys) {
            $filters[] = __('API Keys = %1', implode(', ', $apiKeys));
        }
        $output->writeln('');
        $output->writeln(
            messages: sprintf(
                '<comment>%s</comment>',
                __('Begin Entity Sync with filters: %1.', implode(', ', $filters)),
            ),
        );
        $output->writeln('----');

        $results = $this->syncOrchestratorService->execute(
            entityTypes: $entityTypes,
            apiKeys: $apiKeys,
            via: 'CLI::' . static::COMMAND_NAME,
        );
        $return = Cli::RETURN_SUCCESS;
        $batchCounts = [];
        $count = 0;
        foreach ($results as $key => $result) {
            [$success, $batchCounts] = $this->processResponse(
                output: $output,
                key: $key,
                syncResult: $result,
                batchCounts: $batchCounts,
            );
            $return += $success;
            $count++;
        }
        if (!$count) {
            $output->writeln(
                messages: sprintf(
                    '<comment>%s</comment>',
                    __('No entities were found that require syncing.'),
                ),
            );
        }

        $endTime = microtime(true);
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln(
                messages: sprintf('<comment>%s</comment>',
                    __(
                        'Sync operations complete in %1 seconds.',
                        number_format($endTime - $startTime, 2),
                    )),
            );
        }
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $output->writeln(
                messages: sprintf('<comment>%s</comment>',
                    __(
                        "Peak memory usage during sync: %1Mb",
                        number_format(
                            num: memory_get_peak_usage(real_usage: true) / (1024 * 1024),
                            decimals: 2,
                        ),
                    ),
                ),
            );
        }
        if ($return === Cli::RETURN_SUCCESS) {
            $output->writeln(
                messages: sprintf(
                    '<comment>%s</comment>',
                    __('Entity sync completed successfully.'),
                ),
            );
        } else {
            $output->writeln(
                messages: sprintf(
                    '<error>%s</error>',
                    __('All or part of entity sync failed. See logs for more details.'),
                ),
            );
        }

        return $return === Cli::RETURN_SUCCESS ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;
    }

    /**
     * @param OutputInterface $output
     * @param string $key
     * @param IndexerResultInterface $syncResult
     * @param array<string, int> $batchCounts
     *
     * @return array<int, int|array<string, int>>
     * @throws LocalizedException
     */
    private function processResponse(
        OutputInterface $output,
        string $key,
        IndexerResultInterface $syncResult,
        array $batchCounts,
    ): array {
        $return = Cli::RETURN_SUCCESS;
        [$apiKey, $action] = explode(EntitySyncOrchestratorService::INDEXER_RESULT_KEY_CONCATENATOR, $key);
        $failures = in_array(
            needle: $syncResult->getStatus(),
            haystack: [IndexerResultStatuses::ERROR, IndexerResultStatuses::PARTIAL],
            strict: true,
        );
        if ($failures) {
            $return = Cli::RETURN_FAILURE;
        }
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
            $output->writeln(
                messages: sprintf(
                    '<comment>%s</comment>',
                    __('Entity sync for API key: %1.', $apiKey),
                ),
            );
            $output->writeln(
                messages: sprintf(
                    '<comment>%s</comment>',
                    __('Action: %1', $action)->render(),
                ),
            );
        }
        if ($syncResult->getStatus() === IndexerResultStatuses::ERROR) {
            $return = Cli::RETURN_FAILURE;
            $output->writeln(
                messages: sprintf(
                    '<error>%s</error>',
                    __(IndexerResultStatuses::ERROR->value)->render(),
                ),
            );
        } else {
            $output->writeln(
                messages: (string)__($syncResult->getStatus()->value),
            );
        }
        foreach ($syncResult->getMessages() as $message) {
            $output->writeln(messages: $message);
        }

        $batchCounts[$action] = $this->processPipelineResultOutput(
            syncResult: $syncResult,
            output: $output,
            batchCount: $batchCounts[$action] ?? 0,
        );
        $output->writeln(messages: '----');

        return [$return, $batchCounts];
    }

    /**
     * @param IndexerResultInterface $syncResult
     * @param OutputInterface $output
     * @param int $batchCount
     *
     * @return int
     * @throws LocalizedException
     */
    private function processPipelineResultOutput(
        IndexerResultInterface $syncResult,
        OutputInterface $output,
        int $batchCount,
    ): int {
        $pipelineResult = $syncResult->getPipelineResult();
        if (!is_array($pipelineResult)) {
            throw new LocalizedException(__(
                'Unexpected result from pipeline. Expected array<string, array<string, %1>>, received %2',
                ApiPipelineResult::class,
                get_debug_type($pipelineResult),
            ));
        }
        foreach ($pipelineResult as $apiPipelineResults) {
            if (!is_array($apiPipelineResults)) {
                continue;
            }
            $apiPipelineResults = array_filter(
                $apiPipelineResults,
                static fn (mixed $item): bool => ($item instanceof ApiPipelineResult),
            );
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                $output->writeln(messages: '');
                $this->processBatchOutput(
                    apiPipelineResults: $apiPipelineResults,
                    output: $output,
                    batchCount: $batchCount,
                );
            }
            $batchCount += count($apiPipelineResults);
        }
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $output->writeln(
                messages: __(' Batches processed : %1', $batchCount)->render(),
            );
        }

        return $batchCount;
    }

    /**
     * @param ApiPipelineResult[] $apiPipelineResults
     * @param OutputInterface $output
     *
     * @return void
     */
    private function processBatchOutput(array $apiPipelineResults, OutputInterface $output, int $batchCount): void
    {
        foreach ($apiPipelineResults as $batch => $apiPipelineResult) {
            $output->writeln(
                messages: __('  Batch        : %1', $batch + $batchCount)->render(),
            );
            $output->writeln(
                messages: __(
                    '  Success      : %1',
                    $apiPipelineResult->success
                        ? 'True'
                        : 'False',
                )->render(),
            );
            $output->writeln(
                messages: __('  API Response : %1', $apiPipelineResult->apiResponse?->getResponseCode())->render(),
            );
            $output->writeln(
                messages: __('  Job ID       : %1', $apiPipelineResult->apiResponse?->jobId ?? 'n/a')->render(),
            );
            $output->writeln(
                messages: __('  Record Count : %1', count($apiPipelineResult->payload ?? []))->render(),
            );
            foreach ($apiPipelineResult->apiResponse?->getMessages() ?? [] as $message) {
                $output->writeln(messages: '  ' . $message);
            }
            $output->writeln(messages: '');
        }
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
