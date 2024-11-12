<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\Configuration\Model\CurrentScopeInterfaceFactory;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\IndexingApi\Api\ConvertEavAttributeToIndexingAttributeActionInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterfaceFactory;
use Klevu\IndexingApi\Service\Determiner\IsAttributeIndexableDeterminerInterface;
use Klevu\IndexingApi\Service\Mapper\AttributeTypeMapperServiceInterface;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;

class ConvertEavAttributeToIndexingAttributeAction implements ConvertEavAttributeToIndexingAttributeActionInterface
{
    /**
     * @var ApiKeyProviderInterface
     */
    private readonly ApiKeyProviderInterface $apiKeyProvider;
    /**
     * @var CurrentScopeInterfaceFactory
     */
    private readonly CurrentScopeInterfaceFactory $currentScopeFactory;
    /**
     * @var MagentoAttributeInterfaceFactory
     */
    private readonly MagentoAttributeInterfaceFactory $magentoAttributeFactory;
    /**
     * @var AttributeTypeMapperServiceInterface[]
     */
    private array $attributeTypeMappers = [];
    /**
     * @var IsAttributeIndexableDeterminerInterface[]
     */
    private array $isIndexableDeterminers = [];
    /**
     * @var MagentoToKlevuAttributeMapperInterface[]
     */
    private array $attributeMappers = [];

    /**
     * @param ApiKeyProviderInterface $apiKeyProvider
     * @param CurrentScopeInterfaceFactory $currentScopeFactory
     * @param MagentoAttributeInterfaceFactory $magentoAttributeInterfaceFactory
     * @param IsAttributeIndexableDeterminerInterface[] $isIndexableDeterminers
     * @param AttributeTypeMapperServiceInterface[] $attributeTypeMappers
     * @param MagentoToKlevuAttributeMapperInterface[] $attributeMappers
     */
    public function __construct(
        ApiKeyProviderInterface $apiKeyProvider,
        CurrentScopeInterfaceFactory $currentScopeFactory,
        MagentoAttributeInterfaceFactory $magentoAttributeInterfaceFactory,
        array $isIndexableDeterminers,
        array $attributeTypeMappers,
        array $attributeMappers,
    ) {
        $this->apiKeyProvider = $apiKeyProvider;
        $this->currentScopeFactory = $currentScopeFactory;
        $this->magentoAttributeFactory = $magentoAttributeInterfaceFactory;
        array_walk($isIndexableDeterminers, [$this, 'addIsIndexableDeterminer']);
        array_walk($attributeTypeMappers, [$this, 'addAttributeTypeMapper']);
        array_walk($attributeMappers, [$this, 'addAttributeMapper']);
    }

    /**
     * @param string $entityType
     * @param AttributeInterface $attribute
     * @param StoreInterface|null $store
     *
     * @return MagentoAttributeInterface
     * @throws AttributeMappingMissingException
     * @throws NoSuchEntityException
     */
    public function execute(
        string $entityType,
        AttributeInterface $attribute,
        ?StoreInterface $store,
    ): MagentoAttributeInterface {
        /** @var AttributeInterface&DataObject $attribute */
        $generateConfigurationForEntitySubtypes = $attribute->getData(
            key: MagentoAttributeInterface::ATTRIBUTE_PROPERTY_GENERATE_CONFIGURATION_FOR_ENTITY_SUBTYPES,
        );
        if (!is_array($generateConfigurationForEntitySubtypes)) {
            $generateConfigurationForEntitySubtypes = explode(
                separator: ',',
                string: (string)$generateConfigurationForEntitySubtypes,
            );
        }

        $apiKey = (null !== $store)
            ? $this->apiKeyProvider->get(
                scope: $this->currentScopeFactory->create([
                    'scopeObject' => $store,
                ]),
            )
            : '';
        $magentoAttribute = $this->magentoAttributeFactory->create([
            'attributeId' => (int)$attribute->getAttributeId(),
            'attributeCode' => (string)$attribute->getAttributeCode(),
            'isIndexable' => $this->getIsIndexable(
                entityType: $entityType,
                attribute: $attribute,
                store: $store,
            ),
            'apiKey' => (string)$apiKey,
            'klevuAttributeName' => $this->getAttributeName(
                entityType: $entityType,
                attribute: $attribute,
                apiKey: $apiKey ?: null,
            ),
        ]);

        $magentoAttribute->setGenerateConfigurationForEntitySubtypes($generateConfigurationForEntitySubtypes);
        $klevuAttributeType = $this->getKlevuAttributeType(
            entityType: $entityType,
            attribute: $attribute,
        );
        if ($klevuAttributeType) {
            $magentoAttribute->setKlevuAttributeType($klevuAttributeType);
        }
        $magentoAttribute->setIsGlobal(
            isGlobal: ScopedAttributeInterface::SCOPE_GLOBAL === (int)$attribute->getDataUsingMethod('is_global'),
        );
        $magentoAttribute->setUsesSourceModel(
            usesSourceModel: match (true) {
                $attribute->getBackendModel() === ArrayBackend::class => true,
                $attribute->getFrontendInput() === 'multiselect' => true,
                default => (bool)$attribute->getSourceModel(),
            },
        );
        $magentoAttribute->setIsHtmlAllowed(
            isHtmlAllowed: (bool)$attribute->getDataUsingMethod('is_html_allowed_on_front'),
        );
        $magentoAttribute->setAllowsMultipleValues(
            allowsMultipleValues: match ($attribute->getFrontendInput()) {
                'multiselect' => true,
                default => false,
            },
        );

        return $magentoAttribute;
    }

