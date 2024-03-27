<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Model\MagentoEntityInterface;

class MagentoEntity implements MagentoEntityInterface
{
    /**
     * @var int
     */
    private readonly int $entityId;
    /**
     * @var string
     */
    private readonly string $apiKey;
    /**
     * @var bool
     */
    private bool $isIndexable;
    /**
     * @var int|null
     */
    private readonly ?int $entityParentId;

    /**
     * @param int $entityId
     * @param int|null $entityParentId
     * @param string $apiKey
     * @param bool $isIndexable
     */
    public function __construct(
        int $entityId,
        string $apiKey,
        bool $isIndexable,
        ?int $entityParentId = null,
    ) {
        $this->entityId = $entityId;
        $this->apiKey = $apiKey;
        $this->isIndexable = $isIndexable;
        $this->entityParentId = $entityParentId;
    }

    /**
     * @return int
     */
    public function getEntityId(): int
    {
        return $this->entityId;
    }

    /**
     * @return int|null
     */
    public function getEntityParentId(): ?int
    {
        return $this->entityParentId;
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @return bool
     */
    public function isIndexable(): bool
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
}
