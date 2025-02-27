<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\IndexingApi\Model\Update\EntityInterface as EntityUpdateInterface;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\EntityUpdateOrchestratorServiceInterface;
use Magento\Framework\Event\ManagerInterface;

class EntityUpdateOrchestratorService implements EntityUpdateOrchestratorServiceInterface
{
    /**
     * @var EntityDiscoveryOrchestratorServiceInterface
     */
    private readonly EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;
    /**
     * @var ManagerInterface
     */
    private readonly ManagerInterface $eventManager;

    /**
     * @param EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     * @param ApiKeysProviderInterface $apiKeysProvider
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService,
        ApiKeysProviderInterface $apiKeysProvider,
        ManagerInterface $eventManager,
    ) {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;
        $this->apiKeysProvider = $apiKeysProvider;
        $this->eventManager = $eventManager;
    }

    /**
     * @param EntityUpdateInterface $entityUpdate
     *
     * @return void
     */
    public function execute(EntityUpdateInterface $entityUpdate): void
    {
        $entityType = $entityUpdate->getEntityType();
        $entityIds = $entityUpdate->getEntityIds();
        $apiKeys = $this->apiKeysProvider->get(
            storeIds: $entityUpdate->getStoreIds(),
        );
        $entitySubtypes = $entityUpdate->getEntitySubtypes();
        $responsesGenerator = $this->discoveryOrchestratorService->execute(
            entityTypes: [$entityType],
            apiKeys: $apiKeys,
            entityIds: $entityIds,
            entitySubtypes: $entitySubtypes,
        );
        foreach ($responsesGenerator as $responses) {
            foreach ($responses as $response) {
                $this->eventManager->dispatch(
                    'klevu_indexing_entity_update_after',
                    [
                        'entityUpdate' => $entityUpdate,
                        'success' => $response->isSuccess(),
                        'response' => $response,
                    ],
                );
            }
        }
    }
}
