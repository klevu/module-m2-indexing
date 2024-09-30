<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Configuration\Model\CurrentScopeFactory;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\Indexing\Exception\StoreApiKeyException;
use Klevu\IndexingApi\Api\ConvertEavAttributeToIndexingAttributeActionInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\Determiner\IsAttributeIndexableDeterminerInterface;
use Klevu\IndexingApi\Service\Provider\AttributeDiscoveryProviderInterface;
use Klevu\IndexingApi\Service\Provider\AttributeProviderProviderInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AttributeDiscoveryProvider implements AttributeDiscoveryProviderInterface
{
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var ApiKeyProviderInterface
     */
    private readonly ApiKeyProviderInterface $apiKeyProvider;
    /**
     * @var CurrentScopeFactory
     */
    private readonly CurrentScopeFactory $currentScopeFactory;
    /**
     * @var IsAttributeIndexableDeterminerInterface
     */
    private readonly IsAttributeIndexableDeterminerInterface $isIndexableDeterminer;
    /**
     * @var AttributeProviderProviderInterface
     */
    private readonly AttributeProviderProviderInterface $attributeProviderProvider;
    /**
     * @var string
     */
    private readonly string $attributeType;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var ConvertEavAttributeToIndexingAttributeActionInterface
     */
    private readonly ConvertEavAttributeToIndexingAttributeActionInterface $convertEavAttributeToIndexingAttributeAction; // phpcs:ignore Generic.Files.LineLength.TooLong

    /**
     * @param StoreManagerInterface $storeManager
     * @param ApiKeyProviderInterface $apiKeyProvider
     * @param CurrentScopeFactory $currentScopeFactory
     * @param IsAttributeIndexableDeterminerInterface $isIndexableDeterminer
     * @param AttributeProviderProviderInterface $attributeProviderProvider
     * @param string $attributeType
     * @param LoggerInterface $logger
     * @param ConvertEavAttributeToIndexingAttributeActionInterface $convertEavAttributeToIndexingAttributeAction
     *
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ApiKeyProviderInterface $apiKeyProvider,
        CurrentScopeFactory $currentScopeFactory,
        IsAttributeIndexableDeterminerInterface $isIndexableDeterminer,
        AttributeProviderProviderInterface $attributeProviderProvider,
        string $attributeType,
        LoggerInterface $logger,
        ConvertEavAttributeToIndexingAttributeActionInterface $convertEavAttributeToIndexingAttributeAction,
    ) {
        $this->storeManager = $storeManager;
        $this->apiKeyProvider = $apiKeyProvider;
        $this->currentScopeFactory = $currentScopeFactory;
        $this->isIndexableDeterminer = $isIndexableDeterminer;
        $this->attributeProviderProvider = $attributeProviderProvider;
        $this->attributeType = $attributeType;
        $this->logger = $logger;
        $this->convertEavAttributeToIndexingAttributeAction = $convertEavAttributeToIndexingAttributeAction;
    }

    /**
     * @return string
     */
    public function getAttributeType(): string
    {
        return $this->attributeType;
    }

    /**
     *
     * @param string[]|null $apiKeys
     * @param int[]|null $attributeIds
     *
     * @return MagentoAttributeInterface[][]
     * @throws NoSuchEntityException
     * @throws StoreApiKeyException
     */
    public function getData(?array $apiKeys = [], ?array $attributeIds = []): array
    {
        $return = [];
        $storeFound = false;
        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $storeApiKey = $this->apiKeyProvider->get(
                scope: $this->currentScopeFactory->create(data: ['scopeObject' => $store]),
            );
            if (!$storeApiKey || ($apiKeys && !in_array($storeApiKey, $apiKeys, true))) {
                continue;
            }
            $storeFound = true;
            $return[$storeApiKey] = $this->createIndexingAttributes(
                store: $store,
                magentoAttributes: $return[$storeApiKey] ?? [],
                attributeIds: $attributeIds,
            );
        }
        if (!$storeFound) {
            throw new StoreApiKeyException(
                __('No store found with the provided API Key.'),
            );
        }

        return $return;
    }

    /**
     * @param StoreInterface $store
     * @param mixed[] $magentoAttributes
     * @param int[] $attributeIds
     *
     * @return MagentoAttributeInterface[]
     */
    private function createIndexingAttributes(
        StoreInterface $store,
        array $magentoAttributes,
        array $attributeIds,
    ): array {
        foreach ($this->attributeProviderProvider->get() as $entityType => $attributeProvider) {
            foreach ($attributeProvider->get($attributeIds) as $attribute) {
                $isIndexable = $this->isIndexableDeterminer->execute(
                    attribute: $attribute,
                    store: $store,
                );
                $magentoAttributes = $this->setMagentoAttribute(
                    magentoAttributes: $magentoAttributes,
                    attribute: $attribute,
                    isIndexable: $isIndexable,
                    entityType: $entityType,
                    store: $store,
                );
            }
        }

        return $magentoAttributes;
    }

    /**
     * @param MagentoAttributeInterface[] $magentoAttributes
     * @param AttributeInterface $attribute
     * @param bool $isIndexable
     * @param string $entityType
     * @param StoreInterface $store
     *
     * @return MagentoAttributeInterface[]
     * @throws NoSuchEntityException
     */
    private function setMagentoAttribute(
        array $magentoAttributes,
        AttributeInterface $attribute,
        bool $isIndexable,
        string $entityType,
        StoreInterface $store,
    ): array {
        $attributeId = (int)$attribute->getId(); // @phpstan-ignore-line

        try {
            $magentoAttributes[$attributeId] = ($magentoAttributes[$attributeId] ?? null)
                ? $this->updateIsIndexableForMagentoAttribute(
                    magentoAttribute: $magentoAttributes[$attributeId],
                    isIndexable: $isIndexable,
                )
                : $this->convertEavAttributeToIndexingAttributeAction->execute(
                    entityType: $entityType,
                    attribute: $attribute,
                    store: $store,
                );
        } catch (AttributeMappingMissingException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $magentoAttributes;
    }

    /**
     * @param MagentoAttributeInterface $magentoAttribute
     * @param bool $isIndexable
     *
     * @return MagentoAttributeInterface
     */
    private function updateIsIndexableForMagentoAttribute(
        MagentoAttributeInterface $magentoAttribute,
        bool $isIndexable,
    ): MagentoAttributeInterface {
        // if attribute is indexable in ANY store the apiKey is assigned to, set as indexable
        $magentoAttribute->setIsIndexable(
            isIndexable: $magentoAttribute->isIndexable() || $isIndexable,
        );

        return $magentoAttribute;
    }
}
