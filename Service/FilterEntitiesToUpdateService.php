<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Validator\BatchSizeValidator;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToUpdateServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\IndexingApi\Validator\ValidatorInterface;
use Magento\Framework\App\ObjectManager;

class FilterEntitiesToUpdateService implements FilterEntitiesToUpdateServiceInterface
{
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;
    /**
     * @var int
     */
    private readonly int $batchSize;

    /**
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param int $batchSize
     * @param ValidatorInterface|null $batchSizeValidator
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        IndexingEntityProviderInterface $indexingEntityProvider,
        int $batchSize = 2500,
        ?ValidatorInterface $batchSizeValidator = null,
    ) {
        $this->indexingEntityProvider = $indexingEntityProvider;

        $objectManager = ObjectManager::getInstance();
        $batchSizeValidator = $batchSizeValidator ?: $objectManager->get(BatchSizeValidator::class);
        if (!$batchSizeValidator->isValid($batchSize)) {
            throw new \InvalidArgumentException(
                message: sprintf(
                    'Invalid Batch Size: %s',
                    implode(', ', $batchSizeValidator->getMessages()),
                ),
            );
        }
        $this->batchSize = $batchSize;
    }

    /**
     *
     * @param string $type
     * @param int[] $entityIds
     * @param string[] $apiKeys
     * @param string[]|null $entitySubtypes
     *
     * @return \Generator<int[]>
     */
    public function execute(
        string $type,
        array $entityIds,
        array $apiKeys,
        ?array $entitySubtypes = [],
    ): \Generator {
        $lastIndexingEntityId = 0;
        while (true) {
            $klevuEntities = $this->indexingEntityProvider->get(
                entityType: $type,
                apiKeys: $apiKeys,
                entityIds: $entityIds,
                pageSize: $this->batchSize,
                startFrom: $lastIndexingEntityId + 1,
                entitySubtypes: $entitySubtypes,
            );
            if (!$klevuEntities) {
                break;
            }
            yield array_map(
                callback: static fn (IndexingEntityInterface $indexingEntity): int => $indexingEntity->getId(),
                array: array_filter(
                    array: $klevuEntities,
                    callback: static fn (IndexingEntityInterface $indexingEntity): bool => (
                        $indexingEntity->getIsIndexable()
                    ),
                ),
            );
            $lastIndexingEntity = array_pop($klevuEntities);
            $lastIndexingEntityId = $lastIndexingEntity->getId();
            foreach ($klevuEntities as $klevuEntity) {
                if (method_exists($klevuEntity, 'clearInstance')) {
                    $klevuEntity->clearInstance();
                }
            }
            unset($klevuEntities);
            if (!$lastIndexingEntityId) {
                break;
            }
        }
    }
}
