<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Configuration\Model\CurrentScopeFactory;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\Indexing\Constants;
use Klevu\IndexingApi\Service\AttributeConflictHandlerServiceInterface;
use Klevu\IndexingApi\Service\Provider\ConflictingAttributeNamesProviderInterface;
use Klevu\IndexingApi\Service\Provider\DuplicateAttributeMappingProviderInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Notification\MessageInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class AttributeConflictHandlerService implements AttributeConflictHandlerServiceInterface
{
    /**
     * @var EventManagerInterface
     */
    private readonly EventManagerInterface $eventManager;
    /**
     * @var StoreManagerInterface
     */
    private readonly StoreManagerInterface $storeManager;
    /**
     * @var CurrentScopeFactory
     */
    private readonly CurrentScopeFactory $currentScopeFactory;
    /**
     * @var ApiKeyProviderInterface
     */
    private readonly ApiKeyProviderInterface $apiKeyProvider;
    /**
     * @var ConflictingAttributeNamesProviderInterface
     */
    private readonly ConflictingAttributeNamesProviderInterface $conflictingAttributeNamesProvider;
    /**
     * @var DuplicateAttributeMappingProviderInterface
     */
    private readonly DuplicateAttributeMappingProviderInterface $duplicateAttributeMappingProvider;
    /**
     * @var string[]|null
     */
    private ?array $apiKeys = null;

    /**
     * @param EventManagerInterface $eventManager
     * @param StoreManagerInterface $storeManager
     * @param CurrentScopeFactory $currentScopeFactory
     * @param ApiKeyProviderInterface $apiKeyProvider
     * @param ConflictingAttributeNamesProviderInterface $conflictingAttributeNamesProvider
     * @param DuplicateAttributeMappingProviderInterface $duplicateAttributeMappingProvider
     */
    public function __construct(
        EventManagerInterface $eventManager,
        StoreManagerInterface $storeManager,
        CurrentScopeFactory $currentScopeFactory,
        ApiKeyProviderInterface $apiKeyProvider,
        ConflictingAttributeNamesProviderInterface $conflictingAttributeNamesProvider,
        DuplicateAttributeMappingProviderInterface $duplicateAttributeMappingProvider,
    ) {
        $this->eventManager = $eventManager;
        $this->storeManager = $storeManager;
        $this->currentScopeFactory = $currentScopeFactory;
        $this->apiKeyProvider = $apiKeyProvider;
        $this->conflictingAttributeNamesProvider = $conflictingAttributeNamesProvider;
        $this->duplicateAttributeMappingProvider = $duplicateAttributeMappingProvider;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->handleConflictingAttributeNames();
        $this->handleDuplicateAttributeMapping();
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     */
    private function handleConflictingAttributeNames(): void
    {
        $conflictingAttributeNames = $this->getConflictingAttributeNames();
        if ($conflictingAttributeNames) {
            $details = '';
            foreach ($conflictingAttributeNames as $apiKey => $conflicts) {
                $details .= __('API Key "%1"' . PHP_EOL, $apiKey)->render();
                foreach ($conflicts as $attributeName => $entityTypes) {
                    $details .= __(
                        'Attribute "%1" mapping clash for %2 entity types' . PHP_EOL,
                        $attributeName,
                        implode(', ', $entityTypes),
                    )->render();
                }
            }

            $this->eventManager->dispatch(
                eventName: 'klevu_notifications_upsertNotification',
                data: [
                    'notification_data' => [
                        'type' => Constants::NOTIFICATION_TYPE_CONFLICTING_ATTRIBUTE_NAMES,
                        'severity' => MessageInterface::SEVERITY_MAJOR,
                        // Magic number prevents dependency. See \Klevu\Notifications\Model\Notification::STATUS_ERROR
                        'status' => 3,
                        'message' => 'Conflicting Klevu attribute configuration detected: '
                            . 'multiple attributes map to same attribute name',
                        'details' => $details,
                        'date' => date('Y-m-d H:i:s'),
                        'delete_after_view' => false,
                    ],
                ] ,
            );
        } else {
            $this->eventManager->dispatch(
                eventName: 'klevu_notifications_deleteNotification',
                data: [
                    'notification_data' => [
                        'type' => Constants::NOTIFICATION_TYPE_CONFLICTING_ATTRIBUTE_NAMES,
                    ],
                ],
            );
        }
    }

    /**
     * @return string[]
     * @throws NoSuchEntityException
     */
    private function getAllApiKeys(): array
    {
        if (null !== $this->apiKeys) {
            return $this->apiKeys;
        }
        $this->apiKeys = array_filter(
            array_map(
                callback: fn (StoreInterface $store): ?string => $this->apiKeyProvider->get(
                    scope: $this->currentScopeFactory->create([
                        'scopeObject' => $store,
                    ]),
                ),
                array: $this->storeManager->getStores(false),
            ),
        );

        return $this->apiKeys;
    }

    /**
     * @return array<string, array<string, string[]>> apiKey => <attributeName => entityTypes>
     * @throws NoSuchEntityException
     */
    private function getConflictingAttributeNames(): array
    {
        $apiKeys = $this->getAllApiKeys();

        $conflictingAttributeNames = array_combine(
            keys: $apiKeys,
            values: array_map(
                callback: fn (string $apiKey): array => $this->conflictingAttributeNamesProvider->getForApiKey($apiKey),
                array: $apiKeys,
            ),
        );

        return array_filter($conflictingAttributeNames);
    }

    /**
     * @return void
     */
    private function handleDuplicateAttributeMapping(): void
    {
        $duplicateAttributeMapping = $this->getDuplicateAttributeMapping();
        if (array_filter($duplicateAttributeMapping)) {
            $details = '';
            foreach ($duplicateAttributeMapping as $apiKey => $duplicateAttributesByApiKey) {
                $details .= $apiKey . PHP_EOL;
                foreach ($duplicateAttributesByApiKey as $entityType => $duplicateAttributes) {
                    $details .= $entityType . PHP_EOL;
                    foreach ($duplicateAttributes as $attributeName => $duplicateCount) {
                        $details .= __(
                            'Attribute "%1" mapped %2 time(s)' . PHP_EOL,
                            $attributeName,
                            $duplicateCount,
                        )->render();
                    }
                }
            }

            $this->eventManager->dispatch(
                eventName: 'klevu_notifications_upsertNotification',
                data: [
                    'notification_data' => [
                        'type' => Constants::NOTIFICATION_TYPE_DUPLICATE_ATTRIBUTE_MAPPING,
                        'severity' => MessageInterface::SEVERITY_MAJOR,
                        // Magic number prevents dependency. See \Klevu\Notifications\Model\Notification::STATUS_ERROR
                        'status' => 3,
                        'message' => 'Conflicting Klevu attribute configuration detected: '
                            . 'multiple Magento attributes mapped to same Klevu attribute',
                        'details' => $details,
                        'date' => date('Y-m-d H:i:s'),
                        'delete_after_view' => false,
                    ],
                ] ,
            );
        } else {
            $this->eventManager->dispatch(
                eventName: 'klevu_notifications_deleteNotification',
                data: [
                    'notification_data' => [
                        'type' => Constants::NOTIFICATION_TYPE_DUPLICATE_ATTRIBUTE_MAPPING,
                    ],
                ],
            );
        }
    }

    /**
     * @return array<string, array<string, array<string, int>>> apiKey => <attributeType => <attributeName => count>>
     * @throws NoSuchEntityException
     */
    private function getDuplicateAttributeMapping(): array
    {
        $apiKeys = $this->getAllApiKeys();

        $conflictingAttributeNames = array_combine(
            keys: $apiKeys,
            values: array_map(
                callback: fn (string $apiKey): array => $this->duplicateAttributeMappingProvider->get($apiKey),
                array: $apiKeys,
            ),
        );

        return array_filter($conflictingAttributeNames);
    }
}
