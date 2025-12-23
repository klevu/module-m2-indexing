<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity as IndexingEntityResource;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToNotRequireUpdateActionInterface;
use Magento\Framework\App\ResourceConnection;

class SetIndexingEntitiesToNotRequireUpdateAction implements SetIndexingEntitiesToNotRequireUpdateActionInterface
{
    /**
     * @var ResourceConnection
     */
    private readonly ResourceConnection $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ResourceConnection $resourceConnection,
    ) {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @param string $entityType
     * @param string|null $apiKey
     * @param int[] $entityIds
     *
     * @return void
     */
    public function execute(
        string $entityType,
        ?string $apiKey,
        array $entityIds,
    ): void {
        // We're not using repositories or search criteria because the use case for this
        //  action tends to be where speed is critical (eg, checkout)
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(
            modelEntity: IndexingEntityResource::TABLE,
        );

        $whereConditions = [
            sprintf('%s = ?', IndexingEntity::TARGET_ENTITY_TYPE) => $entityType,
            sprintf('%s IN (?)', IndexingEntity::TARGET_ID) => $entityIds,
        ];
        if (null !== $apiKey) {
            $whereConditions[sprintf('%s = ?', IndexingEntity::API_KEY)] = $apiKey;
        }

        $connection->update(
            table: $tableName,
            bind: [
                IndexingEntity::REQUIRES_UPDATE => 0,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => '',
            ],
            where: $whereConditions,
        );
    }
}
