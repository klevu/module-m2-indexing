<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity as IndexingEntityResource;
use Klevu\IndexingApi\Service\Provider\IndexingEntityTargetIdsProviderInterface;
use Magento\Framework\App\ResourceConnection;

class IndexingEntityTargetIdsProvider implements IndexingEntityTargetIdsProviderInterface
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
     * @param int[] $entityIds
     *
     * @return array<int, int>
     */
    public function getByEntityIds(array $entityIds): array {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(
            modelEntity: IndexingEntityResource::TABLE,
        );

        $select = $connection->select();
        $select->from(
            name: ['e' => $tableName],
            cols: [
                IndexingEntity::ENTITY_ID,
                IndexingEntity::TARGET_ID,
            ],
        );
        $select->where(
            cond: sprintf('e.%s IN (?)', IndexingEntity::ENTITY_ID),
            value: $entityIds,
        );
        $select->distinct();

        return array_map(
            callback: 'intval',
            array: $connection->fetchPairs($select),
        );
    }
}
