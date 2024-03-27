<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Mapper;

use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\IndexingApi\Model\Source\StandardAttribute;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class MagentoToKlevuAttributeMapper implements MagentoToKlevuAttributeMapperInterface
{
    /**
     * @var AttributeRepositoryInterface
     */
    private readonly AttributeRepositoryInterface $attributeRepository;
    /**
     * @var string
     */
    private readonly string $entityType;
    /**
     * @var string
     */
    private readonly string $prefix;
    /**
     * @var string[]|null
     */
    private ?array $attributeMapping;

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param string $entityType
     * @param string $prefix
     * @param string[]|null $attributeMapping
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        string $entityType,
        string $prefix = '',
        ?array $attributeMapping = [],
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->entityType = $entityType;
        $this->prefix = $prefix;
        $this->attributeMapping = $attributeMapping;
    }

    /**
     * @param AttributeInterface $attribute
     *
     * @return string
     * @throws AttributeMappingMissingException
     */
    public function get(AttributeInterface $attribute): string
    {
        $attributeCode = $attribute->getAttributeCode();

        return $this->getByCode(attributeCode: $attributeCode);
    }

    /**
     * @param string $attributeName
     *
     * @return AttributeInterface
     * @throws NoSuchEntityException
     */
    public function reverse(string $attributeName): AttributeInterface
    {
        $attributeCode = $this->reverseForCode($attributeName);

        return $this->attributeRepository->get(
            entityTypeCode: $this->entityType,
            attributeCode: $attributeCode,
        );
    }

    /**
     * @param string $attributeCode
     *
     * @return string
     * @throws AttributeMappingMissingException
     */
    public function getByCode(string $attributeCode): string
    {
        $attributeName = ($this->attributeMapping[$attributeCode] ?? null)
            ? $this->attributeMapping[$attributeCode]
            : null;

        if ($this->isPrefixAdditionRequired(attributeCode: $attributeCode, attributeName: $attributeName)) {
            $attributeName = $this->prefix . $attributeCode;
        }
        if ($this->isMappingMissing(attributeCode: $attributeCode, attributeName: $attributeName)) {
            $mappedMagentoAttribute = array_search(
                needle: $this->prefix . $attributeCode,
                haystack: $this->attributeMapping,
                strict: true,
            );
            throw new AttributeMappingMissingException(
                __(
                    'Attribute mapping for Magento attribute %1 is missing. '
                    . 'Klevu attribute %2 is mapped to Magento attribute %3. '
                    . '2 Magento attributes can not be mapped to the same Klevu attribute. '
                    . 'Either add mapping for Magento attribute %1 or set it not to be indexable.',
                    $attributeCode,
                    $this->attributeMapping[$mappedMagentoAttribute],
                    $mappedMagentoAttribute,
                ),
            );
        }

        return $attributeName ?? $attributeCode;
    }

    /**
     * @param string $attributeName
     *
     * @return string
     */
    public function reverseForCode(string $attributeName): string
    {
        $attributeCode = array_search(needle: $attributeName, haystack: $this->attributeMapping, strict: true)
            ?: $attributeName;
        if ($this->isPrefixRemovalRequired(attributeCode: $attributeCode, attributeName: $attributeName)) {
            $attributeCode = substr(string: $attributeCode, offset: strlen($this->prefix));
        }

        return $attributeCode;
    }

    /**
     * @param string $attributeCode
     * @param string|null $attributeName
     *
     * @return bool
     */
    private function isPrefixAdditionRequired( string $attributeCode, ?string $attributeName = null): bool
    {
        return !$attributeName
            && $this->prefix
            && !in_array(needle: $attributeCode, haystack: StandardAttribute::values(), strict: true);
    }

    /**
     * @param string $attributeCode
     * @param string $attributeName
     *
     * @return bool
     */
    private function isPrefixRemovalRequired(string $attributeCode, string $attributeName): bool
    {
        return ($attributeCode === $attributeName)
            && $this->prefix
            && str_starts_with(haystack: $attributeName, needle: $this->prefix)
            && !in_array(needle: $attributeName, haystack: StandardAttribute::values(), strict: true);
    }

    /**
     * @param string $attributeCode
     * @param string|null $attributeName
     *
     * @return bool
     */
    private function isMappingMissing(string $attributeCode, ?string $attributeName = null): bool
    {
        return (null === $attributeName)
            && in_array(needle: $this->prefix . $attributeCode, haystack: $this->attributeMapping, strict: true);
    }
}
