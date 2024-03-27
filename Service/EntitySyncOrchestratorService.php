<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Exception\InvalidEntityIndexerServiceException;
use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Api\Data\IndexerResultInterfaceFactory;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;
use Klevu\IndexingApi\Service\EntityIndexerServiceInterface;
use Klevu\IndexingApi\Service\EntitySyncOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\Provider\AccountCredentialsProviderInterface;
use Klevu\PhpSDK\Model\AccountCredentials;
use Psr\Log\LoggerInterface;

class EntitySyncOrchestratorService implements EntitySyncOrchestratorServiceInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var AccountCredentialsProviderInterface
     */
    private readonly AccountCredentialsProviderInterface $accountCredentialsProvider;
    /**
     * @var IndexerResultInterfaceFactory
     */
    private readonly IndexerResultInterfaceFactory $indexerResultFactory;
    /**
     * @var EntityIndexerServiceInterface[]
     */
    private array $entityIndexerServices = [];

    /**
     * @param LoggerInterface $logger
     * @param AccountCredentialsProviderInterface $accountCredentialsProvider
     * @param IndexerResultInterfaceFactory $indexerResultFactory
     * @param EntityIndexerServiceInterface[][] $entityIndexerServices
     *
     * @throws InvalidEntityIndexerServiceException
     */
    public function __construct(
        LoggerInterface $logger,
        AccountCredentialsProviderInterface $accountCredentialsProvider,
        IndexerResultInterfaceFactory $indexerResultFactory,
        array $entityIndexerServices,
    ) {
        $this->logger = $logger;
        $this->accountCredentialsProvider = $accountCredentialsProvider;
        $this->indexerResultFactory = $indexerResultFactory;
        array_walk($entityIndexerServices, [$this, 'setIndexerServices']);
    }

    /**
     * @param string|null $entityType
     * @param string|null $apiKey
     * @param string|null $via
     *
     * @return IndexerResultInterface[]
     */
    public function execute(
        ?string $entityType = null,
        ?string $apiKey = null,
        ?string $via = null,
    ): array {
        $return = [];
        $indexerServicesByType = $this->getIndexerServices($entityType);
        $accountCredentialsArray = $this->getCredentialsArray($apiKey);
        foreach ($accountCredentialsArray as $accountCredentials) {
            $apiKeyResponses = [];
            foreach ($indexerServicesByType as $key => $indexerService) {
                $apiKeyResponses[$key] = $indexerService->execute(
                    apiKey: $accountCredentials->jsApiKey,
                    via: $via,
                );
            }
            $return[$accountCredentials->jsApiKey] = $this->createIndexerResult($apiKeyResponses);
        }
        $this->logger->debug(
            message: 'IndexerService::execute completed',
            context: [
                'return' => $return,
            ],
        );

        return $return;
    }

    /**
     * Expected format for entityIndexerServices, injected vi di.xml
     * <argument name="entityIndexerServices" xsi:type="array">
     *   <item name="KLEVU_PRODUCT" xsi:type="array">
     *     <item name="add" xsi:type="object">Klevu\IndexingProducts\Service\EntityIndexerService\Add</item>
     *   </item>
     * </argument>
     *
     * @param EntityIndexerServiceInterface[] $indexerServices
     * @param string $entityType
     *
     * @return void
     * @throws InvalidEntityIndexerServiceException
     */
    private function setIndexerServices(array $indexerServices, string $entityType): void
    {
        foreach ($indexerServices as $action => $indexerService) {
            if (!($indexerService instanceof EntityIndexerServiceInterface)) {
                throw new InvalidEntityIndexerServiceException(
                    __(
                        'Invalid Indexer Service: Expected instance of %s, received %s',
                        EntityIndexerServiceInterface::class,
                        get_debug_type($indexerService),
                    ),
                );
            }
            $this->entityIndexerServices[$entityType . '::' . $action] = $indexerService;
        }
    }

    /**
     * @param string|null $entityType
     *
     * @return EntityIndexerServiceInterface[]
     */
    private function getIndexerServices(?string $entityType): array
    {
        return $entityType
            ? array_filter(
                array: $this->entityIndexerServices,
                callback: static fn (string $key): bool => str_starts_with(haystack: $key, needle: $entityType . '::'),
                mode: ARRAY_FILTER_USE_KEY,
            )
            : $this->entityIndexerServices;
    }

    /**
     * @param string|null $apiKey
     *
     * @return AccountCredentials[]
     */
    private function getAccountCredentials(?string $apiKey): array
    {
        $accountCredentials = $this->accountCredentialsProvider->get();
        if ($apiKey) {
            $credentials = $accountCredentials[$apiKey] ?? null;
            $accountCredentials = $credentials
                ? [$credentials]
                : [];
        }

        return $accountCredentials;
    }

    /**
     * @param string|null $apiKey
     *
     * @return AccountCredentials[]
     */
    private function getCredentialsArray(?string $apiKey): array
    {
        $accountCredentialsArray = $this->getAccountCredentials(apiKey: $apiKey);
        if ($apiKey && !$accountCredentialsArray) {
            $this->logger->warning(
                message: 'Method: {method}, Warning: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => __(
                        'No Account found for provided API Key. Check the JS API Key (%1) provided.',
                        $apiKey,
                    )->render(),
                ],
            );
        }

        return $accountCredentialsArray;
    }

    /**
     * @param IndexerResultInterface[] $indexerResults
     *
     * @return IndexerResultInterface
     */
    private function createIndexerResult(array $indexerResults): IndexerResultInterface
    {
        $return = $this->indexerResultFactory->create();
        $return->setStatus(
            status: $this->getResultStatuses($indexerResults),
        );
        $return->setPipelineResult(
            pipelineResult: $this->getPipelineResult($indexerResults),
        );
        $return->setMessages(
            messages: $this->getMessages($indexerResults),
        );

        return $return;
    }

    /**
     * @param IndexerResultInterface[] $indexerResults
     *
     * @return IndexerResultStatuses
     */
    private function getResultStatuses(array $indexerResults): IndexerResultStatuses
    {
        $uniqueStatuses = array_unique(
            array_map(
                static fn (IndexerResultInterface $indexerResult): string => $indexerResult->getStatus()->value,
                $indexerResults,
            ),
        );
        $uniqueStatuses = array_filter(
            array: $uniqueStatuses,
            callback: static fn (string $status): bool => IndexerResultStatuses::NOOP->value !== $status,
        );

        return match (true) {
            !$uniqueStatuses => IndexerResultStatuses::NOOP,
            in_array(IndexerResultStatuses::ERROR, $uniqueStatuses, false) => IndexerResultStatuses::ERROR,
            count($uniqueStatuses) === 1 => IndexerResultStatuses::from(current($uniqueStatuses)),
            default => IndexerResultStatuses::PARTIAL,
        };
    }

    /**
     * @param IndexerResultInterface[] $indexerResults
     *
     * @return mixed[]
     */
    private function getPipelineResult(array $indexerResults): array
    {
        return array_map(
            static fn (IndexerResultInterface $indexerResult): mixed => $indexerResult->getPipelineResult(),
            $indexerResults,
        );
    }

    /**
     * @param IndexerResultInterface[] $indexerResults
     *
     * @return string[]
     */
    private function getMessages(array $indexerResults): array
    {
        return array_merge(
            [],
            ...array_values(
                array_map(
                    static fn (IndexerResultInterface $indexerResult): array => $indexerResult->getMessages(),
                    $indexerResults,
                ),
            ),
        );
    }
}
