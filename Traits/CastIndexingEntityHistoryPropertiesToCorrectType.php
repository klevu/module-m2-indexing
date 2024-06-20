<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Traits;

use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;

trait CastIndexingEntityHistoryPropertiesToCorrectType
{
    /**
     * Set correct types on object fields.
     * Protects against direct use of resourceModel to load data or using getData after loading via repository
     * i.e. $object = $repo->getById(); $object->getData('target_id'); which would return a string, rather than an int.
     *
     * @param SyncHistoryEntityRecordInterface $object
     *
     * @return void
     */
    private function castPropertiesToCorrectType(SyncHistoryEntityRecordInterface $object): void
    {
        /** @var SyncHistoryEntityRecordInterface $object */
        $object->setId((int)$object->getId());
        $object->setTargetEntityType(entityType: $object->getTargetEntityType());
        $object->setTargetId(targetId: (int)$object->getTargetId());
        $object->setTargetParentId(targetParentId: (int)$object->getTargetParentId());
        $object->setApiKey(apiKey: $object->getApiKey());
        $action = $object->getData(key: SyncHistoryEntityRecord::ACTION);
        if (!($action instanceof Actions)) {
            if (null === $action) {
                $action = '';
            }
            $action = Actions::from(value: $action);
        }
        $object->setAction(action: $action);
        $object->setActionTimestamp(
            actionTimestamp: $object->getActionTimestamp() ?: null,
        );
        $object->setIsSuccess(
            success: (bool)($object->getIsSuccess()),
        );
        $object->setMessage(
            message: $object->getMessage() ?: null,
        );
    }
}
