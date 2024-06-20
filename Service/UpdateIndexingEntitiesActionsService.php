<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\UpdateIndexingEntitiesActionsActionInterface;
use Klevu\IndexingApi\Service\BatchResponderServiceInterface;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;

class UpdateIndexingEntitiesActionsService implements BatchResponderServiceInterface
{
    /**
     * @var UpdateIndexingEntitiesActionsActionInterface
     */
    private readonly UpdateIndexingEntitiesActionsActionInterface $updateIndexingEntitiesActionsAction;

    /**
     * @param UpdateIndexingEntitiesActionsActionInterface $updateIndexingEntitiesActionsAction
     */
    public function __construct(UpdateIndexingEntitiesActionsActionInterface $updateIndexingEntitiesActionsAction)
    {
        $this->updateIndexingEntitiesActionsAction = $updateIndexingEntitiesActionsAction;
    }

    /**
     * @param ApiPipelineResult $apiPipelineResult
     * @param Actions $action
     * @param array<int, IndexingEntityInterface> $indexingEntities
     * @param string $entityType
     * @param string $apiKey
     *
     * @return void
     */
    public function execute(
        ApiPipelineResult $apiPipelineResult,
        Actions $action,
        array $indexingEntities,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        string $entityType,
        // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        string $apiKey,
    ): void {
        if (!$apiPipelineResult->success) {
            return;
        }
        $entityIds = array_map(
            callback: static fn (IndexingEntityInterface $indexingEntity): int => (int)$indexingEntity->getId(),
            array: $indexingEntities,
        );
        $this->updateIndexingEntitiesActionsAction->execute(
            entityIds: $entityIds,
            lastAction: $action,
        );
    }
}
