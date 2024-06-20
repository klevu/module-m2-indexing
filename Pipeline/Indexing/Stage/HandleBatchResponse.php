<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Pipeline\Indexing\Stage;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\BatchResponderServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\PhpSDK\Model\Indexing\RecordIterator;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\Pipelines\Exception\ExtractionException;
use Klevu\Pipelines\Exception\ExtractionExceptionInterface;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelineArgumentsException;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelinePayloadException;
use Klevu\Pipelines\Exception\TransformationExceptionInterface;
use Klevu\Pipelines\Extractor\Extractor;
use Klevu\Pipelines\Model\Extraction;
use Klevu\Pipelines\Parser\ArgumentConverter;
use Klevu\Pipelines\Parser\SyntaxParser;
use Klevu\Pipelines\Pipeline\PipelineInterface;
use Klevu\Pipelines\Pipeline\StagesNotSupportedTrait;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;

class HandleBatchResponse implements PipelineInterface
{
    use StagesNotSupportedTrait;

    public const ARGUMENT_KEY_ACTION = 'action';
    public const ARGUMENT_KEY_API_KEY = 'apiKey';
    public const ARGUMENT_KEY_ENTITY_TYPE = 'entityType';

    /**
     * @var ArgumentConverter
     */
    private readonly ArgumentConverter $argumentConverter;
    /**
     * @var Extractor
     */
    private readonly Extractor $extractor;
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;
    /**
     * @var EventManagerInterface
     */
    private readonly EventManagerInterface $eventManager;
    /**
     * @var BatchResponderServiceInterface[]
     */
    private array $batchResponderServices = [];
    /**
     * @var string
     */
    private readonly string $identifier;
    /**
     * @var string|Extraction|null
     */
    private string | Extraction | null $apiKeyArgument = null;
    /**
     * @var string|Extraction|null
     */
    private string | Extraction | null $actionArgument = null;
    /**
     * @var string|Extraction|null
     */
    private string | Extraction | null $entityTypeArgument = null;

    /**
     * @param ArgumentConverter $argumentConverter
     * @param Extractor $extractor
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param EventManagerInterface $eventManager
     * @param BatchResponderServiceInterface[] $batchResponderServices
     * @param mixed[] $stages
     * @param mixed[]|null $args
     * @param string $identifier
     */
    public function __construct(
        ArgumentConverter $argumentConverter,
        Extractor $extractor,
        IndexingEntityProviderInterface $indexingEntityProvider,
        EventManagerInterface $eventManager,
        array $batchResponderServices = [],
        array $stages = [],
        ?array $args = null,
        string $identifier = '',
    ) {
        $this->argumentConverter = $argumentConverter;
        $this->extractor = $extractor;
        $this->indexingEntityProvider = $indexingEntityProvider;
        $this->eventManager = $eventManager;
        $this->identifier = $identifier;
        array_walk($batchResponderServices, [$this, 'addBatchResponderService']);
        array_walk($stages, [$this, 'addStage']);
        if ($args) {
            $this->setArgs($args);
        }
    }

