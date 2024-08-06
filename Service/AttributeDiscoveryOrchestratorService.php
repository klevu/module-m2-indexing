<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Exception\IndexingAttributeSaveException;
use Klevu\Indexing\Model\DiscoveryResultFactory;
use Klevu\IndexingApi\Api\Data\DiscoveryResultInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\Action\AddIndexingAttributesActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingAttributesToBeIndexableActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingAttributesToDeleteActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingAttributesToUpdateActionInterface;
use Klevu\IndexingApi\Service\AttributeConflictHandlerServiceInterface;
use Klevu\IndexingApi\Service\AttributeDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\FilterAttributesToAddServiceInterface;
use Klevu\IndexingApi\Service\FilterAttributesToDeleteServiceInterface;
use Klevu\IndexingApi\Service\FilterAttributesToSetToIndexableServiceInterface;
use Klevu\IndexingApi\Service\FilterAttributesToUpdateServiceInterface;
use Klevu\IndexingApi\Service\Provider\AttributeDiscoveryProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class AttributeDiscoveryOrchestratorService implements AttributeDiscoveryOrchestratorServiceInterface
{
    /**
     * @var DiscoveryResultFactory
     */
    private readonly DiscoveryResultFactory $discoveryResultFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var AddIndexingAttributesActionInterface
     */
    private readonly AddIndexingAttributesActionInterface $addIndexingAttributesAction;
    /**
     * @var SetIndexingAttributesToDeleteActionInterface
     */
    private readonly SetIndexingAttributesToDeleteActionInterface $setIndexingAttributesToDeleteAction;
    /**
     * @var SetIndexingAttributesToUpdateActionInterface
     */
    private readonly SetIndexingAttributesToUpdateActionInterface $setIndexingAttributesToUpdateAction;
    /**
     * @var SetIndexingAttributesToBeIndexableActionInterface
     */
    private readonly SetIndexingAttributesToBeIndexableActionInterface $setIndexingAttributesToBeIndexableAction;
    /**
     * @var FilterAttributesToAddServiceInterface
     */
    private readonly FilterAttributesToAddServiceInterface $filterAttributesToAddService;
    /**
     * @var FilterAttributesToDeleteServiceInterface
     */
    private readonly FilterAttributesToDeleteServiceInterface $filterAttributesToDeleteService;
    /**
     * @var FilterAttributesToUpdateServiceInterface
     */
    private readonly FilterAttributesToUpdateServiceInterface $filterAttributesToUpdateService;
    /**
     * @var FilterAttributesToSetToIndexableServiceInterface
     */
    private readonly FilterAttributesToSetToIndexableServiceInterface $filterAttributesToSetToIndexableService;
    /**
     * @var AttributeConflictHandlerServiceInterface
     */
    private readonly AttributeConflictHandlerServiceInterface $attributeConflictHandlerService;
    /**
     * @var AttributeDiscoveryProviderInterface[]
     */
    private array $discoveryProviders = [];
    /**
     * @var bool
     */
    private bool $success = true;
    /**
     * @var string[]
     */
    private array $messages = [];

    /**
     * @param DiscoveryResultFactory $discoveryResultFactory
     * @param LoggerInterface $logger
     * @param AddIndexingAttributesActionInterface $addIndexingAttributesAction
     * @param SetIndexingAttributesToDeleteActionInterface $setIndexingAttributesToDeleteAction
     * @param SetIndexingAttributesToUpdateActionInterface $setIndexingAttributesToUpdateAction
     * @param SetIndexingAttributesToBeIndexableActionInterface $setIndexingAttributesToBeIndexableAction
     * @param FilterAttributesToAddServiceInterface $filterAttributesToAddService
     * @param FilterAttributesToDeleteServiceInterface $filterAttributesToDeleteService
     * @param FilterAttributesToUpdateServiceInterface $filterAttributesToUpdateService
     * @param FilterAttributesToSetToIndexableServiceInterface $filterAttributesToSetToIndexableService
     * @param AttributeDiscoveryProviderInterface[] $discoveryProviders
     * @param AttributeConflictHandlerServiceInterface $attributeConflictHandlerService
     */
    public function __construct(
        DiscoveryResultFactory $discoveryResultFactory,
        LoggerInterface $logger,
        AddIndexingAttributesActionInterface $addIndexingAttributesAction,
        SetIndexingAttributesToDeleteActionInterface $setIndexingAttributesToDeleteAction,
        SetIndexingAttributesToUpdateActionInterface $setIndexingAttributesToUpdateAction,
        SetIndexingAttributesToBeIndexableActionInterface $setIndexingAttributesToBeIndexableAction,
        FilterAttributesToAddServiceInterface $filterAttributesToAddService,
        FilterAttributesToDeleteServiceInterface $filterAttributesToDeleteService,
        FilterAttributesToUpdateServiceInterface $filterAttributesToUpdateService,
        FilterAttributesToSetToIndexableServiceInterface $filterAttributesToSetToIndexableService,
        AttributeConflictHandlerServiceInterface $attributeConflictHandlerService,
        array $discoveryProviders = [],
    ) {
        $this->discoveryResultFactory = $discoveryResultFactory;
        $this->logger = $logger;
        $this->addIndexingAttributesAction = $addIndexingAttributesAction;
        $this->setIndexingAttributesToDeleteAction = $setIndexingAttributesToDeleteAction;
        $this->setIndexingAttributesToUpdateAction = $setIndexingAttributesToUpdateAction;
        $this->setIndexingAttributesToBeIndexableAction = $setIndexingAttributesToBeIndexableAction;
        $this->filterAttributesToAddService = $filterAttributesToAddService;
        $this->filterAttributesToDeleteService = $filterAttributesToDeleteService;
        $this->filterAttributesToUpdateService = $filterAttributesToUpdateService;
        $this->filterAttributesToSetToIndexableService = $filterAttributesToSetToIndexableService;
        $this->attributeConflictHandlerService = $attributeConflictHandlerService;
        array_walk($discoveryProviders, [$this, 'addDiscoveryProvider']);
    }

    /**
     *
     * @param string|null $attributeType
     * @param string[]|null $apiKeys
     * @param int[]|null $attributeIds
     *
     * @return DiscoveryResultInterface
     */
    public function execute(
        ?string $attributeType = null,
        ?array $apiKeys = [],
        ?array $attributeIds = [],
    ): DiscoveryResultInterface {
        $this->validateDiscoveryProviders();
        $providers = $this->filterDiscoveryProviders($attributeType);
        $magentoAttributesByApiKeys = [];
        foreach ($providers as $discoveryProvider) {
            try {
                $magentoAttributesByApiKey = $discoveryProvider->getData(
                    apiKeys: $apiKeys,
                    attributeIds: $attributeIds,
                );
            } catch (LocalizedException $exception) {
                $this->messages[] = $exception->getMessage();
                $this->success = false;
                continue;
            }
            $type = $discoveryProvider->getAttributeType();
            $magentoAttributesByApiKeys[$type] = array_merge(
                $magentoAttributesByApiKeys[$type] ?? [],
                $magentoAttributesByApiKey,
            );
        }

        foreach ($magentoAttributesByApiKeys as $type => $magentoAttributesByApiKey) {
            // add any missing attributes to klevu_indexing_attribute
            $this->addMissingAttributesToIndexAttributesTable(
                type: $type,
                magentoAttributesByApiKey: $magentoAttributesByApiKey,
            );
            // set as indexable any indexable attributes that were set to not indexable
            // also sets next action to add
            $this->setNonIndexableAttributesToBeIndexable(
                type: $type,
                magentoAttributesByApiKey: $magentoAttributesByApiKey,
                attributeIds: $attributeIds,
            );
            // set next action to update for any attributes that require an update
            $this->setAttributesToUpdate(
                type: $type,
                attributeIds: $attributeIds,
                apiKeys: array_keys($magentoAttributesByApiKey),
            );
            // set attributes that are no longer indexable to next action delete
            $this->setNonIndexableAttributesToDelete(
                type: $type,
                magentoAttributesByApiKey: $magentoAttributesByApiKey,
                attributeIds: $attributeIds,
            );
        }

        $this->attributeConflictHandlerService->execute();

        return $this->discoveryResultFactory->create(data: [
            'isSuccess' => $this->success,
            'messages' => $this->messages,
        ]);
    }

    /**
     * @param AttributeDiscoveryProviderInterface $discoveryProvider
     *
     * @return void
     */
    private function addDiscoveryProvider(AttributeDiscoveryProviderInterface $discoveryProvider): void
    {
        $this->discoveryProviders[] = $discoveryProvider;
    }

    /**
     * @return void
     */
    private function validateDiscoveryProviders(): void
    {
        if (!$this->discoveryProviders) {
            $message = 'No providers available for attribute discovery.';
            $this->logger->warning(
                message: 'Method: {method} - Warning: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $message,
                ],
            );
            $this->success = false;
            $this->messages[] = $message;
        }
    }

    /**
     * @param string $type
     * @param MagentoAttributeInterface[][] $magentoAttributesByApiKey
     *
     * @return void
     */
    private function addMissingAttributesToIndexAttributesTable(
        string $type,
        array $magentoAttributesByApiKey,
    ): void {
        $filteredMagentoAttributesByApiKey = $this->filterAttributesToAddService->execute(
            magentoAttributesByApiKey: $magentoAttributesByApiKey,
            entityType: $type,
        );
        foreach ($filteredMagentoAttributesByApiKey as $magentoAttributes) {
            try {
                $this->addIndexingAttributesAction->execute(
                    type: $type,
                    magentoAttributes: $magentoAttributes,
                );
            } catch (IndexingAttributeSaveException $exception) {
                $this->success = false;
                $this->messages[] = $exception->getMessage();
            }
        }
    }

    /**
     * @param string $type
     * @param MagentoAttributeInterface[][] $magentoAttributesByApiKey
     * @param int[]|null $attributeIds
     *
     * @return void
     */
    private function setNonIndexableAttributesToBeIndexable(
        string $type,
        array $magentoAttributesByApiKey,
        ?array $attributeIds = [],
    ): void {
        if (!$attributeIds) {
            return;
        }
        $klevuAttributeIds = $this->filterAttributesToSetToIndexableService->execute(
            magentoAttributesByApiKey: $magentoAttributesByApiKey,
            type: $type,
            attributeIds: $attributeIds,
        );
        try {
            $this->setIndexingAttributesToBeIndexableAction->execute($klevuAttributeIds);
        } catch (IndexingAttributeSaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }

    /**
     * @param string $type
     * @param int[] $attributeIds
     * @param string[] $apiKeys
     *
     * @return void
     */
    private function setAttributesToUpdate(
        string $type,
        array $attributeIds,
        array $apiKeys,
    ): void {
        if (!$attributeIds) {
            return;
        }
        $klevuAttributeIds = $this->filterAttributesToUpdateService->execute(
            type: $type,
            attributeIds: $attributeIds,
            apiKeys: $apiKeys,
        );

        try {
            $this->setIndexingAttributesToUpdateAction->execute($klevuAttributeIds);
        } catch (IndexingAttributeSaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }

    /**
     * @param string $type
     * @param MagentoAttributeInterface[][] $magentoAttributesByApiKey
     * @param int[] $attributeIds
     *
     * @return void
     */
    private function setNonIndexableAttributesToDelete(
        string $type,
        array $magentoAttributesByApiKey,
        array $attributeIds,
    ): void {
        $klevuAttributeIds = $this->filterAttributesToDeleteService->execute(
            magentoAttributesByApiKey: $magentoAttributesByApiKey,
            type: $type,
            attributeIds: $attributeIds,
        );

        try {
            $this->setIndexingAttributesToDeleteAction->execute(attributeIds: $klevuAttributeIds);
        } catch (IndexingAttributeSaveException $exception) {
            $this->success = false;
            $this->messages[] = $exception->getMessage();
        }
    }

    /**
     * @param string|null $attributeType
     *
     * @return AttributeDiscoveryProviderInterface[]
     */
    private function filterDiscoveryProviders(?string $attributeType = null): array
    {
        if (!$attributeType) {
            return $this->discoveryProviders;
        }

        $return = array_filter(
            array: $this->discoveryProviders,
            callback: static fn (AttributeDiscoveryProviderInterface $provider) => (
                $provider->getAttributeType() === $attributeType
            ),
        );
        if (!$return) {
            $this->success = false;
            $this->messages[] = 'Supplied attribute type did not match any providers.';
        }

        return $return;
    }
}
