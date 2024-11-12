<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToDeleteServiceInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class FilterEntitiesToDeleteService implements FilterEntitiesToDeleteServiceInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var EntityDiscoveryProviderInterface[]
     */
    private array $discoveryProviders = [];

    /**
     * @param LoggerInterface $logger
     * @param EntityDiscoveryProviderInterface[] $discoveryProviders
     */
    public function __construct(
        LoggerInterface $logger,
        array $discoveryProviders = [],
    ) {
        array_walk($discoveryProviders, [$this, 'addDiscoveryProvider']);
        $this->logger = $logger;
    }

    /**
     * @param IndexingEntityInterface[] $klevuIndexingEntities
     * @param string $type
     * @param string[] $apiKeys
     * @param string[] $entitySubtypes
     *
     * @return int[]
     */
    public function execute(
        array $klevuIndexingEntities,
        string $type,
        array $apiKeys = [],
        array $entitySubtypes = [],
    ): array {
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
            $entitiesFound = false;
            foreach ($magentoEntitiesByApiKey as $apiKey => $magentoEntitiesById) {
                foreach ($magentoEntitiesById as $magentoEntities) {
                    $return[] = $this->getKlevuEntitiesNoLongerExist(
                        type: $type,
                        magentoEntities: $magentoEntities,
                        indexingEntities: $klevuIndexingEntities,
                        apiKey: $apiKey,
                    );
                    $entitiesFound = true;
                }
            }
            if (!$entitiesFound) {
                // requested entities have been deleted.
                // generator above never enters final foreach loop
                $return[] = $this->getKlevuEntitiesNoLongerExist(
                    type: $type,
                    magentoEntities: [],
                    indexingEntities: $klevuIndexingEntities,
                );
            }
        } catch (LocalizedException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return array_filter(array_values(array_merge(...$return)));
    }

    /**
     * @param EntityDiscoveryProviderInterface $discoveryProvider
     * @param string $entityType
     *
     * @return void
     */
    private function addDiscoveryProvider(EntityDiscoveryProviderInterface $discoveryProvider, string $entityType): void
    {
        $this->discoveryProviders[$entityType] = $discoveryProvider;
    }

    /**
     * @param string $type
     * @param MagentoEntityInterface[] $magentoEntities
     * @param IndexingEntityInterface[] $indexingEntities
     * @param string|null $apiKey
     *
     * @return int[]
     */
    private function getKlevuEntitiesNoLongerExist(
        string $type,
        array $magentoEntities,
        array $indexingEntities,
        ?string $apiKey = null,
    ): array {
        $magentoEntityIds = array_map(
            callback: static fn (MagentoEntityInterface $magentoEntity): string => (
                $magentoEntity->getEntityId()
                . '-' . ($magentoEntity->getEntityParentId() ?: 0)
                . '-' . $magentoEntity->getApiKey()
                . '-' . $type
            ),
            array: $magentoEntities,
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

                    return $indexingEntity->getIsIndexable()
                        && !in_array(needle: $klevuId, haystack: $magentoEntityIds, strict: true);
                },
            )
            : $indexingEntities;

        return array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => (
                (int)$indexingEntity->getId()
            ),
            array: $klevuEntities,
        );
    }

    /**
     * @param string $type
     *
     * @return EntityDiscoveryProviderInterface|null
     */
    private function getDiscoveryProvider(string $type): ?EntityDiscoveryProviderInterface
    {
        $discoveryProviders = array_filter(
            array: $this->discoveryProviders,
            callback: static fn (EntityDiscoveryProviderInterface $entityDiscoveryProvider): bool => (
                $entityDiscoveryProvider->getEntityType() === $type
            ),
        );

        return array_shift($discoveryProviders);
    }
}
