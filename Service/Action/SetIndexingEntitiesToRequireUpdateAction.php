<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity as IndexingEntityResource;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToRequireUpdateActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Sql\ExpressionFactory;
use Magento\Framework\Serialize\SerializerInterface;

class SetIndexingEntitiesToRequireUpdateAction implements SetIndexingEntitiesToRequireUpdateActionInterface
{
    /**
     * @var ResourceConnection
     */
    private readonly ResourceConnection $resourceConnection;
    /**
     * @var ExpressionFactory
     */
    private readonly ExpressionFactory $expressionFactory;
    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;

    /**
     * @param ResourceConnection $resourceConnection
     * @param ExpressionFactory $expressionFactory
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        ExpressionFactory $expressionFactory,
        SerializerInterface $serializer,
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->expressionFactory = $expressionFactory;
        $this->serializer = $serializer;
    }

    /**
     * @param string $entityType
     * @param string|null $apiKey
     * @param array<int|string, int|int[]> $targetIds Either targetId to affect all records with that id
     *                                        or an array of ['target_id' => <int>, 'target_parent_id' => <int>|<null>]
     * @param mixed[] $origValues
     *
     * @return void
     */
    public function execute(
        string $entityType,
        ?string $apiKey,
        array $targetIds,
        array $origValues,
    ): void {
        // We're not using repositories or search criteria because the use case for this
        //  action tends to be where speed is critical (eg, checkout)
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(
            modelEntity: IndexingEntityResource::TABLE,
        );

        $whereConditions = [
            sprintf('%s = ?', IndexingEntity::TARGET_ENTITY_TYPE) => $entityType,
        ];
        if (null !== $apiKey) {
            $whereConditions[sprintf('%s = ?', IndexingEntity::API_KEY)] = $apiKey;
        }

        $targetIdConditions = $this->formatTargetIdConditions(
            connection: $connection,
            targetIdItems: array_filter($targetIds),
        );
        $whereConditions[] = $this->expressionFactory->create([
            'expression' => '(' . implode(' OR ', $targetIdConditions) . ')',
        ]);

        $connection->update(
            table: $tableName,
            bind: [
                IndexingEntity::REQUIRES_UPDATE => 1,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => $this->serializer->serialize($origValues),
            ],
            where: $whereConditions,
        );
    }

    /**
     * @param AdapterInterface $connection
     * @param array<int|string, int|int[]> $targetIdItems
     *
     * @return string[]
     */
    private function formatTargetIdConditions(
        AdapterInterface $connection,
        array $targetIdItems,
    ): array {
        $targetIdConditions = [];

        foreach ($targetIdItems as $targetIdItem) {
            if (!is_array($targetIdItem)) {
                $targetIdConditions[] = $connection->quoteInto(
                    text: sprintf(
                        '(%s = ?)',
                        IndexingEntity::TARGET_ID,
                    ),
                    value: (int)$targetIdItem,
                );
                continue;
            }

            $entityId = $targetIdItem[static::ENTITY_IDS_KEY_TARGET_ID] ?? null;
            if (!$entityId) {
                continue;
            }

            $entityParentId = $targetIdItem[static::ENTITY_IDS_KEY_TARGET_PARENT_ID] ?? null;
            if (null === $entityParentId) {
                $targetIdConditions[] = $connection->quoteInto(
                    text: sprintf(
                        '(%s = ? AND %s IS NULL)',
                        IndexingEntity::TARGET_ID,
                        IndexingEntity::TARGET_PARENT_ID,
                    ),
                    value: (int)$entityId,
                );
                continue;
            }

            $targetIdConditions[] = sprintf(
                '(%s AND %s)',
                $connection->quoteInto(
                    text: sprintf('%s = ?', IndexingEntity::TARGET_ID),
                    value: (int)$entityId,
                ),
                $connection->quoteInto(
                    text: sprintf('%s = ?', IndexingEntity::TARGET_PARENT_ID),
                    value: (int)$entityParentId,
                ),
            );
        }

        return $targetIdConditions;
    }
}
