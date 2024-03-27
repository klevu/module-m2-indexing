<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Observer;

use Klevu\IndexingApi\Service\AttributeUpdateOrchestratorServiceInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * "klevu/module-m2-indexing-mq" (if installed) will replace this observer
 */
class UpdateAttributesObserver implements ObserverInterface
{
    /**
     * @var AttributeUpdateOrchestratorServiceInterface
     */
    private readonly AttributeUpdateOrchestratorServiceInterface $attributeUpdateOrchestratorService;

    /**
     * @param AttributeUpdateOrchestratorServiceInterface $attributeUpdateOrchestratorService
     */
    public function __construct(AttributeUpdateOrchestratorServiceInterface $attributeUpdateOrchestratorService)
    {
        $this->attributeUpdateOrchestratorService = $attributeUpdateOrchestratorService;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $attributeUpdate = $event->getData('attributeUpdate');

        $this->attributeUpdateOrchestratorService->execute($attributeUpdate);
    }
}
