<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Observer\Sync\Attributes;

use Klevu\IndexingApi\Service\Action\UpdateIndexingAttributeActionsActionInterface;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
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
     * @param MagentoToKlevuAttributeMapperInterface $attributeToNameMapper
     * @param UpdateIndexingAttributeActionsActionInterface $updateIndexingAttributeActionsAction
     * @param string $entityType
     */
    public function __construct(
        MagentoToKlevuAttributeMapperInterface $attributeToNameMapper,
        UpdateIndexingAttributeActionsActionInterface $updateIndexingAttributeActionsAction,
        string $entityType,
    ) {
        $this->attributeToNameMapper = $attributeToNameMapper;
        $this->updateIndexingAttributeActionsAction = $updateIndexingAttributeActionsAction;
        $this->entityType = $entityType;
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
            targetCode: $this->attributeToNameMapper->reverseForCode($attributeName),
        );
    }
}
