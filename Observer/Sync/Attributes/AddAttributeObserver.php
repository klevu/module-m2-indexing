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
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class AddAttributeObserver implements ObserverInterface
{
    /**
     * @var MagentoToKlevuAttributeMapperInterface
     */
    private readonly MagentoToKlevuAttributeMapperInterface $attributeToNameMapper;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
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
     * @param LoggerInterface $logger
     * @param UpdateIndexingAttributeActionsActionInterface $updateIndexingAttributeActionsAction
     * @param string $entityType
     * @param StaticAttributeProviderInterface[] $staticAttributeProviders
     */
    public function __construct(
        MagentoToKlevuAttributeMapperProviderInterface $attributeToNameMapperProvider,
        LoggerInterface $logger,
        UpdateIndexingAttributeActionsActionInterface $updateIndexingAttributeActionsAction,
        string $entityType,
        array $staticAttributeProviders,
    ) {
        $this->attributeToNameMapper = $attributeToNameMapperProvider->getByType(entityType: $entityType);
        $this->logger = $logger;
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
        try {
            $attributeId = $this->getAttributeId(attributeName: $attributeName, apiKey: $apiKey);
        } catch (NoSuchEntityException) {
            $this->logger->info(
                message: 'Method: {method}, Info: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => sprintf(
                        'The attribute with a "%s" attributeName doesn\'t exist for attribute type %s.',
                        $attributeName,
                        $this->entityType,
                    ),
                ],
            );

            return;
        }

        $this->updateIndexingAttributeActionsAction->execute(
            apiKey: $apiKey,
            targetId: $attributeId,
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
     * @return int
     * @throws NoSuchEntityException
     */
    private function getAttributeId(string $attributeName, string $apiKey): int
    {
        $attribute = $this->getAttributeFromStaticProvider(attributeName: $attributeName)
            ?? $this->attributeToNameMapper->reverse(attributeName: $attributeName, apiKey: $apiKey);

        return (int)$attribute->getAttributeId();
    }

    /**
     * @param string $attributeName
     *
     * @return AttributeInterface|null
     */
    private function getAttributeFromStaticProvider(string $attributeName): ?AttributeInterface
    {
        $return = null;
        foreach ($this->staticAttributeProviders as $attributeProvider) {
            $attribute = $attributeProvider->getByAttributeCode($attributeName);
            if ($attribute) {
                $return = $attribute;
                break;
            }
        }

        return $return;
    }
}
