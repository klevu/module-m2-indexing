<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Store\Api\Data\StoreInterface;

class EntitySyncConditionsValues implements EntitySyncConditionsValuesInterface
{
    /**
     * @var string
     */
    private string $apiKey = '';
    /**
     * @var string 
     */
    private string $targetEntityType = '';
    /**
     * @var ExtensibleDataInterface|PageInterface|null
     */
    private ExtensibleDataInterface|PageInterface|null $targetEntity = null;
    /**
     * @var ExtensibleDataInterface|PageInterface|null
     */
    private ExtensibleDataInterface|PageInterface|null $targetParentEntity = null;
    /**
     * @var StoreInterface|null
     */
    private ?StoreInterface $store = null;
    /**
     * @var IndexingEntityInterface|null
     */
    private ?IndexingEntityInterface $indexingEntity = null;
    /**
     * @var bool|null
     */
    private ?bool $isIndexable = null;
    /**
     * @var array<string, bool>
     */
    private array $syncConditionsValues = [];

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @param string $apiKey
     *
     * @return void
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }
    
    /**
     * @return string
     */
    public function getTargetEntityType(): string
    {
        return $this->targetEntityType;
    }
    
    /**
     * @param string $entityType
     *
     * @return void
     */
    public function setTargetEntityType(string $entityType): void
    {
        $this->targetEntityType = $entityType;
    }

    /**
     * @return ExtensibleDataInterface|PageInterface|null
     */
    public function getTargetEntity(): ExtensibleDataInterface|PageInterface|null
    {
        return $this->targetEntity;
    }

    /**
     * @param ExtensibleDataInterface|PageInterface $targetEntity
     *
     * @return void
     */
    public function setTargetEntity(ExtensibleDataInterface|PageInterface $targetEntity): void
    {
        $this->targetEntity = $targetEntity;
    }

    /**
     * @return ExtensibleDataInterface|PageInterface|null
     */
    public function getTargetParentEntity(): ExtensibleDataInterface|PageInterface|null
    {
        return $this->targetParentEntity;
    }

    /**
     * @param ExtensibleDataInterface|PageInterface $targetParentEntity
     *
     * @return void
     */
    public function setTargetParentEntity(ExtensibleDataInterface|PageInterface $targetParentEntity): void
    {
        $this->targetParentEntity = $targetParentEntity;
    }

    /**
     * @return StoreInterface|null
     */
    public function getStore(): ?StoreInterface
    {
        return $this->store;
    }

    /**
     * @param StoreInterface $store
     *
     * @return void
     */
    public function setStore(StoreInterface $store): void
    {
        $this->store = $store;
    }

    /**
     * @return IndexingEntityInterface|null
     */
    public function getIndexingEntity(): ?IndexingEntityInterface
    {
        return $this->indexingEntity;
    }

    /**
     * @param IndexingEntityInterface $indexingEntity
     *
     * @return void
     */
    public function setIndexingEntity(IndexingEntityInterface $indexingEntity): void
    {
        $this->indexingEntity = $indexingEntity;
    }

    /**
     * @return bool|null
     */
    public function getIsIndexable(): ?bool
    {
        return $this->isIndexable;
    }

    /**
     * @param bool $isIndexable
     *
     * @return void
     */
    public function setIsIndexable(bool $isIndexable): void
    {
        $this->isIndexable = $isIndexable;
    }
    
    /**
     * @return array<string, bool>
     */
    public function getSyncConditionsValues(): array
    {
        return $this->syncConditionsValues;
    }
    
    /**
     * @param array<string, bool> $values
     *
     * @return void
     */
    public function setSyncConditionsValues(array $values): void
    {
        $this->syncConditionsValues = [];
        array_walk(
            array: $values, 
            callback: function (bool $value, string $key): void {
                $this->addSyncConditionsValue($key, $value);
            },
        );
    }

    /**
     * @param string $key
     * @param bool $value
     *
     * @return void
     */
    public function addSyncConditionsValue(string $key, bool $value): void
    {
        $this->syncConditionsValues[$key] = $value;
    }
}
