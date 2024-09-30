<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\IndexingApi\Api\Data\DiscoveryResultInterface;
use Klevu\IndexingApi\Model\Update\AttributeInterface as AttributeUpdateInterface;
use Klevu\IndexingApi\Service\AttributeDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\AttributeUpdateOrchestratorServiceInterface;

class AttributeUpdateOrchestratorService implements AttributeUpdateOrchestratorServiceInterface
{
    /**
     * @var AttributeDiscoveryOrchestratorServiceInterface
     */
    private readonly AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;

    /**
     * @param AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     * @param ApiKeysProviderInterface $apiKeysProvider
     */
    public function __construct(
        AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService,
        ApiKeysProviderInterface $apiKeysProvider,
    ) {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;
        $this->apiKeysProvider = $apiKeysProvider;
    }

    /**
     * @param AttributeUpdateInterface $attributeUpdate
     *e
     *
     * @return DiscoveryResultInterface
     */
    public function execute(AttributeUpdateInterface $attributeUpdate): DiscoveryResultInterface
    {
        $attributeType = $attributeUpdate->getAttributeType();
        $attributeIds = $attributeUpdate->getAttributeIds();
        $apiKeys = $this->apiKeysProvider->get(
            storeIds: $attributeUpdate->getStoreIds(),
        );

        return $this->discoveryOrchestratorService->execute(
            attributeTypes: [$attributeType],
            apiKeys: $apiKeys,
            attributeIds: $attributeIds,
        );
    }
}
