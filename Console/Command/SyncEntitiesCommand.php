<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Console\Command;

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
    public const OPTION_API_KEY = 'api-key';
    public const OPTION_ENTITY_TYPE = 'entity-type';

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
            name: static::OPTION_API_KEY,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__('Sync entities only for this API key (optional).'),
        );
        $this->addOption(
            name: static::OPTION_ENTITY_TYPE,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__('Sync entities only for this attribute type (optional).'),
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

        $entityType = $input->getOption(static::OPTION_ENTITY_TYPE);
        $apiKey = $input->getOption(static::OPTION_API_KEY);
        $filters = [];
        if ($entityType) {
            $filters[] = __('Entity Type = %1', $entityType);
        }
        if ($apiKey) {
            $filters[] = __('API Key = %1', $apiKey);
        }
        $output->writeln('');
        $output->writeln(
            sprintf(
                '<comment>%s</comment>',
                __('Begin Entity Sync with filters: %1.', implode(', ', $filters)),
            ),
        );
        $output->writeln('----');

        $results = $this->syncOrchestratorService->execute(
            entityType: $entityType,
            apiKey: $apiKey,
            via: 'CLI::' . static::COMMAND_NAME,
        );
        $return = $this->processResponse(output: $output, results: $results);

        $endTime = microtime(true);
        $output->writeln(
            sprintf('<comment>%s</comment>',
                __(
                    'Sync operations complete in %1 seconds.',
                    number_format($endTime - $startTime, 2),
                )),
        );

        return $return;
    }

    /**
     * @param OutputInterface $output
     * @param IndexerResultInterface[] $results
     *
     * @return int
     * @throws LocalizedException
     */
    private function processResponse(
        OutputInterface $output,
        array $results,
    ): int {
        $return = Cli::RETURN_SUCCESS;
        if (!$results) {
            $output->writeln(
                sprintf(
                    '<comment>%s</comment>',
                    __('No entities were found that require syncing.'),
                ),
            );

            return $return;
        }
        $outputSuccessMessage = true;
        foreach ($results as $apiKey => $syncResult) {
            $failures = in_array(
                $syncResult->getStatus(),
                [IndexerResultStatuses::ERROR, IndexerResultStatuses::PARTIAL],
                true,
            );
            if ($failures) {
                $outputSuccessMessage = false;
            }
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln(
                    sprintf(
                        '<comment>%s</comment>',
                        __('Entity Sync for API Key: %1.', $apiKey),
                    ),
                );
            }
            if ($syncResult->getStatus() === IndexerResultStatuses::ERROR) {
                $return = Cli::RETURN_FAILURE;
                $output->writeln(
                    sprintf('<error>%s</error>', __(IndexerResultStatuses::ERROR->value)->render()),
                );
            } else {
                $output->writeln(
                    (string)__($syncResult->getStatus()->value),
                );
            }
            foreach ($syncResult->getMessages() as $message) {
                $output->writeln($message);
            }

            $this->processPipelineResultOutput($syncResult, $output);
            $output->writeln('----');
        }
        if ($outputSuccessMessage) {
            $output->writeln(
                sprintf(
                    '<comment>%s</comment>',
                    __('Entity sync command completed successfully.'),
                ),
            );
        } else {
            $output->writeln(
                sprintf(
                    '<error>%s</error>',
                    __('All or part of Entity Sync Failed. See Logs for more details.'),
                ),
            );
        }

        return $return;
    }

    /**
     * @param IndexerResultInterface $syncResult
     * @param OutputInterface $output
     *
     * @return void
     * @throws LocalizedException
     */
    private function processPipelineResultOutput(IndexerResultInterface $syncResult, OutputInterface $output): void
    {
        $pipelineResult = $syncResult->getPipelineResult();
        if (!is_array($pipelineResult)) {
            throw new LocalizedException(__(
                'Unexpected result from pipeline. Expected array<string, %1>, received %2',
                ApiPipelineResult::class,
                get_debug_type($pipelineResult),
            ));
        }
        /**
         * @var string $action
         * @var ApiPipelineResult[] $apiPipelineResults
         */
        foreach ($pipelineResult as $action => $apiPipelineResults) {
            if (!is_array($apiPipelineResults)) {
                continue;
            }
            $apiPipelineResults = array_filter(
                $apiPipelineResults,
                static fn (mixed $item): bool => ($item instanceof ApiPipelineResult),
            );
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
                $output->writeln(' --');
                $output->writeln(
                    __(' Action  : %1', $action)->render(),
                );
                $output->writeln(
                    __(' Batches : %1', count($apiPipelineResults))->render(),
                );
                $output->writeln('');
            }

            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
                $this->processBatchOutput($apiPipelineResults, $output);
            }
        }
    }

    /**
     * @param ApiPipelineResult[] $apiPipelineResults
     * @param OutputInterface $output
     *
     * @return void
     */
    private function processBatchOutput(array $apiPipelineResults, OutputInterface $output): void
    {
        foreach ($apiPipelineResults as $batch => $apiPipelineResult) {
            $output->writeln(
                __('  Batch        : %1', $batch)->render(),
            );
            $output->writeln(
                __(
                    '  Success      : %1',
                    $apiPipelineResult->success
                        ? 'True'
                        : 'False',
                )->render(),
            );
            $output->writeln(
                __('  API Response : %1', $apiPipelineResult->apiResponse?->getResponseCode())->render(),
            );
            $output->writeln(
                __('  Job ID       : %1', $apiPipelineResult->apiResponse?->jobId ?? 'n/a')->render(),
            );
            $output->writeln(
                __('  Record Count : %1', count($apiPipelineResult->payload ?? []))->render(),
            );
            foreach ($apiPipelineResult->apiResponse?->getMessages() ?? [] as $message) {
                $output->writeln('  ' . $message);
            }
            $output->writeln('');
        }
    }
}
