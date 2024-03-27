<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Model\MagentoAttributeInterface;

class MagentoAttribute implements MagentoAttributeInterface
{
    /**
     * @var int
     */
    private readonly int $attributeId;
    /**
     * @var string
     */
    private readonly string $attributeCode;
    /**
     * @var string
     */
    private readonly string $apiKey;
    /**
     * @var bool
     */
    private bool $isIndexable;
    /**
     * @var string
     */
    private readonly string $klevuAttributeName;

    /**
     * @param int $attributeId
     * @param string $attributeCode
     * @param string $apiKey
     * @param bool $isIndexable
     * @param string $klevuAttributeName
     */
    public function __construct(
        int $attributeId,
        string $attributeCode,
        string $apiKey,
        bool $isIndexable,
        string $klevuAttributeName,
    ) {
        $this->attributeId = $attributeId;
        $this->attributeCode = $attributeCode;
        $this->apiKey = $apiKey;
        $this->isIndexable = $isIndexable;
        $this->klevuAttributeName = $klevuAttributeName;
    }

    /**
     * @return int
     */
    public function getAttributeId(): int
    {
        return $this->attributeId;
    }

    /**
     * @return string
     */
    public function getAttributeCode(): string
    {
        return $this->attributeCode;
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

    /**
     * @return string
     */
    public function getKlevuAttributeName(): string
    {
        return $this->klevuAttributeName;
    }
}
