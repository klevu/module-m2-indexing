<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Mapper;

use Klevu\IndexingApi\Service\Mapper\AttributeTypeMapperServiceInterface;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Magento\Catalog\Model\Product\Attribute\Backend\Boolean;
use Magento\Eav\Api\Data\AttributeInterface as EavAttributeInterface;

class AttributeTypeMapperService implements AttributeTypeMapperServiceInterface
{
    /**
     * @var array<string, DataType>
     */
    private array $customMapping = [];
    /**
     * @var DataType[]
     */
    private array $supportedTypes = [
        DataType::MULTIVALUE,
        DataType::NUMBER,
        DataType::STRING,
    ];

    /**
     * @param array<string, string> $customMapping
     */
    public function __construct(array $customMapping = [])
    {
        array_walk($customMapping, [$this, 'setCustomMapping']);
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return DataType
     */
    public function execute(EavAttributeInterface $attribute): DataType
    {
        if ($this->hasCustomMapping($attribute)) {
            $return = $this->getCustomMapping($attribute);
            if (in_array($return, $this->supportedTypes, true)) {
                return $return;
            }
        }

        return match (true) {
            // update to DataType::DATETIME when supported in Klevu Indexing v3
            $this->isDateAttribute($attribute) => DataType::STRING,
            // update to DataType:: BOOLEAN when supported in Klevu Indexing v3
            $this->isBooleanAttribute($attribute) => DataType::STRING,
            $this->isMultiValueAttribute($attribute) => DataType::MULTIVALUE,
            // update once supported in Klevu Indexing v3
            $this->isEnumAttribute($attribute) => DataType::STRING,
            $this->isNumericAttribute($attribute) => DataType::NUMBER,
            default => DataType::STRING,
        };
    }

    /**
     * @param string $dataType
     * @param string $attributeCode
     *
     * @return void
     */
    private function setCustomMapping(string $dataType, string $attributeCode): void
    {
        $this->customMapping[$attributeCode] = DataType::from($dataType);
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return bool
     */
    private function hasCustomMapping(EavAttributeInterface $attribute): bool
    {
        return array_key_exists(
            key: $attribute->getAttributeCode(),
            array: $this->customMapping,
        );
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return DataType
     */
    private function getCustomMapping(EavAttributeInterface $attribute): DataType
    {
        return $this->customMapping[$attribute->getAttributeCode()];
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return bool
     */
    private function isDateAttribute(EavAttributeInterface $attribute): bool // @phpstan-ignore-line
    {
        return $attribute->getBackendType() === 'datetime'
            || in_array(needle: $attribute->getFrontendInput(), haystack: ['date', 'datetime'], strict: true);
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return bool
     */
    private function isBooleanAttribute(EavAttributeInterface $attribute): bool // @phpstan-ignore-line
    {
        return $attribute->getFrontendInput() === 'boolean'
            || $attribute->getBackendModel() === Boolean::class;
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return bool
     */
    private function isMultiValueAttribute(EavAttributeInterface $attribute): bool
    {
        return $attribute->getFrontendInput() === 'multiselect';
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return bool
     */
    private function isNumericAttribute(EavAttributeInterface $attribute): bool // @phpstan-ignore-line
    {
        return in_array(needle: $attribute->getBackendType(), haystack: ['int', 'decimal'], strict: true)
            && $attribute->getFrontendInput() !== 'select';
    }

    /**
     * @param EavAttributeInterface $attribute
     *
     * @return bool
     */
    private function isEnumAttribute(EavAttributeInterface $attribute): bool // @phpstan-ignore-line
    {
        return $attribute->getFrontendInput() === 'select'
            && $attribute->getBackendType() === 'int';
    }
}
