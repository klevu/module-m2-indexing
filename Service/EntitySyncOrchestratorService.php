<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Exception\InvalidEntityIndexerServiceException;
use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Service\EntityIndexerServiceInterface;
use Klevu\IndexingApi\Service\EntitySyncOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\Provider\AccountCredentialsProviderInterface;
use Klevu\PhpSDK\Model\AccountCredentials;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Psr\Log\LoggerInterface;

class EntitySyncOrchestratorService implements EntitySyncOrchestratorServiceInterface
{
    public const INDEXER_RESULT_KEY_CONCATENATOR = '~~';

    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var AccountCredentialsProviderInterface
     */
    private readonly AccountCredentialsProviderInterface $accountCredentialsProvider;
    /**
     * @var EventManagerInterface
     */
    private readonly EventManagerInterface $eventManager;
    /**
     * @var EntityIndexerServiceInterface[]
     */
    private array $entityIndexerServices = [];

    /**
     * @param LoggerInterface $logger
     * @param AccountCredentialsProviderInterface $accountCredentialsProvider
     * @param EventManagerInterface $eventManager
     * @param EntityIndexerServiceInterface[][] $entityIndexerServices
     *
     * @throws InvalidEntityIndexerServiceException
     */
    public function __construct(
        LoggerInterface $logger,
        AccountCredentialsProviderInterface $accountCredentialsProvider,
        EventManagerInterface $eventManager,
        array $entityIndexerServices,
    ) {
        $this->logger = $logger;
        $this->accountCredentialsProvider = $accountCredentialsProvider;
        $this->eventManager = $eventManager;
        array_walk($entityIndexerServices, [$this, 'setIndexerServices']);
    }

    /**
     * @param string[] $entityTypes
     * @param string[] $apiKeys
     * @param string|null $via
     *
     * @return \Generator<IndexerResultInterface>
     */
    public function execute(
        array $entityTypes = [],
        array $apiKeys = [],
        ?string $via = null,
    ): \Generator {
        $indexerServicesByType = $this->getIndexerServices($entityTypes);
        foreach ($this->getCredentialsArray(apiKeys: $apiKeys) as $accountCredentials) {
            foreach ($indexerServicesByType as $action => $indexerService) {
                $responses = $indexerService->execute(
                    apiKey: $accountCredentials->jsApiKey,
                    via: $via,
                );
                foreach ($responses as $indexerResults) {
                    $key = $accountCredentials->jsApiKey . static::INDEXER_RESULT_KEY_CONCATENATOR . $action;
                    yield $key => $indexerResults;
                }
            }
        }
        $this->logger->debug(
            message: 'IndexerService::execute completed',
        );
        $this->eventManager->dispatch(
            'klevu_indexing_entity_orchestrator_sync_after',
            [
                'entityType' => $entityTypes,
                'apiKeys' => $apiKeys,
            ],
        );
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
     * @param string[] $entityTypes
     *
     * @return EntityIndexerServiceInterface[]
     */
    private function getIndexerServices(array $entityTypes): array
    {
        return $entityTypes
            ? array_filter(
                array: $this->entityIndexerServices,
                callback: static function (string $key) use ($entityTypes): bool {
                    foreach ($entityTypes as $entityType) {
                        if (str_starts_with(haystack: $key, needle: $entityType . '::')) {
                            return true;
                        }
                    }

                    return false;
                },
                mode: ARRAY_FILTER_USE_KEY,
            )
            : $this->entityIndexerServices;
    }

    /**
     * @param string[] $apiKeys
     *
     * @return AccountCredentials[]
     */
    private function getCredentialsArray(array $apiKeys): array
    {
        $accountCredentialsArray = $this->getAccountCredentials(apiKeys: $apiKeys);
        if ($apiKeys && !$accountCredentialsArray) {
            $this->logger->warning(
                message: 'Method: {method}, Warning: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => __(
                        'No Account found for provided API Keys. Check the JS API Keys (%1) provided.',
                        implode(', ', $apiKeys),
                    )->render(),
                ],
            );
        }

        return $accountCredentialsArray;
    }

    /**
     * @param string[] $apiKeys
     *
     * @return AccountCredentials[]
     */
    private function getAccountCredentials(array $apiKeys): array
    {
        $accountCredentials = $this->accountCredentialsProvider->get();
        if ($apiKeys) {
            $accountCredentials = array_filter(
                array: $accountCredentials,
                callback: static fn (string $apiKey): bool => (
                    in_array(needle: $apiKey, haystack: $apiKeys, strict: true)
                ),
                mode: ARRAY_FILTER_USE_KEY,
            );
        }

        return $accountCredentials;
    }
}
