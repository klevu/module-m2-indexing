<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Traits;

use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;

trait CastSyncHistoryConsolidationPropertiesToCorrectType
{
    /**
     * Set correct types on object fields.
     * Protects against direct use of resourceModel to load data or using getData after loading via repository
     * i.e. $object = $repo->getById(); $object->getData('target_id'); which would return a string, rather than an int.
     *
     * @param SyncHistoryEntityConsolidationRecordInterface $object
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function castPropertiesToCorrectType(SyncHistoryEntityConsolidationRecordInterface $object): void
    {
        /** @var SyncHistoryEntityConsolidationRecordInterface $object */
        $object->setId((int)$object->getId());
        $object->setTargetEntityType(entityType: $object->getTargetEntityType());
        $object->setTargetId(targetId: $object->getTargetId());
        $object->setTargetParentId(targetParentId: $object->getTargetParentId());
        $object->setApiKey(apiKey: $object->getApiKey());
        $object->setHistory(history: $object->getHistory());
        $object->setDate(date: $object->getDate());
    }
}
