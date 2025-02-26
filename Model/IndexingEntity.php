<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder

use Klevu\Indexing\Model\ResourceModel\IndexingEntity as IndexingEntityResourceModel;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Magento\Framework\Model\AbstractModel;

class IndexingEntity extends AbstractModel implements IndexingEntityInterface
{
    public const ENTITY_ID = 'entity_id';
    public const TARGET_ENTITY_TYPE = 'target_entity_type';
    public const TARGET_ENTITY_SUBTYPE = 'target_entity_subtype';
    public const TARGET_ID = 'target_id';
    public const TARGET_PARENT_ID = 'target_parent_id';
    public const API_KEY = 'api_key';
    public const NEXT_ACTION = 'next_action';
    public const LOCK_TIMESTAMP = 'lock_timestamp';
    public const LAST_ACTION = 'last_action';
    public const LAST_ACTION_TIMESTAMP = 'last_action_timestamp';
    public const IS_INDEXABLE = 'is_indexable';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            resourceModel: IndexingEntityResourceModel::class,
        );
    }

    /**
     * @return string
     */
    public function getTargetEntityType(): string
    {
        return (string)$this->getData(key: static::TARGET_ENTITY_TYPE);
    }

    /**
     * @param string $entityType
     *
     * @return void
     */
    public function setTargetEntityType(string $entityType): void
    {
        $this->setData(key: static::TARGET_ENTITY_TYPE, value: $entityType);
    }

    /**
     * @return string|null
     */
    public function getTargetEntitySubtype(): ?string
    {
        $subType = $this->getData(key: static::TARGET_ENTITY_SUBTYPE);

        return $subType ? (string)$subType : null;
    }

    /**
     * @param string|null $entitySubtype
     *
     * @return void
     */
    public function setTargetEntitySubtype(?string $entitySubtype): void
    {
        $this->setData(key: static::TARGET_ENTITY_SUBTYPE, value: $entitySubtype);
    }

    /**
     * @return int
     */
    public function getTargetId(): int
    {
        return (int)$this->getData(key: static::TARGET_ID);
    }

    /**
     * @param int $targetId
     *
     * @return void
     */
    public function setTargetId(int $targetId): void
    {
        $this->setData(key: static::TARGET_ID, value: $targetId);
    }

    /**
     * @return int|null
     */
    public function getTargetParentId(): ?int
    {
        $id = $this->getData(key: static::TARGET_PARENT_ID);

        return $id ? (int)$id : null;
    }

    /**
     * @param int|null $targetParentId
     *
     * @return void
     */
    public function setTargetParentId(?int $targetParentId = null): void
    {
        $this->setData(key: static::TARGET_PARENT_ID, value: $targetParentId);
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return (string)$this->getData(key: static::API_KEY);
    }

    /**
     * @param string $apiKey
     *
     * @return void
     */
    public function setApiKey(string $apiKey): void
    {
        $this->setData(key: static::API_KEY, value: $apiKey);
    }

    /**
     * @return Actions
     */
    public function getNextAction(): Actions
    {
        return $this->getData(key: static::NEXT_ACTION);
    }

    /**
     * @param Actions $nextAction
     *
     * @return void
     */
    public function setNextAction(Actions $nextAction): void
    {
        $this->setData(key: static::NEXT_ACTION, value: $nextAction);
    }

    /**
     * @return string|null
     */
    public function getLockTimestamp(): ?string
    {
        $timestamp = $this->getData(key: static::LOCK_TIMESTAMP);

        return $timestamp
            ? (string)$timestamp
            : null;
    }

    /**
     * @param string|null $lockTimestamp
     *
     * @return void
     */
    public function setLockTimestamp(?string $lockTimestamp = null): void
    {
        $this->setData(key: static::LOCK_TIMESTAMP, value: $lockTimestamp);
    }

    /**
     * @return \Klevu\IndexingApi\Model\Source\Actions
     */
    public function getLastAction(): Actions
    {
        return $this->getData(key: static::LAST_ACTION);
    }

    /**
     * @param Actions $lastAction
     *
     * @return void
     */
    public function setLastAction(Actions $lastAction): void
    {
        $this->setData(key: static::LAST_ACTION, value: $lastAction);
    }

    /**
     * @return string|null
     */
    public function getLastActionTimestamp(): ?string
    {
        $timestamp = $this->getData(key: static::LAST_ACTION_TIMESTAMP);

        return $timestamp
            ? (string)$timestamp
            : null;
    }

    /**
     * @param string|null $lastActionTimestamp
     *
     * @return void
     */
    public function setLastActionTimestamp(?string $lastActionTimestamp = null): void
    {
        $this->setData(key: static::LAST_ACTION_TIMESTAMP, value: $lastActionTimestamp);
    }

    /**
     * @return bool
     */
    public function getIsIndexable(): bool
    {
        return (bool)$this->getData(key: static::IS_INDEXABLE);
    }

    /**
     * @param bool $isIndexable
     *
     * @return void
     */
    public function setIsIndexable(bool $isIndexable): void
    {
        $this->setData(key: static::IS_INDEXABLE, value: (bool)$isIndexable);
    }

    /**
     * @return $this
     */
    protected function _clearData(): self
    {
        $this->setData([]);
        $this->setOrigData();
        $this->storedData = [];

        return $this;
    }
}
