<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Observer;

use Klevu\IndexingApi\Service\AttributeDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\AttributeSyncOrchestratorServiceInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class DiscoverAttributesAfterIntegrationObserver implements ObserverInterface
{
    /**
     * @var AttributeDiscoveryOrchestratorServiceInterface
     */
    private readonly AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;
    /**
     * @var AttributeSyncOrchestratorServiceInterface
     */
    private readonly AttributeSyncOrchestratorServiceInterface $syncOrchestratorService;

    /**
     * @param AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     * @param AttributeSyncOrchestratorServiceInterface $syncOrchestratorService
     */
    public function __construct(
        AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService,
        AttributeSyncOrchestratorServiceInterface $syncOrchestratorService,
    ) {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;
        $this->syncOrchestratorService = $syncOrchestratorService;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $apiKey = $event->getData(key: 'apiKey');
        if (!$apiKey) {
            return;
        }

        $this->discoveryOrchestratorService->execute(apiKeys: [$apiKey]);
        $this->syncOrchestratorService->execute(apiKey: $apiKey);
    }
}