    /**
     * @param mixed $payload
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return mixed
     * @throws InvalidPipelinePayloadException
     * @throws ExtractionExceptionInterface
     * @throws TransformationExceptionInterface
     */
    public function execute(mixed $payload, ?\ArrayAccess $context = null): mixed
    {
        /** @var ApiPipelineResult $payload */
        $this->validatePayload($payload);
        [$entityType, $apiKey, $indexingEntities, $action] = $this->extractPayloadData(
            payload: $payload,
            context: $context,
        );
        $this->eventManager->dispatch(
            'klevu_indexing_handle_batch_response_before',
            [
                'apiPipelineResult' => $payload,
                'action' => $action,
                'indexingEntities' => $indexingEntities,
                'entityType' => $entityType,
                'apiKey' => $apiKey,
            ],
        );
        foreach ($this->batchResponderServices as $batchResponderService) {
            $batchResponderService->execute(
                apiPipelineResult: $payload,
                action: $action,
                indexingEntities: $indexingEntities,
                entityType: $entityType,
                apiKey: $apiKey,
            );
        }
        $this->eventManager->dispatch(
            'klevu_indexing_handle_batch_response_after',
            [
                'apiPipelineResult' => $payload,
                'action' => $action,
                'indexingEntities' => $indexingEntities,
                'entityType' => $entityType,
                'apiKey' => $apiKey,
            ],
        );

        return $payload;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param mixed[] $args
     *
     * @return void
     */
    public function setArgs(array $args): void
    {
        if (null === ($args[static::ARGUMENT_KEY_API_KEY] ?? null)) {
            throw new InvalidPipelineArgumentsException(
                pipelineName: $this::class,
                arguments: $args,
                message: sprintf(
                    'Argument (%s) is required',
                    static::ARGUMENT_KEY_API_KEY,
                ),
            );
        }
        if (null === ($args[static::ARGUMENT_KEY_ACTION] ?? null)) {
            throw new InvalidPipelineArgumentsException(
                pipelineName: $this::class,
                arguments: $args,
                message: sprintf(
                    'Argument (%s) is required',
                    static::ARGUMENT_KEY_ACTION,
                ),
            );
        }
        if (null === ($args[static::ARGUMENT_KEY_ENTITY_TYPE] ?? null)) {
            throw new InvalidPipelineArgumentsException(
                pipelineName: $this::class,
                arguments: $args,
                message: sprintf(
                    'Argument (%s) is required',
                    static::ARGUMENT_KEY_ENTITY_TYPE,
                ),
            );
        }
        $this->apiKeyArgument = $this->prepareApiKeyArgument(
            apiKey: $args[static::ARGUMENT_KEY_API_KEY],
            arguments: $args,
        );
        $this->actionArgument = $this->prepareActionArgument(
            action: $args[static::ARGUMENT_KEY_ACTION],
            arguments: $args,
        );
        $this->entityTypeArgument = $this->prepareEntityTypeArgument(
            entityType: $args[static::ARGUMENT_KEY_ENTITY_TYPE],
            arguments: $args,
        );
    }

    /**
     * @param BatchResponderServiceInterface $batchResponderService
     *
     * @return void
     */
    private function addBatchResponderService(BatchResponderServiceInterface $batchResponderService): void
    {
        $this->batchResponderServices[] = $batchResponderService;
    }

    /**
     * @param mixed $apiKey
     * @param mixed[]|null $arguments
     *
     * @return string|Extraction
     */
    private function prepareApiKeyArgument(
        mixed $apiKey,
        ?array $arguments,
    ): string | Extraction {
        if (
            is_string($apiKey)
            && str_starts_with($apiKey, SyntaxParser::EXTRACTION_START_CHARACTER)
        ) {
            $resultArgument = $this->argumentConverter->execute($apiKey);
            $apiKey = $resultArgument->getValue();
        }

        if (!is_string($apiKey) && !($apiKey instanceof Extraction)) {
            throw new InvalidPipelineArgumentsException(
                pipelineName: $this::class,
                arguments: $arguments,
                message: sprintf(
                    'Argument (%s) must be string|%s; Received %s',
                    static::ARGUMENT_KEY_API_KEY,
                    Extraction::class,
                    get_debug_type($apiKey),
                ),
            );
        }

        return $apiKey;
    }

    /**
     * @param mixed $entityType
     * @param mixed[]|null $arguments
     *
     * @return string|Extraction
     */
    private function prepareEntityTypeArgument(
        mixed $entityType,
        ?array $arguments,
    ): string | Extraction {
        if (
            is_string($entityType)
            && str_starts_with($entityType, SyntaxParser::EXTRACTION_START_CHARACTER)
        ) {
            $resultArgument = $this->argumentConverter->execute($entityType);
            $entityType = $resultArgument->getValue();
        }

        if (!is_string($entityType) && !($entityType instanceof Extraction)) {
            throw new InvalidPipelineArgumentsException(
                pipelineName: $this::class,
                arguments: $arguments,
                message: sprintf(
                    'Argument (%s) must be string|%s; Received %s',
                    static::ARGUMENT_KEY_ENTITY_TYPE,
                    Extraction::class,
                    get_debug_type($entityType),
                ),
            );
        }

        return $entityType;
    }

    /**
     * @param mixed $action
     * @param mixed[]|null $arguments
     *
     * @return string|Extraction
     */
    private function prepareActionArgument(
        mixed $action,
        ?array $arguments,
    ): string | Extraction {
        if (
            is_string($action)
            && str_starts_with($action, SyntaxParser::EXTRACTION_START_CHARACTER)
        ) {
            $resultArgument = $this->argumentConverter->execute($action);
            $action = $resultArgument->getValue();
        }
        if (!is_string($action) && !($action instanceof Extraction)) {
            throw new InvalidPipelineArgumentsException(
                pipelineName: $this::class,
                arguments: $arguments,
                message: sprintf(
                    'Argument (%s) must be string|%s; Received %s',
                    static::ARGUMENT_KEY_ACTION,
                    Extraction::class,
                    get_debug_type($action),
                ),
            );
        }
        if (is_string($action)) {
            try {
                $action = Actions::tryFrom($action)->value;
            } catch (\Exception) {
                throw new InvalidPipelineArgumentsException(
                    pipelineName: $this::class,
                    arguments: $arguments,
                    message: sprintf(
                        'Argument (%s) valid action (%s); Received %s',
                        static::ARGUMENT_KEY_ACTION,
                        Actions::class,
                        $action,
                    ),
                );
            }
        }

        return $action;
    }

    /**
     * @param mixed $payload
     *
     * @return void
     * @throws InvalidPipelinePayloadException
     */
    private function validatePayload(mixed $payload): void
    {
        if (($payload instanceof ApiPipelineResult)) {
            return;
        }
        throw new InvalidPipelinePayloadException(
            pipelineName: $this::class,
            message: (string)__(
                'Payload must be instance of %1; Received %2',
                ApiPipelineResult::class,
                is_scalar($payload)
                    ? $payload
                    : get_debug_type($payload),
            ),
        );
    }

    /**
     * @param ApiPipelineResult $payload
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return array<Actions|string|IndexingEntityInterface[]>
     * @throws ExtractionExceptionInterface
     * @throws TransformationExceptionInterface
     */
    private function extractPayloadData(ApiPipelineResult $payload, ?\ArrayAccess $context): array
    {
        $entityType = $this->getEntityType(
            entityTypeArgument: $this->entityTypeArgument,
            payload: $payload,
            context: $context,
        );
        $apiKey = $this->getApiKey(
            apiKeyArgument: $this->apiKeyArgument,
            payload: $payload,
            context: $context,
        );
        $indexingEntities = $this->getIndexingEntities(
            payload: $payload->payload,
            entityType: $entityType,
            apiKey: $apiKey,
        );
        $action = $this->getAction(
            actionArgument: $this->actionArgument,
            payload: $payload,
            context: $context,
        );

        return [$entityType, $apiKey, $indexingEntities, $action];
    }

    /**
     * @param mixed $payload
     * @param string $entityType
     * @param string $apiKey
     *
     * @return IndexingEntityInterface[]
     */
    private function getIndexingEntities(
        mixed $payload,
        string $entityType,
        string $apiKey,
    ): array {
        $targetIds = $payload instanceof RecordIterator
            ? $this->getTargetIdsFromRecordIterator($payload)
            : $this->getTargetIdsFromArray($payload);

        $entityCollection = $this->indexingEntityProvider->getForTargetParentPairs(
            entityType: $entityType,
            apiKey: $apiKey,
            pairs: $targetIds,
        );
        /** @var IndexingEntityInterface[] $items */
        $items = $entityCollection->getItems();

        return $items;
    }

    /**
     * @param mixed $apiKeyArgument
     * @param mixed $payload
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return string
     * @throws ExtractionExceptionInterface
     * @throws TransformationExceptionInterface
     */
    private function getApiKey(
        mixed $apiKeyArgument,
        mixed $payload,
        ?\ArrayAccess $context,
    ): string {
        $result = $apiKeyArgument;
        if ($result instanceof Extraction) {
            try {
                $result = $this->extractor->extract(
                    source: $payload,
                    accessor: $result->accessor,
                    transformations: $result->transformations,
                    context: $context,
                );
            } catch (ExtractionException $exception) {
                throw new InvalidPipelineArgumentsException(
                    pipelineName: $this::class,
                    arguments: [
                        static::ARGUMENT_KEY_API_KEY => $apiKeyArgument,
                    ],
                    message: sprintf(
                        'Result argument (%s) value could not be extracted: %s',
                        static::ARGUMENT_KEY_API_KEY,
                        $exception->getMessage(),
                    ),
                );
            }
        }
        if (!is_string($result)) {
            throw new InvalidPipelineArgumentsException(
                pipelineName: $this::class,
                arguments: [
                    static::ARGUMENT_KEY_API_KEY => $apiKeyArgument,
                ],
                message: sprintf(
                    'Argument (%s) must be string|%s; Received %s',
                    static::ARGUMENT_KEY_API_KEY,
                    Extraction::class,
                    get_debug_type($result),
                ),
            );
        }

        return $result;
    }

    /**
     * @param mixed $entityTypeArgument
     * @param mixed $payload
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return string
     * @throws ExtractionExceptionInterface
     * @throws TransformationExceptionInterface
     */
    private function getEntityType(
        mixed $entityTypeArgument,
        mixed $payload,
        ?\ArrayAccess $context,
    ): string {
        $result = $entityTypeArgument;
        if ($result instanceof Extraction) {
            try {
                $result = $this->extractor->extract(
                    source: $payload,
                    accessor: $result->accessor,
                    transformations: $result->transformations,
                    context: $context,
                );
            } catch (ExtractionException $exception) {
                throw new InvalidPipelineArgumentsException(
                    pipelineName: $this::class,
                    arguments: [
                        static::ARGUMENT_KEY_ENTITY_TYPE => $entityTypeArgument,
                    ],
                    message: sprintf(
                        'Result argument (%s) value could not be extracted: %s',
                        static::ARGUMENT_KEY_ENTITY_TYPE,
                        $exception->getMessage(),
                    ),
                );
            }
        }
        if (!is_string($result)) {
            throw new InvalidPipelineArgumentsException(
                pipelineName: $this::class,
                arguments: [
                    static::ARGUMENT_KEY_ENTITY_TYPE => $entityTypeArgument,
                ],
                message: sprintf(
                    'Argument (%s) must be string|%s; Received %s',
                    static::ARGUMENT_KEY_ENTITY_TYPE,
                    Extraction::class,
                    get_debug_type($result),
                ),
            );
        }

        return $result;
    }

    /**
     * @param mixed $actionArgument
     * @param mixed $payload
     * @param \ArrayAccess<int|string, mixed>|null $context
     *
     * @return Actions
     * @throws ExtractionExceptionInterface
     * @throws TransformationExceptionInterface
     * @thrpws \ValueError
     */
    private function getAction(
        mixed $actionArgument,
        mixed $payload,
        ?\ArrayAccess $context,
    ): Actions {
        $result = $actionArgument;
        if ($result instanceof Extraction) {
            try {
                $result = $this->extractor->extract(
                    source: $payload,
                    accessor: $result->accessor,
                    transformations: $result->transformations,
                    context: $context,
                );
            } catch (ExtractionException $exception) {
                throw new InvalidPipelineArgumentsException(
                    pipelineName: $this::class,
                    arguments: [
                        static::ARGUMENT_KEY_ACTION => $actionArgument,
                    ],
                    message: sprintf(
                        'Result argument (%s) value could not be extracted: %s',
                        static::ARGUMENT_KEY_ACTION,
                        $exception->getMessage(),
                    ),
                );
            }
        }
        if (!is_string($result)) {
            throw new InvalidPipelineArgumentsException(
                pipelineName: $this::class,
                arguments: [
                    static::ARGUMENT_KEY_ACTION => $actionArgument,
                ],
                message: sprintf(
                    'Argument (%s) must be string|%s; Received %s',
                    static::ARGUMENT_KEY_ACTION,
                    Extraction::class,
                    get_debug_type($result),
                ),
            );
        }

        return Actions::from($result);
    }

    /**
     * @param RecordIterator $payload
     *
     * @return int[][]
     */
    private function getTargetIdsFromRecordIterator(RecordIterator $payload): array
    {
        $targetIds = [];
        foreach ($payload as $record) {
            $pair = [];
            $recordId = $this->stripEntityPrefixFromId($record->getId());
            $ids = explode('-', $recordId);
            $pair[IndexingEntity::TARGET_ID] = ($ids[1] ?? null)
                ? (int)$ids[1]
                : (int)$ids[0];
            $pair[IndexingEntity::TARGET_PARENT_ID] = ($ids[1] ?? null)
                ? (int)$ids[0]
                : null;
            $targetIds[$record->getId()] = $pair;
        }
        $payload->rewind();

        return $targetIds;
    }

    /**
     * @param string[] $payload
     *
     * @return int[][]
     */
    private function getTargetIdsFromArray(array $payload): array
    {
        $targetIds = [];
        foreach ($payload as $record) {
            $pair = [];
            $recordId = $this->stripEntityPrefixFromId($record);
            $ids = explode('-', $recordId);
            $pair[IndexingEntity::TARGET_ID] = ($ids[1] ?? null)
                ? (int)$ids[1]
                : (int)$ids[0];
            $pair[IndexingEntity::TARGET_PARENT_ID] = ($ids[1] ?? null)
                ? (int)$ids[0]
                : null;
            $targetIds[$record] = $pair;
        }

        return $targetIds;
    }

    /**
     * @param string $recordId
     *
     * @return string
     */
    private function stripEntityPrefixFromId(string $recordId): string
    {
        $return = $recordId;
        if (str_contains(haystack: $recordId, needle: '_')) {
            $idParts = explode(separator: '_', string: $recordId);
            $return = $idParts[1];
        }

        return $return;
    }
}
