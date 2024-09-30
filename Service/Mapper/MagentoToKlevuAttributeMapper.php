<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Mapper;

use Klevu\Configuration\Exception\ApiKeyNotFoundException;
use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingApi\Service\Provider\StandardAttributesProviderInterface;
use Klevu\PhpSDK\Exception\ApiExceptionInterface;
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
     * @var StandardAttributesProviderInterface
     */
    private readonly StandardAttributesProviderInterface $standardAttributesProvider;
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
     * @var string[]
     */
    private array $standardAttributes = [];

    /**
     * @param AttributeRepositoryInterface $attributeRepository
     * @param StandardAttributesProviderInterface $standardAttributesProvider
     * @param string $entityType
     * @param string $prefix
     * @param string[]|null $attributeMapping
     */
    public function __construct(
        AttributeRepositoryInterface $attributeRepository,
        StandardAttributesProviderInterface $standardAttributesProvider,
        string $entityType,
        string $prefix = '',
        ?array $attributeMapping = [],
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->standardAttributesProvider = $standardAttributesProvider;
        $this->entityType = $entityType;
        $this->prefix = $prefix;
        $this->attributeMapping = $attributeMapping;
    }

    /**
     * @param AttributeInterface $attribute
     * @param string|null $apiKey
     *
     * @return string
     * @throws ApiExceptionInterface
     * @throws AttributeMappingMissingException
     * @throws ApiKeyNotFoundException
     */
    public function get(AttributeInterface $attribute, ?string $apiKey = null): string
    {
        $attributeCode = (string)$attribute->getAttributeCode();

        return $this->getByCode(attributeCode: $attributeCode, apiKey: $apiKey);
    }

    /**
     * @param string $attributeName
     * @param string|null $apiKey
     *
     * @return AttributeInterface
     * @throws ApiExceptionInterface
     * @throws NoSuchEntityException
     * @throws ApiKeyNotFoundException
     */
    public function reverse(string $attributeName, ?string $apiKey = null): AttributeInterface
    {
        $attributeCode = $this->reverseForCode($attributeName, $apiKey);

        return $this->attributeRepository->get(
            entityTypeCode: $this->entityType,
            attributeCode: $attributeCode,
        );
    }

    /**
     * @param string $attributeCode
     * @param string|null $apiKey
     *
     * @return string
     * @throws ApiExceptionInterface
     * @throws AttributeMappingMissingException
     * @throws ApiKeyNotFoundException
     */
    public function getByCode(string $attributeCode, ?string $apiKey = null): string
    {
        $attributeName = ($this->attributeMapping[$attributeCode] ?? null)
            ? $this->attributeMapping[$attributeCode]
            : null;

        if (
            $this->isPrefixAdditionRequired(
                attributeCode: $attributeCode,
                apiKey: $apiKey,
                attributeName: $attributeName,
            )
        ) {
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
     * @param string|null $apiKey
     *
     * @return string
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    public function reverseForCode(string $attributeName, ?string $apiKey = null): string
    {
        $attributeCode = array_search(needle: $attributeName, haystack: $this->attributeMapping, strict: true)
            ?: $attributeName;
        if (
            $this->isPrefixRemovalRequired(
                attributeCode: $attributeCode,
                attributeName: $attributeName,
                apiKey: $apiKey,
            )
        ) {
            $attributeCode = substr(string: $attributeCode, offset: strlen($this->prefix));
        }

        return $attributeCode;
    }

    /**
     * @param string $attributeCode
     * @param string|null $apiKey
     * @param string|null $attributeName
     *
     * @return bool
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    private function isPrefixAdditionRequired(
        string $attributeCode,
        ?string $apiKey = null,
        ?string $attributeName = null,
    ): bool {
        return !$attributeName
            && $this->prefix
            && !in_array(
                needle: $attributeCode,
                haystack: $this->getStandardAttributes(apiKey: $apiKey),
                strict: true,
            );
    }

    /**
     * @param string $attributeCode
     * @param string $attributeName
     *
     * @param string|null $apiKey
     *
     * @return bool
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    private function isPrefixRemovalRequired(
        string $attributeCode,
        string $attributeName,
        ?string $apiKey = null,
    ): bool {
        return ($attributeCode === $attributeName)
            && $this->prefix
            && str_starts_with(haystack: $attributeName, needle: $this->prefix)
            && !in_array(
                needle: $attributeName,
                haystack: $this->getStandardAttributes(apiKey: $apiKey),
                strict: true,
            );
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

    /**
     * @param string|null $apiKey
     *
     * @return string[]
     * @throws ApiExceptionInterface
     * @throws ApiKeyNotFoundException
     */
    private function getStandardAttributes(?string $apiKey = null): array
    {
        if (!($this->standardAttributes[$apiKey] ?? null)) {
            $this->standardAttributes[$apiKey] = $this->standardAttributesProvider->getAttributeCodes(
                apiKey: $apiKey,
                includeAliases: true,
            );
        }

        return $this->standardAttributes[$apiKey];
    }
}
