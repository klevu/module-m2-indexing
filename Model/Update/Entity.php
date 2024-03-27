<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\Update;

use Klevu\IndexingApi\Model\Update\EntityInterface;

class Entity implements EntityInterface
{
    public const ENTITY_TYPE = 'entityType';
    public const ENTITY_IDS = 'entityIds';
    public const STORE_IDS = 'storeIds';
    public const CUSTOMER_GROUP_IDS = 'customerGroupIds';
    public const ATTRIBUTES = 'attributes';

    /**
     * @var string
     */
    private string $entityType;
    /**
     * @var int[]
     */
    private array $entityIds;
    /**
     * @var int[]
     */
    private array $storeIds;
    /**
     * @var int[]
     */
    private array $customerGroupIds;
    /**
     * @var string[]
     */
    private array $attributes;

    /**
     * @param mixed[] $data
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $data)
    {
        array_walk($data, [$this, 'setData']);
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * @param string $entityType
     *
     * @return void
     */
    public function setEntityType(string $entityType): void
    {
        $this->entityType = $entityType;
    }

    /**
     * @return int[]
     */
    public function getEntityIds(): array
    {
        return $this->entityIds;
    }

    /**
     * @param int[] $entityIds
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setEntityIds(array $entityIds): void
    {
        array_walk($entityIds, [$this, 'validateIsInt'], static::ENTITY_IDS);
        $this->entityIds = $entityIds;
    }

    /**
     * @return int[]
     */
    public function getStoreIds(): array
    {
        return $this->storeIds;
    }

    /**
     * @param int[] $storeIds
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setStoreIds(array $storeIds): void
    {
        array_walk($storeIds, [$this, 'validateIsInt'], static::STORE_IDS);
        $this->storeIds = $storeIds;
    }

    /**
     * @return int[]
     */
    public function getCustomerGroupIds(): array
    {
        return $this->customerGroupIds;
    }

    /**
     * @param int[] $customerGroupIds
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setCustomerGroupIds(array $customerGroupIds): void
    {
        array_walk($customerGroupIds, [$this, 'validateIsInt'], static::CUSTOMER_GROUP_IDS);
        $this->customerGroupIds = $customerGroupIds;
    }

    /**
     * @return string[]
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param string[] $attributes
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    public function setAttributes(array $attributes): void
    {
        array_walk($attributes, [$this, 'validateIsString'], static::ATTRIBUTES);
        $this->attributes = $attributes;
    }

    /**
     * @param mixed $value
     * @param string $key
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function setData(mixed $value, string $key): void
    {
        match ($key) {
            static::ENTITY_TYPE => $this->setEntityType($value),
            static::ENTITY_IDS => $this->setEntityIds($value),
            static::STORE_IDS => $this->setStoreIds($value),
            static::CUSTOMER_GROUP_IDS => $this->setCustomerGroupIds($value),
            static::ATTRIBUTES => $this->setAttributes($value),
            default => throw new \InvalidArgumentException(
                sprintf(
                    'Invalid key provided in creation of %s. Key %s',
                    $this::class,
                    $key,
                ),
            ),
        };
    }

    /**
     * @param mixed $value
     * @param mixed $key
     * @param string $attribute
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateIsInt(mixed $value, mixed $key, string $attribute): void
    {
        if (is_int($value)) {
            return;
        }
        throw new \InvalidArgumentException(
            sprintf(
                'Invalid value supplied for %s at position %s. Expects int, received %s',
                $attribute,
                $key,
                get_debug_type($value),
            ),
        );
    }

    /**
     * @param mixed $value
     * @param mixed $key
     * @param string $attribute
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateIsString(mixed $value, mixed $key, string $attribute): void
    {
        if (is_string($value)) {
            return;
        }
        throw new \InvalidArgumentException(
            sprintf(
                'Invalid value supplied for %s at position %s. Expects string, received %s',
                $attribute,
                $key,
                get_debug_type($value),
            ),
        );
    }
}
