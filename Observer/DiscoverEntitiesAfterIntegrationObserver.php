<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Observer;

use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class DiscoverEntitiesAfterIntegrationObserver implements ObserverInterface
{
    /**
     * @var EntityDiscoveryOrchestratorServiceInterface
     */
    private readonly EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;

    /**
     * @param EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     */
    public function __construct(EntityDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService)
    {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;
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
    }
}
