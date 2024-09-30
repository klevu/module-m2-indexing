<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Observer\Sync\Attributes;

use Klevu\IndexingApi\Service\Action\UpdateIndexingAttributeActionsActionInterface;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingApi\Service\Provider\MagentoToKlevuAttributeMapperProviderInterface;
use Klevu\IndexingApi\Service\Provider\StaticAttributeProviderInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class DeleteAttributeObserver implements ObserverInterface
{
    /**
     * @var MagentoToKlevuAttributeMapperInterface
     */
    private readonly MagentoToKlevuAttributeMapperInterface $attributeToNameMapper;
    /**
     * @var UpdateIndexingAttributeActionsActionInterface
     */
    private readonly UpdateIndexingAttributeActionsActionInterface $updateIndexingAttributeActionsAction;
    /**
     * @var string
     */
    private readonly string $entityType;
    /**
     * @var StaticAttributeProviderInterface[]
     */
    private array $staticAttributeProviders;

    /**
     * @param MagentoToKlevuAttributeMapperProviderInterface $attributeToNameMapperProvider
     * @param UpdateIndexingAttributeActionsActionInterface $updateIndexingAttributeActionsAction
     * @param string $entityType
     * @param StaticAttributeProviderInterface[] $staticAttributeProviders
     */
    public function __construct(
        MagentoToKlevuAttributeMapperProviderInterface $attributeToNameMapperProvider,
        UpdateIndexingAttributeActionsActionInterface $updateIndexingAttributeActionsAction,
        string $entityType,
        array $staticAttributeProviders,
    ) {
        $this->attributeToNameMapper = $attributeToNameMapperProvider->getByType(entityType: $entityType);
        $this->updateIndexingAttributeActionsAction = $updateIndexingAttributeActionsAction;
        $this->entityType = $entityType;
        array_walk($staticAttributeProviders, [$this, 'addStaticAttributeProvider']);
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $apiKey = $event->getData(key: 'api_key');
        $attributeName = $event->getData(key: 'attribute_name');
        $attributeType = $event->getData(key: 'attribute_type');
        if (!$apiKey || !$attributeName || $attributeType !== $this->entityType) {
            return;
        }
        $this->updateIndexingAttributeActionsAction->execute(
            apiKey: $apiKey,
            targetCode: $this->getAttributeCode(attributeName: $attributeName, apiKey: $apiKey),
        );
    }

    /**
     * @param StaticAttributeProviderInterface $staticAttributeProvider
     *
     * @return void
     */
    private function addStaticAttributeProvider(StaticAttributeProviderInterface $staticAttributeProvider): void
    {
        $this->staticAttributeProviders[] = $staticAttributeProvider;
    }

    /**
     * @param string $attributeName
     * @param string $apiKey
     *
     * @return string
     */
    private function getAttributeCode(string $attributeName, string $apiKey): string
    {
        return $this->getAttributeCodeFromStaticProvider($attributeName)
            ?? $this->attributeToNameMapper->reverseForCode(
                attributeName: $attributeName,
                apiKey: $apiKey,
            );
    }

    /**
     * @param string $attributeName
     *
     * @return string|null
     */
    private function getAttributeCodeFromStaticProvider(string $attributeName): ?string
    {
        $return = null;
        foreach ($this->staticAttributeProviders as $attributeProvider) {
            $attribute = $attributeProvider->getByAttributeCode($attributeName);
            if ($attribute) {
                $return = $attribute->getAttributeCode();
                break;
            }
        }

        return $return;
    }
}
