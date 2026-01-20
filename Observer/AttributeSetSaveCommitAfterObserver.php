<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Observer;

use Klevu\Indexing\Exception\IndexingEntitySaveException;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\KlevuToMagentoEntityTypeMapInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToUpdateActionInterface;
use Klevu\IndexingApi\Service\Provider\AttributesToWatchProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\IndexingApi\Service\Provider\TargetEntityIdsToUpdateForAttributeSetProviderInterface;
use Magento\Eav\Api\Data\AttributeSetInterface;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class AttributeSetSaveCommitAfterObserver implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var KlevuToMagentoEntityTypeMapInterface
     */
    private readonly KlevuToMagentoEntityTypeMapInterface $klevuToMagentoEntityTypeMap;
    /**
     * @var IndexingEntityProviderInterface
     */
    private readonly IndexingEntityProviderInterface $indexingEntityProvider;
    /**
     * @var SetIndexingEntitiesToUpdateActionInterface
     */
    private readonly SetIndexingEntitiesToUpdateActionInterface $setIndexingEntitiesToUpdateAction;
    /**
     * @var array<string, AttributesToWatchProviderInterface>
     */
    private array $attributesToWatchProviders = [];
    /**
     * @var array<string, TargetEntityIdsToUpdateForAttributeSetProviderInterface>
     */
    private array $targetEntityIdsToUpdateForAttributeSetProviders = [];

    /**
     * @param LoggerInterface $logger
     * @param KlevuToMagentoEntityTypeMapInterface $klevuToMagentoEntityTypeMap
     * @param IndexingEntityProviderInterface $indexingEntityProvider
     * @param SetIndexingEntitiesToUpdateActionInterface $setIndexingEntitiesToUpdateAction
     * @param array<string, AttributesToWatchProviderInterface> $attributesToWatchProviders
     * @param array<string, TargetEntityIdsToUpdateForAttributeSetProviderInterface> $targetEntityIdsToUpdateForAttributeSetProviders
     */
    public function __construct(
        LoggerInterface $logger,
        KlevuToMagentoEntityTypeMapInterface $klevuToMagentoEntityTypeMap,
        IndexingEntityProviderInterface $indexingEntityProvider,
        SetIndexingEntitiesToUpdateActionInterface $setIndexingEntitiesToUpdateAction,
        array $attributesToWatchProviders = [],
        array $targetEntityIdsToUpdateForAttributeSetProviders = [],
    ) {
        $this->logger = $logger;
        $this->klevuToMagentoEntityTypeMap = $klevuToMagentoEntityTypeMap;
        $this->indexingEntityProvider = $indexingEntityProvider;
        $this->setIndexingEntitiesToUpdateAction = $setIndexingEntitiesToUpdateAction;
        array_walk(
            array: $attributesToWatchProviders,
            callback: [$this, 'addAttributesToWatchProvider'],
        );
        array_walk(
            array: $targetEntityIdsToUpdateForAttributeSetProviders,
            callback: [$this, 'addTargetEntityIdsToUpdateForAttributeSetProvider'],
        );
    }

    /**
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $attributeSet = $event->getDataUsingMethod('object');
        if (
            !($attributeSet instanceof AttributeSetInterface)
            || !method_exists($attributeSet, 'getDataUsingMethod')
        ) {
            return;
        }

        $removeAttributeCodes = array_map(
            callback: static fn (Attribute $attribute): string => $attribute->getAttributeCode(),
            array: $attributeSet->getDataUsingMethod('remove_attributes') ?: [],
        );
        if (!$removeAttributeCodes) {
            // We handle addition of attributes through a plugin on saveInSetIncluding
            return;
        }

        $klevuEntityType = $this->klevuToMagentoEntityTypeMap->getKlevuEntityTypeForMagentoEntityTypeId(
            magentoEntityTypeId: (int)$attributeSet->getEntityTypeId(),
        );

        $attributesToWatchProvider = $this->attributesToWatchProviders[$klevuEntityType] ?? null;
        if (!$attributesToWatchProvider) {
            return;
        }
        $targetEntityIdsToUpdateForAttributeSetProvider = $this->targetEntityIdsToUpdateForAttributeSetProviders[$klevuEntityType] ?? null; // phpcs:ignore Generic.Files.LineLength.TooLong
        if (!$targetEntityIdsToUpdateForAttributeSetProvider) {
            return;
        }

        $attributeCodesToWatch = $attributesToWatchProvider->getAttributeCodes();
        $attributeCodesToAction = array_intersect(
            $attributeCodesToWatch,
            $removeAttributeCodes,
        );
        if (!$attributeCodesToAction) {
            return;
        }

        $attributeSetId = (int)$attributeSet->getAttributeSetId();
        $indexingEntitiesToUpdateGenerator = $this->getIndexingEntitiesToUpdate(
            klevuEntityType: $klevuEntityType,
            attributeSetId: $attributeSetId,
        );
        foreach ($indexingEntitiesToUpdateGenerator as $indexingEntities) {
            $indexingEntityIds = array_map(
                callback: static fn (IndexingEntityInterface $indexingEntity): int => $indexingEntity->getId(),
                array: $indexingEntities,
            );
            try {
                $this->setIndexingEntitiesToUpdateAction->execute(
                    entityIds: $indexingEntityIds,
                );
            } catch (IndexingEntitySaveException $exception) {
                $this->logger->error(
                    message: 'Could not update indexing entities after attribute set save',
                    context: [
                        'method' => __METHOD__,
                        'exception' => $exception::class,
                        'error' => $exception->getMessage(),
                        'klevuEntityType' => $klevuEntityType,
                        'attributeSetId' => $attributeSetId,
                        'attributeCodesToAction' => $attributeCodesToAction,
                        'indexingEntityIds' => $indexingEntityIds,
                    ],
                );
            }
        }
    }

    /**
     * @param string $klevuEntityType
     * @param int $attributeSetId
     *
     * @return \Generator<array<IndexingEntityInterface>>
     */
    private function getIndexingEntitiesToUpdate(
        string $klevuEntityType,
        int $attributeSetId,
    ): \Generator {
        $targetEntityIdsToUpdateForAttributeSetProvider = $this->targetEntityIdsToUpdateForAttributeSetProviders[$klevuEntityType] ?? null;
        if (!$targetEntityIdsToUpdateForAttributeSetProvider) {
            return;
        }

        $targetEntityIdsToUpdateGenerator = $targetEntityIdsToUpdateForAttributeSetProvider->get(
            attributeSetId: $attributeSetId,
        );
        foreach ($targetEntityIdsToUpdateGenerator as $targetEntityIdsToUpdate) {
            yield $this->indexingEntityProvider->get(
                entityType: $klevuEntityType,
                entityIds: $targetEntityIdsToUpdate,
                nextAction: Actions::NO_ACTION,
                isIndexable: true,
            );
        }
    }

    /**
     * @param AttributesToWatchProviderInterface|null $attributesToWatchProvider
     * @param string $klevuEntityType
     *
     * @return void
     */
    private function addAttributesToWatchProvider(
        ?AttributesToWatchProviderInterface $attributesToWatchProvider,
        string $klevuEntityType,
    ): void {
        if (null === $attributesToWatchProvider) {
            return;
        }

        $this->attributesToWatchProviders[$klevuEntityType] = $attributesToWatchProvider;
    }

    /**
     * @param TargetEntityIdsToUpdateForAttributeSetProviderInterface|null $targetEntityIdsToUpdateForAttributeSetProvider
     * @param string $klevuEntityType
     *
     * @return void
     */
    private function addTargetEntityIdsToUpdateForAttributeSetProvider(
        ?TargetEntityIdsToUpdateForAttributeSetProviderInterface $targetEntityIdsToUpdateForAttributeSetProvider,
        string $klevuEntityType,
    ): void {
        if (null === $targetEntityIdsToUpdateForAttributeSetProvider) {
            return;
        }

        $this->targetEntityIdsToUpdateForAttributeSetProviders[$klevuEntityType] = $targetEntityIdsToUpdateForAttributeSetProvider; // phpcs:ignore Generic.Files.LineLength.TooLong
    }
}
