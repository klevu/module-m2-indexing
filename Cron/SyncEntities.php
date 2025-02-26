<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Cron;

use Klevu\Indexing\Service\EntitySyncOrchestratorService;
use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Service\EntitySyncOrchestratorServiceInterface;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Psr\Log\LoggerInterface;

class SyncEntities
{
    /**
     * @var EntitySyncOrchestratorServiceInterface
     */
    private readonly EntitySyncOrchestratorServiceInterface $syncOrchestratorService;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var int[]
     */
    private array $batchCounts;

    /**
     * @param EntitySyncOrchestratorServiceInterface $syncOrchestratorService
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntitySyncOrchestratorServiceInterface $syncOrchestratorService,
        LoggerInterface $logger,
    ) {
        $this->syncOrchestratorService = $syncOrchestratorService;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info(
            message: 'Starting sync of entities.',
        );
        $results = $this->syncOrchestratorService->execute(
            via: 'Cron: ' . self::class,
        );
        $this->logPipelineResults(results: $results);
    }

    /**
     * @param \Generator<IndexerResultInterface> $results
     *
     * @return void
     */
    private function logPipelineResults(\Generator $results): void
    {
        /**
         * @var IndexerResultInterface $syncResult
         */
        foreach ($results as $key => $syncResult) {
            [$apiKey, $action] = explode(EntitySyncOrchestratorService::INDEXER_RESULT_KEY_CONCATENATOR, $key);
            $pipelineResult = $syncResult->getPipelineResult();
            if (!is_array($pipelineResult)) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'line' => __LINE__,
                        'message' => sprintf(
                            'Unexpected result from pipeline. Expected array<string, array<string, %s>>, received %s',
                            ApiPipelineResult::class,
                            get_debug_type($pipelineResult),
                        ),
                    ],
                );
                continue;
            }
            $this->batchCounts[$action] = $this->batchCounts[$action] ?? 0;

            foreach ($pipelineResult as $apiPipelineResults) {
                if (!is_array($apiPipelineResults)) {
                    continue;
                }
                $apiPipelineResults = array_filter(
                    $apiPipelineResults,
                    static fn (mixed $item): bool => ($item instanceof ApiPipelineResult),
                );
                foreach ($apiPipelineResults as $batch => $apiPipelineResult) {
                    $this->logger->info(
                        message: sprintf(
                            'Sync of entities for apiKey: %s, %s batch %s: completed %s. Job ID: %s',
                            $apiKey,
                            $action,
                            $batch + $this->batchCounts[$action],
                            $apiPipelineResult->success
                                ? 'successfully'
                                : 'with failures. See logs for more details',
                            $apiPipelineResult->apiResponse?->jobId ?? 'N/A',
                        ),
                    );
                }
                $this->batchCounts[$action] += count($apiPipelineResults);
            }
        }
    }
}
