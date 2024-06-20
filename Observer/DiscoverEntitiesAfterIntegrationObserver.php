<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Observer;

use Klevu\IndexingApi\Service\Action\CreateCronScheduleActionInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class DiscoverEntitiesAfterIntegrationObserver implements ObserverInterface
{
    /**
     * @var CreateCronScheduleActionInterface
     */
    private readonly CreateCronScheduleActionInterface $createCronScheduleAction;

    /**
     * @param CreateCronScheduleActionInterface $createCronScheduleAction
     */
    public function __construct(
        CreateCronScheduleActionInterface $createCronScheduleAction,
    ) {
        $this->createCronScheduleAction = $createCronScheduleAction;
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
        $this->createCronScheduleAction->execute();
    }
}
