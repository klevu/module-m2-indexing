<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder

use Klevu\Indexing\Model\ResourceModel\IndexingAttribute as IndexingAttributeResourceModel;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Magento\Framework\Model\AbstractModel;

class IndexingAttribute extends AbstractModel implements IndexingAttributeInterface
{
    public const ENTITY_ID = 'entity_id';
    public const TARGET_ATTRIBUTE_TYPE = 'target_attribute_type';
    public const TARGET_ID = 'target_id';
    public const TARGET_CODE = 'target_code';
    public const API_KEY = 'api_key';
    public const NEXT_ACTION = 'next_action';
    public const LOCK_TIMESTAMP = 'lock_timestamp';
    public const LAST_ACTION = 'last_action';
    public const LAST_ACTION_TIMESTAMP = 'last_action_timestamp';
    public const IS_INDEXABLE = 'is_indexable';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            resourceModel: IndexingAttributeResourceModel::class,
        );
    }

    /**
     * @return string
     */
    public function getTargetAttributeType(): string
    {
        return (string)$this->getData(key: static::TARGET_ATTRIBUTE_TYPE);
    }

    /**
     * @param string $attributeType
     *
     * @return void
     */
    public function setTargetAttributeType(string $attributeType): void
    {
        $this->setData(key: static::TARGET_ATTRIBUTE_TYPE, value: $attributeType);
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
     * @return string
     */
    public function getTargetCode(): string
    {
        return $this->getData(key: static::TARGET_CODE);
    }

    /**
     * @param string $targetCode
     *
     * @return void
     */
    public function setTargetCode(string $targetCode): void
    {
        $this->setData(key: static::TARGET_CODE, value: $targetCode);
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
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        $createdAt = $this->getData(key: static::CREATED_AT);

        return $createdAt
            ? (string)$createdAt
            : null;
    }

    /**
     * @param string|null $createdAt
     *
     * @return void
     */
    public function setCreatedAt(?string $createdAt = null): void
    {
        $this->setData(key: static::CREATED_AT, value: $createdAt);
    }

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        $updatedAt = $this->getData(key: static::UPDATED_AT);

        return $updatedAt
            ? (string)$updatedAt
            : null;
    }

    /**
     * @param string|null $updatedAt
     *
     * @return void
     */
    public function setUpdatedAt(?string $updatedAt = null): void
    {
        $this->setData(key: static::UPDATED_AT, value: $updatedAt);
    }
}
