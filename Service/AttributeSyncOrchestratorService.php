<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Exception\InvalidAccountCredentialsException;
use Klevu\Indexing\Exception\InvalidAttributeIndexerServiceException;
use Klevu\IndexingApi\Api\Data\SyncResultInterface;
use Klevu\IndexingApi\Service\AttributeIndexerServiceInterface;
use Klevu\IndexingApi\Service\AttributeSyncOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\Provider\AccountCredentialsProviderInterface;
use Klevu\PhpSDK\Model\AccountCredentials;
use Psr\Log\LoggerInterface;

class AttributeSyncOrchestratorService implements AttributeSyncOrchestratorServiceInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var AccountCredentialsProviderInterface
     */
    private readonly AccountCredentialsProviderInterface $accountCredentialsProvider;
    /**
     * @var AttributeIndexerServiceInterface[]
     */
    private array $attributesIndexerServices;

    /**
     * @param LoggerInterface $logger
     * @param AccountCredentialsProviderInterface $accountCredentialsProvider
     * @param AttributeIndexerServiceInterface[][] $attributesIndexerServices
     *
     */
    public function __construct(
        LoggerInterface $logger,
        AccountCredentialsProviderInterface $accountCredentialsProvider,
        array $attributesIndexerServices,
    ) {
        $this->logger = $logger;
        $this->accountCredentialsProvider = $accountCredentialsProvider;
        array_walk($attributesIndexerServices, [$this, 'setIndexerServices']);
    }

    /**
     * @param string[] $attributeTypes
     * @param string[] $apiKeys
     *
     * @return SyncResultInterface[][][]
     */
    public function execute(array $attributeTypes = [], array $apiKeys = []): array
    {
        $return = [];
        $attributesIndexerServices = $this->getIndexerServices(attributeTypes: $attributeTypes);
        foreach ($this->getCredentialsArray(apiKeys: $apiKeys) as $accountCredentials) {
            try {
                foreach ($attributesIndexerServices as $action => $attributesIndexerService) {
                    $response = $attributesIndexerService->execute(
                        accountCredentials: $accountCredentials,
                        attributeType: $this->getAttributeTypeFromAction(action: $action),
                    );
                    if ($response) {
                        $return[$accountCredentials->jsApiKey][$action] = $response;
                    }
                }
            } catch (InvalidAccountCredentialsException $exception) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }

        return $return;
    }

    /**
     * Expected format for attributesIndexerServices, injected vi di.xml
     * <argument name="attributesIndexerServices" xsi:type="array">
     *   <item name="KLEVU_PRODUCT" xsi:type="array">
     *     <item name="add" xsi:type="object">Klevu\IndexingProducts\Service\AttributeIndexerService\Add</item>
     *   </item>
     * </argument>
     *
     * @param AttributeIndexerServiceInterface[] $indexerServices
     * @param string $attributeType
     *
     * @return void
     * @throws InvalidAttributeIndexerServiceException
     */
    private function setIndexerServices(array $indexerServices, string $attributeType): void
    {
        foreach ($indexerServices as $key => $indexerService) {
            if (!($indexerService instanceof AttributeIndexerServiceInterface)) {
                throw new InvalidAttributeIndexerServiceException(
                    __(
                        'Invalid Indexer Service: Expected instance of %s, received %s',
                        AttributeIndexerServiceInterface::class,
                        get_debug_type($indexerService),
                    ),
                );
            }
            $this->attributesIndexerServices[$attributeType . '::' . $key] = $indexerService;
        }
    }

    /**
     * @param string[] $attributeTypes
     *
     * @return AttributeIndexerServiceInterface[]
     */
    private function getIndexerServices(array $attributeTypes): array
    {
        return $attributeTypes
            ? array_filter(
                array: $this->attributesIndexerServices,
                callback: static function (string $key) use ($attributeTypes): bool {
                    foreach ($attributeTypes as $attributeType){
                        if (str_starts_with(haystack: $key, needle: $attributeType . '::')) {
                            return true;
                        }
                    }
                    return false;
                },
                mode: ARRAY_FILTER_USE_KEY,
            )
            : $this->attributesIndexerServices;
    }

    /**
     * @param string[] $apiKeys
     *
     * @return AccountCredentials[]
     */
    private function getCredentialsArray(array $apiKeys): array
    {
        $accountCredentialsArray = $this->getAccountCredentials(apiKeys: $apiKeys);
        if ($apiKeys && !$accountCredentialsArray) {
            $this->logger->warning(
                message: 'Method: {method}, Warning: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => __(
                        'No Account found for provided API Keys. Check the JS API Keys (%1) provided.',
                        implode(', ', $apiKeys),
                    )->render(),
                ],
            );
        }

        return $accountCredentialsArray;
    }

    /**
     * @param string[] $apiKeys
     *
     * @return AccountCredentials[]
     */
    private function getAccountCredentials(array $apiKeys): array
    {
        $accountCredentials = $this->accountCredentialsProvider->get();
        if ($apiKeys) {
            $accountCredentials = array_filter(
                array: $accountCredentials,
                callback: static fn (string $apiKey): bool => (
                    in_array(needle: $apiKey, haystack: $apiKeys, strict: true)
                ),
                mode: ARRAY_FILTER_USE_KEY,
            );
        }

        return $accountCredentials;
    }

    /**
     * @param string $action
     *
     * @return string
     */
    private function getAttributeTypeFromAction(string $action): string
    {
        $actionArray = explode(separator: '::', string: $action);

        return array_shift(array: $actionArray);
    }
}
