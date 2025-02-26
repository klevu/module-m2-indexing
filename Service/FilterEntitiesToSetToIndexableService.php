<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterEntitiesToSetToIndexableServiceInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class FilterEntitiesToSetToIndexableService implements FilterEntitiesToSetToIndexableServiceInterface
{
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider; // @phpstan-ignore-line
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var EntityDiscoveryProviderInterface[]
     */
    private array $discoveryProviders = [];

    /**
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param LoggerInterface $logger
     * @param EntityDiscoveryProviderInterface[] $discoveryProviders
     */
    public function __construct(
        IndexingEntityProviderInterface $indexingEntityProvider,
        LoggerInterface $logger,
        array $discoveryProviders = [],
    ) {
        $this->indexingEntityProvider = $indexingEntityProvider;
        $this->logger = $logger;
        array_walk($discoveryProviders, [$this, 'addDiscoveryProvider']);
    }

    /**
     * @param IndexingEntityInterface[] $klevuIndexingEntities
     * @param string $type
     * @param string[] $apiKeys
     * @param string[]|null $entitySubtypes
     *
     * @return \Generator<int[]>
     */
    public function execute(
        array $klevuIndexingEntities,
        string $type,
        array $apiKeys,
        ?array $entitySubtypes = [],
    ): \Generator {
        $return = [];
        $klevuEntityIds = array_filter(
            array_map(
                callback: static fn (IndexingEntityInterface $indexingEntity): int => $indexingEntity->getTargetId(),
                array: $klevuIndexingEntities,
            ),
        );
        if (!$klevuEntityIds) {
            return $return;
        }
        $discoveryProvider = $this->getDiscoveryProvider($type);
        if (null === $discoveryProvider) {
            return $return;
        }
        try {
            /** @var \Generator<string, \Generator<MagentoEntityInterface[]>> $magentoEntitiesByApiKey */
            $magentoEntitiesByApiKey = $discoveryProvider->getData(
                apiKeys: $apiKeys,
                entityIds: $klevuEntityIds,
                entitySubtypes: $entitySubtypes,
            );
            unset($klevuEntityIds);
            foreach ($magentoEntitiesByApiKey as $apiKey => $magentoEntitiesById) {
                foreach ($magentoEntitiesById as $magentoEntities) {
                    yield $this->getKlevuEntitiesToSetToIndexable(
                        magentoEntities: $magentoEntities,
                        type: $type,
                        indexingEntities: $klevuIndexingEntities,
                        apiKey: $apiKey,
                    );
                }
            }
            unset($magentoEntitiesByApiKey);
        } catch (LocalizedException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }

    /**
     * @param EntityDiscoveryProviderInterface $discoveryProvider
     *
     * @return void
     */
    private function addDiscoveryProvider(EntityDiscoveryProviderInterface $discoveryProvider): void
    {
        $this->discoveryProviders[$discoveryProvider->getEntityType()] = $discoveryProvider;
    }

    /**
     * @param MagentoEntityInterface[] $magentoEntities
     * @param string $type
     * @param IndexingEntityInterface[] $indexingEntities
     * @param string $apiKey
     *
     * @return int[]
     */
    private function getKlevuEntitiesToSetToIndexable(
        array $magentoEntities,
        string $type,
        array $indexingEntities,
        string $apiKey,
    ): array {
        $magentoEntityIds = array_map(
            callback: static fn (MagentoEntityInterface $magentoEntity): string => (
                $magentoEntity->getEntityId()
                . '-' . ($magentoEntity->getEntityParentId() ?: 0)
                . '-' . $magentoEntity->getApiKey()
                . '-' . $type
            ),
            array: array_filter(
                array: $magentoEntities,
                callback: static fn (MagentoEntityInterface $magentoEntity): bool => $magentoEntity->isIndexable(),
            ),
        );
        $klevuEntities = $magentoEntityIds
            ? array_filter(
                array: $indexingEntities,
                callback: static function (IndexingEntityInterface $indexingEntity) use (
                    $magentoEntityIds,
                    $apiKey,
                ): bool {
                    if ($apiKey && $apiKey !== $indexingEntity->getApiKey()) {
                        return false;
                    }
                    $klevuId = $indexingEntity->getTargetId()
                        . '-' . ($indexingEntity->getTargetParentId() ?: 0)
                        . '-' . $indexingEntity->getApiKey()
                        . '-' . $indexingEntity->getTargetEntityType();

                    return (!$indexingEntity->getIsIndexable() || $indexingEntity->getNextAction() === Actions::DELETE)
                        && in_array(needle: $klevuId, haystack: $magentoEntityIds, strict: true);
                },
            )
            : [];
        unset($magentoEntityIds);
        $return = array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => (int)$indexingEntity->getId(),
            array: $klevuEntities,
        );
        unset($klevuEntities);

        return $return;
    }

    /**
     * @param string $type
     *
     * @return EntityDiscoveryProviderInterface|null
     */
    private function getDiscoveryProvider(string $type): ?EntityDiscoveryProviderInterface
    {
        return $this->discoveryProviders[$type] ?? null;
    }
}
