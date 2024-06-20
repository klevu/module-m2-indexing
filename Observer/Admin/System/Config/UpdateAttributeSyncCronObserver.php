<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Observer\Admin\System\Config;

use Klevu\Indexing\Constants;
use Klevu\IndexingApi\Service\Action\ConsolidateCronConfigSettingsActionInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class UpdateAttributeSyncCronObserver implements ObserverInterface
{
    /**
     * @var ConsolidateCronConfigSettingsActionInterface
     */
    private readonly ConsolidateCronConfigSettingsActionInterface $consolidateCronConfigSettingsAction;

    /**
     * @param ConsolidateCronConfigSettingsActionInterface $consolidateCronConfigSettingsAction
     */
    public function __construct(ConsolidateCronConfigSettingsActionInterface $consolidateCronConfigSettingsAction)
    {
        $this->consolidateCronConfigSettingsAction = $consolidateCronConfigSettingsAction;
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $changedPaths = (array)$observer->getData('changed_paths');
        if (
            !in_array(Constants::XML_PATH_ATTRIBUTE_CRON_FREQUENCY, $changedPaths, true)
            && !in_array(Constants::XML_PATH_ATTRIBUTE_CRON_EXPR, $changedPaths, true)
        ) {
            return;
        }
        $this->consolidateCronConfigSettingsAction->execute();
    }
}
