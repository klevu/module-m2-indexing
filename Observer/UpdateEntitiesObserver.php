<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Observer;

use Klevu\IndexingApi\Service\EntityUpdateOrchestratorServiceInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * "klevu/module-m2-indexing-mq" (if installed) will replace this observer
 */
class UpdateEntitiesObserver implements ObserverInterface
{
    /**
     * @var EntityUpdateOrchestratorServiceInterface
     */
    private readonly EntityUpdateOrchestratorServiceInterface $entityUpdateOrchestratorService;

    /**
     * @param EntityUpdateOrchestratorServiceInterface $entityUpdateOrchestratorService
     */
    public function __construct(EntityUpdateOrchestratorServiceInterface $entityUpdateOrchestratorService)
    {
        $this->entityUpdateOrchestratorService = $entityUpdateOrchestratorService;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $entityUpdate = $event->getData('entityUpdate');

        $this->entityUpdateOrchestratorService->execute($entityUpdate);
    }
}
