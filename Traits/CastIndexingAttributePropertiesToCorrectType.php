<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Traits;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;

trait CastIndexingAttributePropertiesToCorrectType
{
    /**
     * Set correct types on object fields.
     * Protects against direct use of resourceModel to load data or using getData after loading via repository
     * i.e. $object = $repo->getById(); $object->getData('target_id'); which would return a string, rather than an int.
     *
     * @param IndexingAttributeInterface $object
     *
     * @return void
     */
    private function castPropertiesToCorrectType(IndexingAttributeInterface $object): void
    {
        /** @var IndexingAttributeInterface $object */
        $object->setId((int)$object->getId());
        $object->setTargetAttributeType($object->getTargetAttributeType());
        $object->setTargetId($object->getTargetId());
        $object->setApiKey($object->getApiKey());
        $nextAction = $object->getData(IndexingEntity::NEXT_ACTION);
        if (!($nextAction instanceof Actions)) {
            $nextAction = Actions::from($nextAction);
        }
        $object->setNextAction($nextAction);
        $lockTimestamp = $object->getLockTimestamp();
        $object->setLockTimestamp(
            $lockTimestamp ?: null,
        );
        $lastAction = $object->getData(IndexingEntity::LAST_ACTION);
        if (!($lastAction instanceof Actions)) {
            $lastAction = Actions::from($lastAction);
        }
        $object->setLastAction($lastAction);
        $lastActionTimestamp = $object->getLastActionTimestamp();
        $object->setLastActionTimestamp(
            $lastActionTimestamp ?: null,
        );
        $object->setIsIndexable($object->getIsIndexable());
    }
}
