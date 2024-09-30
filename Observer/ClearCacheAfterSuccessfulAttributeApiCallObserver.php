<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Observer;

use Klevu\IndexingApi\Service\Action\Cache\ClearAttributesCacheActionInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ClearCacheAfterSuccessfulAttributeApiCallObserver implements ObserverInterface
{
    /**
     * @var ClearAttributesCacheActionInterface
     */
    private readonly ClearAttributesCacheActionInterface $clearAttributesCacheAction;

    /**
     * @param ClearAttributesCacheActionInterface $clearAttributesCacheAction
     */
    public function __construct(ClearAttributesCacheActionInterface $clearAttributesCacheAction)
    {
        $this->clearAttributesCacheAction = $clearAttributesCacheAction;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $apiKey = $event->getData(key: 'api_key');
        $this->clearAttributesCacheAction->execute(
            apiKeys: is_scalar($apiKey)
                ? [(string)$apiKey]
                : [],
        );
    }
}
