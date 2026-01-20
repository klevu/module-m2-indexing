<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Plugin\Eav\Model\ResourceModel\Entity\Attribute;

use Klevu\Indexing\Exception\IndexingEntitySaveException;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\KlevuToMagentoEntityTypeMapInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToUpdateActionInterface;
use Klevu\IndexingApi\Service\Provider\AttributesToWatchProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\IndexingApi\Service\Provider\TargetEntityIdsToUpdateForAttributeSetProviderInterface;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\ResourceModel\Entity\Attribute as AttributeResource;
use Magento\Framework\Model\AbstractModel;
use Psr\Log\LoggerInterface;

class AddAttributeToSetPlugin
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
     * @var int[]|null
     */
    private ?array $originalAttributeIdsInSet = null;
    /**
     * @var array<string, array<string, int>>
     */
    private array $attributeIdsToWatch = [];

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
     * @param AttributeResource $subject
     * @param callable $proceed
     * @param AbstractModel $object
     * @param int $attributeEntityId
     * @param int $attributeSetId
     * @param int $attributeGroupId
     * @param int $attributeSortOrder
     *
     * @return array|null[]
     */
    public function beforeSaveInSetIncluding(
        AttributeResource $subject,
        AbstractModel $object,
        $attributeEntityId = null,
        $attributeSetId = null,
        $attributeGroupId = null,
        $attributeSortOrder = null,
    ): array {
        $return = [
            $object,
            $attributeEntityId,
            $attributeSetId,
            $attributeGroupId,
            $attributeSortOrder,
        ];
        if (null !== $this->originalAttributeIdsInSet) {
            return $return;
        }

        if (!($object instanceof Attribute)) {
            return $return;
        }

        $attributeId = (null === $attributeEntityId)
            ? (int)$object->getId()
            : (int)$attributeEntityId;
        $attributeSetId = (int)($attributeSetId ?? $object->getAttributeSetId());
        if (!$attributeId || !$attributeSetId) {
            return $return;
        }

        $this->originalAttributeIdsInSet = $this->getOriginalAttributeIdsInSet(
            subject: $subject,
            entityTypeId: (int)$object->getEntityTypeId(),
            attributeSetId: $attributeSetId,
        );

        return $return;
    }

    /**
     * @param AttributeResource $subject
     * @param AttributeResource $result
     * @param AbstractModel $object
     * @param int $attributeEntityId
     * @param int $attributeSetId
     * @param int $attributeGroupId
     * @param int $attributeSortOrder
     *
     * @return AttributeResource
     */
    public function afterSaveInSetIncluding(
        AttributeResource $subject,
        AttributeResource $result,
        AbstractModel $object,
        $attributeEntityId = null,
        $attributeSetId = null,
        $attributeGroupId = null,
        $attributeSortOrder = null,
    ): AttributeResource {
        if (!($object instanceof Attribute)) {
            return $result;
        }

        $attributeId = (null === $attributeEntityId)
            ? (int)$object->getId()
            : (int)$attributeEntityId;
        if (
            null === $this->originalAttributeIdsInSet
            || in_array($attributeId, $this->originalAttributeIdsInSet, true)
        ) {
            return $result;
        }

        $attributeGroupId = (int)($attributeGroupId ?? $object->getAttributeGroupId());
        $attributeSetId = (int)($attributeSetId ?? $object->getAttributeSetId());
        $klevuEntityType = $this->klevuToMagentoEntityTypeMap->getKlevuEntityTypeForMagentoEntityTypeId(
            magentoEntityTypeId: (int)$object->getEntityTypeId(),
        );

        if (
            !$attributeId
            || !$attributeGroupId
            || !$attributeSetId
            || !$klevuEntityType
            || !array_key_exists(
                key: $klevuEntityType,
                array: $this->targetEntityIdsToUpdateForAttributeSetProviders,
            )
        ) {
            return $result;
        }

        $attributeIdsToWatch = $this->getAttributeIdsToWatch(
            subject: $subject,
            klevuEntityType: $klevuEntityType,
        );
        if (!in_array($attributeId, $attributeIdsToWatch, true)) {
            return $result;
        }

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
                    message: 'Could not update indexing entities after save attribute in set',
                    context: [
                        'method' => __METHOD__,
                        'exception' => $exception::class,
                        'error' => $exception->getMessage(),
                        'klevuEntityType' => $klevuEntityType,
                        'attributeSetId' => $attributeSetId,
                        'attributeCode' => $object->getAttributeCode(),
                        'indexingEntityIds' => $indexingEntityIds,
                    ],
                );
            }
        }

        return $result;
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
     * @param AttributeResource $subject
     * @param int $entityTypeId
     * @param int $attributeSetId
     *
     * @return int[]
     */
    private function getOriginalAttributeIdsInSet(
        AttributeResource $subject,
        int $entityTypeId,
        int $attributeSetId,
    ): array {
        $connection = $subject->getConnection();
        $select = $connection->select();
        $select->from(
            name: $subject->getTable('eav_entity_attribute'),
            cols: ['attribute_id'],
        );
        $select->where(
            cond: 'entity_type_id = ?',
            value: $entityTypeId,
        );
        $select->where(
            cond: 'attribute_set_id = ?',
            value: $attributeSetId,
        );

        return array_map(
            callback: 'intval',
            array: $connection->fetchCol($select),
        );
    }

    /**
     * @param AttributeResource $subject
     * @param string $klevuEntityType
     *
     * @return array<string, int>
     */
    private function getAttributeIdsToWatch(
        AttributeResource $subject,
        string $klevuEntityType,
    ): array {
        if (array_key_exists($klevuEntityType, $this->attributeIdsToWatch)) {
            return $this->attributeIdsToWatch[$klevuEntityType];
        }

        $this->attributeIdsToWatch[$klevuEntityType] = [];
        $attributesToWatchProvider = $this->attributesToWatchProviders[$klevuEntityType] ?? null;
        if (!$attributesToWatchProvider) {
            return $this->attributeIdsToWatch[$klevuEntityType];
        }

        $attributeCodesToWatch = $attributesToWatchProvider->getAttributeCodes();
        if (!$attributeCodesToWatch) {
            return $this->attributeIdsToWatch[$klevuEntityType];
        }

        $connection = $subject->getConnection();
        $select = $connection->select();
        $select->from(
            name: $subject->getTable('eav_attribute'),
            cols: ['attribute_id', 'attribute_code'],
        );
        $select->where(
            cond: 'attribute_code IN (?)',
            value: $attributeCodesToWatch,
        );

        foreach ($connection->fetchAssoc($select) as $row) {
            $this->attributeIdsToWatch[$klevuEntityType][$row['attribute_code']] = (int)$row['attribute_id'];
        }

        return $this->attributeIdsToWatch[$klevuEntityType];
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
