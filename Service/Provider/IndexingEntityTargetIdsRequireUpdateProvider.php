<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity as IndexingEntityResource;
use Klevu\IndexingApi\Service\Provider\IndexingEntityTargetIdsRequireUpdateProviderInterface;
use Magento\Framework\App\ResourceConnection;

class IndexingEntityTargetIdsRequireUpdateProvider implements IndexingEntityTargetIdsRequireUpdateProviderInterface
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
     * @param string|null $entityType
     * @param string[]|null $apiKeys
     *
     * @return int[]
     */
    public function get(
        ?string $entityType,
        ?array $apiKeys,
    ): array {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(
            modelEntity: IndexingEntityResource::TABLE,
        );

        $select = $connection->select();
        $select->from(
            name: ['e' => $tableName],
            cols: IndexingEntity::TARGET_ID,
        );
        if (null !== $entityType) {
            $select->where(
                cond: sprintf('e.%s = ?', IndexingEntity::TARGET_ENTITY_TYPE),
                value: $entityType,
            );
        }
        if (null !== $apiKeys) {
            $select->where(
                cond: sprintf('e.%s IN (?)', IndexingEntity::API_KEY),
                value: $apiKeys,
            );
        }
        $select->where(
            cond: sprintf('e.%s = ?', IndexingEntity::REQUIRES_UPDATE),
            value: 1,
        );
        $select->distinct();

        return array_map(
            callback: 'intval',
            array: $connection->fetchCol($select),
        );
    }
}
