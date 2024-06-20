<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder

use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityRecord as SyncHistoryEntityRecordResourceModel;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Magento\Framework\Model\AbstractModel;

class SyncHistoryEntityRecord extends AbstractModel implements SyncHistoryEntityRecordInterface
{
    public const ENTITY_ID = 'entity_id';
    public const TARGET_ENTITY_TYPE = 'target_entity_type';
    public const TARGET_ID = 'target_id';
    public const TARGET_PARENT_ID = 'target_parent_id';
    public const API_KEY = 'api_key';
    public const ACTION = 'action';
    public const ACTION_TIMESTAMP = 'action_timestamp';
    public const IS_SUCCESS = 'is_success';
    public const MESSAGE = 'message';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            resourceModel: SyncHistoryEntityRecordResourceModel::class,
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

        return $id
            ? (int)$id
            : null;
    }

    /**
     * @param int|null $targetParentId
     *
     * @return void
     */
    public function setTargetParentId(?int $targetParentId): void
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
    public function getAction(): Actions
    {
        return $this->getData(key: static::ACTION);
    }

    /**
     * @param Actions $action
     *
     * @return void
     */
    public function setAction(Actions $action): void
    {
        $this->setData(key: static::ACTION, value: $action);
    }

    /**
     * @return string
     */
    public function getActionTimestamp(): string
    {
        return (string)$this->getData(key: static::ACTION_TIMESTAMP);
    }

    /**
     * @param string $actionTimestamp
     *
     * @return void
     */
    public function setActionTimestamp(string $actionTimestamp): void
    {
        $this->setData(key: static::ACTION_TIMESTAMP, value: $actionTimestamp);
    }

    /**
     * @return bool
     */
    public function getIsSuccess(): bool
    {
        return (bool)$this->getData(key: static::IS_SUCCESS);
    }

    /**
     * @param bool $success
     *
     * @return void
     */
    public function setIsSuccess(bool $success): void
    {
        $this->setData(key: static::IS_SUCCESS, value: $success);
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        $message = $this->getData(key: static::MESSAGE);

        return $message
            ? (string)$message
            : null;
    }

    /**
     * @param string|null $message
     *
     * @return void
     */
    public function setMessage(?string $message): void
    {
        $this->setData(key: static::MESSAGE, value: $message);
    }
}
