<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Traits;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;

trait CastIndexingEntityPropertiesToCorrectType
{
    /**
     * Set correct types on object fields.
     * Protects against direct use of resourceModel to load data or using getData after loading via repository
     * i.e. $object = $repo->getById(); $object->getData('target_id'); which would return a string, rather than an int.
     *
     * @param IndexingEntityInterface $object
     *
     * @return void
     */
    private function castPropertiesToCorrectType(IndexingEntityInterface $object): void
    {
        /** @var IndexingEntityInterface $object */
        $object->setId((int)$object->getId());
        $object->setTargetEntityType($object->getTargetEntityType());
        $object->setTargetId($object->getTargetId());
        $object->setTargetParentId($object->getTargetParentId());
        $object->setApiKey($object->getApiKey());
        $nextAction = $object->getData(IndexingEntity::NEXT_ACTION);
        if (!($nextAction instanceof Actions)) {
            if (null === $nextAction) {
                $nextAction = '';
            }
            $nextAction = Actions::from($nextAction);
        }
        $object->setNextAction($nextAction);
        $lockTimestamp = $object->getLockTimestamp();
        $object->setLockTimestamp(
            $lockTimestamp ?: null,
        );
        $lastAction = $object->getData(IndexingEntity::LAST_ACTION);
        if (!($lastAction instanceof Actions)) {
            if (null === $lastAction) {
                $lastAction = '';
            }
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
