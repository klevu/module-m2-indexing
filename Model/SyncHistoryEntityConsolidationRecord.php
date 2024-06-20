<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder

use Klevu\Indexing\Model\ResourceModel\SyncHistoryEntityConsolidationRecord as SyncHistoryEntityConsolidationResourceModel; // phpcs:ignore Generic.Files.LineLength.TooLong
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;

class SyncHistoryEntityConsolidationRecord
    extends AbstractModel
    implements SyncHistoryEntityConsolidationRecordInterface
{
    public const ENTITY_ID = 'entity_id';
    public const TARGET_ENTITY_TYPE = 'target_entity_type';
    public const TARGET_ID = 'target_id';
    public const TARGET_PARENT_ID = 'target_parent_id';
    public const API_KEY = 'api_key';
    public const HISTORY = 'history';
    public const DATE = 'date';

    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;

    /**
     * @param SerializerInterface $serializer
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param mixed[] $data
     */
    public function __construct(
        SerializerInterface $serializer,
        Context $context,
        Registry $registry,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
    ) {
        parent::__construct(
            context: $context,
            registry: $registry,
            resource: $resource,
            resourceCollection: $resourceCollection,
            data: $data,
        );

        $this->serializer = $serializer;
    }

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            resourceModel: SyncHistoryEntityConsolidationResourceModel::class,
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
     * @return string
     */
    public function getHistory(): string
    {
        return $this->getData(key: static::HISTORY);
    }

    /**
     * @param string $history
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setHistory(string $history): void
    {
        $this->serializer->unserialize(string: $history);
        $this->setData(key: static::HISTORY, value: $history);
    }

    /**
     * @return string
     */
    public function getDate(): string
    {
        return $this->getData(key: static::DATE);
    }

    /**
     * @param string $date
     *
     * @return void
     */
    public function setDate(string $date): void
    {
        $this->setData(key: static::DATE, value: $date);
    }
}
