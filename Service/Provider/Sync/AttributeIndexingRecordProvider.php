<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider\Sync;

use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\AttributeIndexingDeleteRecordCreatorServiceInterface;
use Klevu\IndexingApi\Service\AttributeIndexingRecordCreatorServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sync\AttributeIndexingRecordProviderInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Psr\Log\LoggerInterface;

class AttributeIndexingRecordProvider implements AttributeIndexingRecordProviderInterface
{
    /**
     * @var IndexingAttributeProviderInterface
     */
    private readonly IndexingAttributeProviderInterface $indexingAttributeProvider;
    /**
     * @var AttributeProviderInterface
     */
    private readonly AttributeProviderInterface $attributeProvider;
    /**
     * @var AttributeIndexingRecordCreatorServiceInterface
     */
    private readonly AttributeIndexingRecordCreatorServiceInterface $indexingRecordCreatorService;
    /**
     * @var AttributeIndexingDeleteRecordCreatorServiceInterface
     */
    private AttributeIndexingDeleteRecordCreatorServiceInterface $indexingDeleteRecordCreatorService;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var Actions
     */
    private Actions $action;
    /**
     * @var string
     */
    private readonly string $entityType;

    /**
     * @param IndexingAttributeProviderInterface $indexingAttributeProvider
     * @param AttributeProviderInterface $attributeProvider
     * @param AttributeIndexingRecordCreatorServiceInterface $indexingRecordCreatorService
     * @param AttributeIndexingDeleteRecordCreatorServiceInterface $indexingDeleteRecordCreatorService
     * @param LoggerInterface $logger
     * @param string $action
     * @param string $entityType
     */
    public function __construct(
        IndexingAttributeProviderInterface $indexingAttributeProvider,
        AttributeProviderInterface $attributeProvider,
        AttributeIndexingRecordCreatorServiceInterface $indexingRecordCreatorService,
        AttributeIndexingDeleteRecordCreatorServiceInterface $indexingDeleteRecordCreatorService,
        LoggerInterface $logger,
        string $action,
        string $entityType,
    ) {
        $this->indexingAttributeProvider = $indexingAttributeProvider;
        $this->attributeProvider = $attributeProvider;
        $this->indexingRecordCreatorService = $indexingRecordCreatorService;
        $this->indexingDeleteRecordCreatorService = $indexingDeleteRecordCreatorService;
        $this->logger = $logger;
        $this->setAction($action);
        $this->entityType = $entityType;
    }

    /**
     * @param string $apiKey
     *
     * @return \Generator
     */
    public function get(string $apiKey): \Generator
    {
        if ($this->action === Actions::DELETE) {
            return $this->deleteAttributes($apiKey);
        }
        return $this->syncAttributes($apiKey);
    }

    /**
     * @param string $action
     *
     * @return void
     */
    private function setAction(string $action): void
    {
        $this->action = Actions::from($action);
    }

    /**
     * @param string $apiKey
     *
     * @return \Generator
     */
    private function syncAttributes(string $apiKey): \Generator
    {
        $attributeIds = $this->getAttributeIdsToSync($apiKey);
        /** @var AttributeInterface[] $attributes */
        $attributes = $attributeIds
            ? $this->attributeProvider->get(
                attributeIds: $attributeIds,
            )
            : [];
        foreach ($attributes as $attribute) {
            try {
                yield $this->indexingRecordCreatorService->execute($attribute, $apiKey);
            } catch (AttributeMappingMissingException $exception) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }
    }

    /**
     * @param string $apiKey
     *
     * @return int[]
     */
    private function getAttributeIdsToSync(string $apiKey): array
    {
        $indexingAttributes = $this->indexingAttributeProvider->get(
            attributeType: $this->entityType,
            apiKey: $apiKey,
            nextAction: $this->action,
            isIndexable: true,
        );

        return array_map(
            callback: static fn (IndexingAttributeInterface $indexingAttribute): int => (
                (int)$indexingAttribute->getTargetId()
            ),
            array: $indexingAttributes,
        );
    }

    /**
     * @param string $apiKey
     *
     * @return \Generator
     */
    private function deleteAttributes(string $apiKey): \Generator
    {
        $attributeCodes = $this->getAttributeCodesToDelete($apiKey);
        foreach ($attributeCodes as $attributeCode) {
            try {
                yield $this->indexingDeleteRecordCreatorService->execute(attributeCode: $attributeCode);
            } catch (AttributeMappingMissingException $exception) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }
    }

    /**
     * @param string $apiKey
     *
     * @return string[]
     */
    private function getAttributeCodesToDelete(string $apiKey): array
    {
        $indexingAttributes = $this->indexingAttributeProvider->get(
            attributeType: $this->entityType,
            apiKey: $apiKey,
            nextAction: $this->action,
            isIndexable: true,
        );

        return array_map(
            callback: static fn (IndexingAttributeInterface $indexingAttribute): string => (
                $indexingAttribute->getTargetCode()
            ),
            array: $indexingAttributes,
        );
    }
}
