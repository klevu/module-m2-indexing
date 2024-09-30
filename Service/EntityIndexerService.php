<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Api\Data\IndexerResultInterfaceFactory;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;
use Klevu\IndexingApi\Service\EntityIndexerServiceInterface;
use Klevu\IndexingApi\Service\Provider\Sync\EntityIndexingRecordProviderInterface;
use Klevu\PhpSDK\Exception\ValidationException as PhpSDKValidationException;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\Pipelines\Exception\ExtractionException;
use Klevu\Pipelines\Exception\HasErrorsExceptionInterface;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelineConfigurationException;
use Klevu\Pipelines\Exception\Pipeline\StageException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Pipeline\ContextFactory as PipelineContextFactory;
use Klevu\Pipelines\Pipeline\PipelineBuilderInterface;
use Klevu\Pipelines\Pipeline\PipelineInterface;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;
use Klevu\PlatformPipelines\Api\PipelineConfigurationProviderInterface;
use Klevu\PlatformPipelines\Api\PipelineContextProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Psr\Log\LoggerInterface;

class EntityIndexerService implements EntityIndexerServiceInterface
{
    /**
     * @var PipelineBuilderInterface
     */
    private readonly PipelineBuilderInterface $pipelineBuilder;
    /**
     * @var EntityIndexingRecordProviderInterface
     */
    private readonly EntityIndexingRecordProviderInterface $entityIndexingRecordProvider;
    /**
     * @var PipelineContextFactory
     */
    private readonly PipelineContextFactory $pipelineContextFactory;
    /**
     * @var PipelineContextProviderInterface[]
     */
    private array $pipelineContextProviders = [];
    /**
     * @var IndexerResultInterfaceFactory
     */
    private readonly IndexerResultInterfaceFactory $indexerResultFactory;
    /**
     * @var string
     */
    private string $pipelineConfigurationFilepath;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var string[]
     */
    private readonly array $pipelineConfigurationOverridesFilepaths;
    /**
     * @var ConfigurationOverridesHandlerInterface
     */
    private ConfigurationOverridesHandlerInterface $configurationOverridesHandler;

    /**
     * @param PipelineBuilderInterface $pipelineBuilder
     * @param EntityIndexingRecordProviderInterface $entityIndexingRecordProvider
     * @param PipelineContextFactory $pipelineContextFactory
     * @param PipelineContextProviderInterface[] $pipelineContextProviders
     * @param IndexerResultInterfaceFactory $indexerResultFactory
     * @param LoggerInterface $logger
     * @param string $pipelineIdentifier
     * @param PipelineConfigurationProviderInterface $pipelineConfigurationProvider
     * @param ConfigurationOverridesHandlerInterface $configurationOverridesHandler
     *
     * @throws NotFoundException
     */
    public function __construct(
        PipelineBuilderInterface $pipelineBuilder,
        EntityIndexingRecordProviderInterface $entityIndexingRecordProvider,
        PipelineContextFactory $pipelineContextFactory,
        array $pipelineContextProviders,
        IndexerResultInterfaceFactory $indexerResultFactory,
        LoggerInterface $logger,
        string $pipelineIdentifier,
        PipelineConfigurationProviderInterface $pipelineConfigurationProvider,
        ConfigurationOverridesHandlerInterface $configurationOverridesHandler,
    ) {
        $this->pipelineBuilder = $pipelineBuilder;
        $this->entityIndexingRecordProvider = $entityIndexingRecordProvider;
        $this->pipelineContextFactory = $pipelineContextFactory;
        array_walk(
            array: $pipelineContextProviders,
            callback: [$this, 'addPipelineContextProvider'],
        );
        $this->indexerResultFactory = $indexerResultFactory;
        $this->logger = $logger;

        $this->pipelineConfigurationFilepath = $pipelineConfigurationProvider
            ->getPipelineConfigurationFilepathByIdentifier($pipelineIdentifier);
        $this->pipelineConfigurationOverridesFilepaths = $pipelineConfigurationProvider
            ->getPipelineConfigurationOverridesFilepathsByIdentifier($pipelineIdentifier);
        $this->configurationOverridesHandler = $configurationOverridesHandler;
    }

