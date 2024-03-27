<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\Update;

use Klevu\IndexingApi\Model\Update\AttributeInterface;

class Attribute implements AttributeInterface
{
    public const ATTRIBUTE_TYPE = 'attributeType';
    public const ATTRIBUTE_IDS = 'attributeIds';
    public const STORE_IDS = 'storeIds';

    /**
     * @var string
     */
    private string $attributeType;
    /**
     * @var int[]
     */
    private array $attributeIds;
    /**
     * @var int[]
     */
    private array $storeIds;

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
    public function getAttributeType(): string
    {
        return $this->attributeType;
    }

    /**
     * @param string $attributeType
     *
     * @return void
     */
    public function setAttributeType(string $attributeType): void
    {
        $this->attributeType = $attributeType;
    }

    /**
     * @return array|int[]
     */
    public function getAttributeIds(): array
    {
        return $this->attributeIds;
    }

    /**
     * @param int[] $attributeIds
     *
     * @return void
     */
    public function setAttributeIds(array $attributeIds): void
    {
        array_walk($attributeIds, [$this, 'validateIsInt'], static::ATTRIBUTE_IDS);
        $this->attributeIds = $attributeIds;
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
     * @param mixed $value
     * @param string $key
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function setData(mixed $value, string $key): void
    {
        match ($key) {
            static::ATTRIBUTE_TYPE => $this->setAttributeType($value),
            static::ATTRIBUTE_IDS => $this->setAttributeIds($value),
            static::STORE_IDS => $this->setStoreIds($value),
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
}
