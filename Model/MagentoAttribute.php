<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\PhpSDK\Model\Indexing\DataType;

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
     * @var DataType|null
     */
    private ?DataType $klevuAttributeType = null;
    /**
     * @var string[]
     */
    private array $generateConfigurationForEntitySubtypes = [];
    /**
     * @var bool|null
     */
    private ?bool $isGlobal = null;
    /**
     * @var bool|null
     */
    private ?bool $usesSourceModel = null;
    /**
     * @var bool|null
     */
    private ?bool $isHtmlAllowed = null;
    /**
     * @var bool|null
     */
    private ?bool $allowsMultipleValues = null;

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

    /**
     * @param DataType $klevuAttributeType
     *
     * @return void
     */
    public function setKlevuAttributeType(DataType $klevuAttributeType): void
    {
        $this->klevuAttributeType = $klevuAttributeType;
    }

    /**
     * @return DataType|null
     */
    public function getKlevuAttributeType(): ?DataType
    {
        return $this->klevuAttributeType;
    }

    /**
     * @param string[] $generateConfigurationForEntitySubtypes
     *
     * @return void
     */
    public function setGenerateConfigurationForEntitySubtypes(array $generateConfigurationForEntitySubtypes): void
    {
        $this->generateConfigurationForEntitySubtypes = $generateConfigurationForEntitySubtypes;
    }

    /**
     * @return string[]
     */
    public function getGenerateConfigurationForEntitySubtypes(): array
    {
        return $this->generateConfigurationForEntitySubtypes;
    }

    /**
     * @param bool $isGlobal
     *
     * @return void
     */
    public function setIsGlobal(bool $isGlobal): void
    {
        $this->isGlobal = $isGlobal;
    }

    /**
     * @return bool|null
     */
    public function isGlobal(): ?bool
    {
        return $this->isGlobal;
    }

    /**
     * @param bool $usesSourceModel
     *
     * @return void
     */
    public function setUsesSourceModel(bool $usesSourceModel): void
    {
        $this->usesSourceModel = $usesSourceModel;
    }

    /**
     * @return bool|null
     */
    public function usesSourceModel(): ?bool
    {
        return $this->usesSourceModel;
    }

    /**
     * @param bool $isHtmlAllowed
     *
     * @return void
     */
    public function setIsHtmlAllowed(bool $isHtmlAllowed): void
    {
        $this->isHtmlAllowed = $isHtmlAllowed;
    }

    /**
     * @return bool|null
     */
    public function isHtmlAllowed(): ?bool
    {
        return $this->isHtmlAllowed;
    }

    /**
     * @param bool $allowsMultipleValues
     *
     * @return void
     */
    public function setAllowsMultipleValues(bool $allowsMultipleValues): void
    {
        $this->allowsMultipleValues = $allowsMultipleValues;
    }

    public function allowsMultipleValues(): ?bool
    {
        return $this->allowsMultipleValues;
    }
}