    /**
     * @param string $apiKey
     * @param string|null $via
     *
     * @return IndexerResultInterface
     * @throws InvalidPipelineConfigurationException
     */
    public function execute(
        string $apiKey,
        ?string $via = '',
    ): IndexerResultInterface {
        $pipeline = $this->buildPipeline();
        $messages = [];
        try {
            $pipelineResult = $pipeline->execute(
                payload: $this->entityIndexingRecordProvider->get($apiKey),
                context: $this->getPipelineContext($via),
            );
            $status = $this->getStatus($pipelineResult);
        } catch (HasErrorsExceptionInterface | PhpSDKValidationException $pipelineException) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $pipelineException->getMessage(),
                ],
            );
            $status = IndexerResultStatuses::ERROR;
            $messages = array_merge(
                $messages,
                [$pipelineException->getMessage()],
                $pipelineException instanceof HasErrorsExceptionInterface
                    ? $pipelineException->getErrors()
                    : [],
            );
        } catch (StageException $stageException) {
            $message = $stageException->getMessage();
            $previousException = $stageException->getPrevious();
            if ($previousException) {
                $message .= ' ' . $previousException->getMessage();
                if ($previousException instanceof TransformationException) {
                    $message .= ' - ' . $previousException->getTransformerName();
                    foreach ($previousException->getErrors() as $error) {
                        $message .= ' - ' . $error;
                    }
                }
            }

            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $message,
                ],
            );
            $status = IndexerResultStatuses::ERROR;
            $messages = array_merge(
                $messages,
                [$stageException->getPrevious()?->getMessage()],
            );
        } catch (ExtractionException | LocalizedException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
            $status = IndexerResultStatuses::ERROR;
            $messages = array_merge(
                $messages,
                [$exception->getMessage()],
            );
        }

        return $this->generateIndexerResult(
            status: $status,
            messages: $messages,
            pipelineResult: $pipelineResult ?? null,
        );
    }

    /**
     * @param PipelineContextProviderInterface $pipelineContextProvider
     * @param string $contextKey
     *
     * @return void
     */
    private function addPipelineContextProvider(
        PipelineContextProviderInterface $pipelineContextProvider,
        string $contextKey,
    ): void {
        $this->pipelineContextProviders[$contextKey] = $pipelineContextProvider;
    }

    /**
     * @return PipelineInterface
     * @throws InvalidPipelineConfigurationException
     */
    private function buildPipeline(): PipelineInterface
    {
        try {
            $this->configurationOverridesHandler->execute();
            $pipeline = $this->pipelineBuilder->buildFromFiles(
                configurationFilepath: $this->pipelineConfigurationFilepath,
                overridesFilepaths: $this->pipelineConfigurationOverridesFilepaths,
            );
        } catch (\TypeError $exception) { // @phpstan-ignore-line TypeError can be thrown by buildFromFiles
            throw new InvalidPipelineConfigurationException(
                pipelineName: null,
                message: $exception->getMessage(),
                code: $exception->getCode(),
                previous: $exception,
            );
        }

        return $pipeline;
    }

    /**
     * @param string $via
     *
     * @return \ArrayAccess<int|string, mixed>
     */
    private function getPipelineContext(
        string $via,
    ): \ArrayAccess {
        $data = array_map(
            callback: static fn (
                PipelineContextProviderInterface $pipelineContextProvider,
            ): array|object => $pipelineContextProvider->get(),
            array: $this->pipelineContextProviders,
        );

        $data['system'] ??= [];
        $data['system']['via'] = $via;

        return $this->pipelineContextFactory->create([
            'data' => $data,
        ]);
    }

    /**
     * @param mixed $pipelineResult
     *
     * @return IndexerResultStatuses
     * @throws LocalizedException
     */
    private function getStatus(mixed $pipelineResult): IndexerResultStatuses
    {
        if (!$pipelineResult) {
            return IndexerResultStatuses::NOOP;
        }
        if (!is_array($pipelineResult)) {
            throw new LocalizedException(__(
                'Unexpected result from pipeline. Expected array<string, %1>, received %2',
                ApiPipelineResult::class,
                get_debug_type($pipelineResult),
            ));
        }
        $failures = array_filter(
            array: $pipelineResult,
            callback: static fn ($apiPipelineResult) => !$apiPipelineResult->success,
        );

        return count($failures)
            ? IndexerResultStatuses::PARTIAL
            : IndexerResultStatuses::SUCCESS;
    }

    /**
     * @param IndexerResultStatuses $status
     * @param string[] $messages
     * @param mixed $pipelineResult
     *
     * @return IndexerResultInterface
     */
    private function generateIndexerResult(
        IndexerResultStatuses $status,
        array $messages,
        mixed $pipelineResult,
    ): IndexerResultInterface {
        /** @var IndexerResultInterface $return */
        $return = $this->indexerResultFactory->create();
        $return->setStatus(status: $status);
        $return->setMessages(messages: $messages);
        $return->setPipelineResult(pipelineResult: $pipelineResult);

        return $return;
    }
}