    /**
     * @param IsAttributeIndexableDeterminerInterface $isIndexableDeterminer
     * @param string $entityType
     *
     * @return void
     */
    private function addIsIndexableDeterminer(
        IsAttributeIndexableDeterminerInterface $isIndexableDeterminer,
        string $entityType,
    ): void {
        $this->isIndexableDeterminers[$entityType] = $isIndexableDeterminer;
    }

    /**
     * @param AttributeTypeMapperServiceInterface $attributeTypeMapper
     * @param string $entityType
     *
     * @return void
     */
    private function addAttributeTypeMapper(
        AttributeTypeMapperServiceInterface $attributeTypeMapper,
        string $entityType,
    ): void {
        $this->attributeTypeMappers[$entityType] = $attributeTypeMapper;
    }

    /**
     * @param MagentoToKlevuAttributeMapperInterface $attributeMapper
     * @param string $entityType
     *
     * @return void
     */
    private function addAttributeMapper(
        MagentoToKlevuAttributeMapperInterface $attributeMapper,
        string $entityType,
    ): void {
        $this->attributeMappers[$entityType] = $attributeMapper;
    }

    /**
     * @param string $entityType
     * @param AttributeInterface $attribute
     * @param StoreInterface|null $store
     *
     * @return bool
     */
    private function getIsIndexable(
        string $entityType,
        AttributeInterface $attribute,
        ?StoreInterface $store,
    ): bool {
        if (null === $store) {
            /** @var AttributeInterface&DataObject $attribute */
            return (bool)$attribute->getDataUsingMethod(
                MagentoAttributeInterface::ATTRIBUTE_PROPERTY_IS_INDEXABLE,
            );
        }

        $isIndexableDeterminer = $this->isIndexableDeterminers[$entityType] ?? null;
        if (!$isIndexableDeterminer) {
            return false;
        }

        return $isIndexableDeterminer->execute(
            attribute: $attribute,
            store: $store,
        );
    }

    /**
     * @param string $entityType
     * @param AttributeInterface $attribute
     *
     * @return DataType|null
     */
    private function getKlevuAttributeType(
        string $entityType,
        AttributeInterface $attribute,
    ): ?DataType {
        $attributeTypeMapper = $this->attributeTypeMappers[$entityType] ?? null;

        return $attributeTypeMapper?->execute($attribute);
    }

    /**
     * @param string $entityType
     * @param AttributeInterface $attribute
     * @param string|null $apiKey
     *
     * @return string
     * @throws AttributeMappingMissingException
     */
    private function getAttributeName(
        string $entityType,
        AttributeInterface $attribute,
        ?string $apiKey,
    ): string {
        $attributeMapper = $this->attributeMappers[$entityType] ?? null;

        return $attributeMapper?->get($attribute, $apiKey) ?: (string)$attribute->getAttributeCode();
    }
}
