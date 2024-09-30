<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Configuration\Exception\ApiKeyNotFoundException;
use Klevu\Configuration\Model\CurrentScopeFactory;
use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\Indexing\Constants;
use Klevu\Indexing\Exception\StoreApiKeyException;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Service\AttributesMissingHandlerServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingAttributeProviderInterface;
use Klevu\IndexingApi\Service\Provider\MagentoToKlevuAttributeMapperProviderInterface;
use Klevu\IndexingApi\Service\Provider\Sdk\AttributesProviderInterface as SdkAttributesProviderInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface;
use Klevu\PhpSDK\Exception\ApiExceptionInterface;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Notification\MessageInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class AttributesMissingHandlerService implements AttributesMissingHandlerServiceInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var SdkAttributesProviderInterface
     */
    private readonly SdkAttributesProviderInterface $sdkAttributesProvider;
    /**
     * @var EventManagerInterface|EventManager
     */
    private readonly EventManagerInterface $eventManager;
    /**
     * @var IndexingAttributeProviderInterface
     */
    private readonly IndexingAttributeProviderInterface $indexingAttributeProvider;
    /**
     * @var MagentoToKlevuAttributeMapperProviderInterface
     */
    private readonly MagentoToKlevuAttributeMapperProviderInterface $magentoToKlevuAttributeMapperProvider;
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
     * @var string[]|null
     */
    private ?array $apiKeys = null;

    /**
     * @param LoggerInterface $logger
     * @param SdkAttributesProviderInterface $sdkAttributesProvider
     * @param EventManagerInterface $eventManager
     * @param IndexingAttributeProviderInterface $indexingAttributeProvider
     * @param MagentoToKlevuAttributeMapperProviderInterface $magentoToKlevuAttributeMapperProvider
     * @param StoreManagerInterface $storeManager
     * @param CurrentScopeFactory $currentScopeFactory
     * @param ApiKeyProviderInterface $apiKeyProvider
     */
    public function __construct(
        LoggerInterface $logger,
        SdkAttributesProviderInterface $sdkAttributesProvider,
        EventManagerInterface $eventManager,
        IndexingAttributeProviderInterface $indexingAttributeProvider,
        MagentoToKlevuAttributeMapperProviderInterface $magentoToKlevuAttributeMapperProvider,
        StoreManagerInterface $storeManager,
        CurrentScopeFactory $currentScopeFactory,
        ApiKeyProviderInterface $apiKeyProvider,
    ) {
        $this->logger = $logger;
        $this->sdkAttributesProvider = $sdkAttributesProvider;
        $this->eventManager = $eventManager;
        $this->indexingAttributeProvider = $indexingAttributeProvider;
        $this->magentoToKlevuAttributeMapperProvider = $magentoToKlevuAttributeMapperProvider;
        $this->storeManager = $storeManager;
        $this->currentScopeFactory = $currentScopeFactory;
        $this->apiKeyProvider = $apiKeyProvider;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $missingAttributeCodes = [];
        $details = '';

        foreach ($this->getAllApiKeys() as $apiKey) {
            $klevuAttributeCodes = $this->getAttributeCodesFromKlevu(apiKey: $apiKey);
            if (!$klevuAttributeCodes) {
                continue;
            }
            $magentoAttributeCodes = $this->getAttributeCodesFromMagento(apiKey: $apiKey);
            $diff = array_diff($klevuAttributeCodes, $magentoAttributeCodes);
            if ($diff) {
                $details .= __('API Key: "%1"' . PHP_EOL, $apiKey)->render();
                $details .= __(
                    'Attribute Codes: %1' . PHP_EOL,
                    implode(', ', $diff),
                )->render();
                $missingAttributeCodes[] = $diff;
            }
        }

        $missingAttributeCodes = array_merge(...$missingAttributeCodes);
        if ($missingAttributeCodes) {
            $this->addNotification(details: $details);
        } else {
            $this->removeNotification();
        }
    }

    /**
     * @param string $apiKey
     *
     * @return string[]
     */
    private function getAttributeCodesFromKlevu(string $apiKey): array
    {
        $attributeCodes = [];
        try {
            $attributesIterator = $this->sdkAttributesProvider->get(apiKey: $apiKey);
            $mutableAttributes = array_filter(
                array: $attributesIterator->toArray(),
                callback: static fn (AttributeInterface $attribute): bool => !$attribute->isImmutable(),
            );
            foreach ($this->magentoToKlevuAttributeMapperProvider->get() as $magentoToKlevuAttributeMapper) {
                $attributeCodes[] = array_map(
                    callback: static fn (AttributeInterface $attribute): string => (
                    $magentoToKlevuAttributeMapper->reverseForCode(
                        attributeName: $attribute->getAttributeName(),
                        apiKey: $apiKey,
                    )
                    ),
                    array: $mutableAttributes,
                );
            }
        } catch (ApiKeyNotFoundException | ApiExceptionInterface | StoreApiKeyException $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return array_unique(
            array_merge(...$attributeCodes),
        );
    }

    /**
     * @param string $apiKey
     *
     * @return string[]
     */
    private function getAttributeCodesFromMagento(string $apiKey): array
    {
        $magentoAttributes = $this->indexingAttributeProvider->get(
            apiKey: $apiKey,
            isIndexable: true,
        );

        return array_values(
            array_map(
                callback: static fn (IndexingAttributeInterface $attribute): string => (
                    $attribute->getTargetCode()
                ),
                array: $magentoAttributes,
            ),
        );
    }

    /**
     * @param string $details
     *
     * @return void
     */
    private function addNotification(string $details): void
    {
        $this->eventManager->dispatch(
            eventName: 'klevu_notifications_upsertNotification',
            data: [
                'notification_data' => [
                    'type' => Constants::NOTIFICATION_TYPE_MISSING_ATTRIBUTES,
                    'severity' => MessageInterface::SEVERITY_NOTICE,
                    // Magic number prevents dependency. See \Klevu\Notifications\Model\Notification::STATUS_ERROR
                    'status' => 4,
                    'message' => 'Attributes exist in Klevu, but are not set to be indexable in Magento: ',
                    'details' => $details,
                    'date' => date('Y-m-d H:i:s'),
                    'delete_after_view' => false,
                ],
            ],
        );
    }

    /**
     * @return void
     */
    private function removeNotification(): void
    {
        $this->eventManager->dispatch(
            eventName: 'klevu_notifications_deleteNotification',
            data: [
                'notification_data' => [
                    'type' => Constants::NOTIFICATION_TYPE_MISSING_ATTRIBUTES,
                ],
            ],
        );
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
}
