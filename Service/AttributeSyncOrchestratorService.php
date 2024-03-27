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
     * @throws InvalidAttributeIndexerServiceException
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
     * @param string|null $attributeType
     * @param string|null $apiKey
     *
     * @return SyncResultInterface[][][]
     */
    public function execute(?string $attributeType = null, ?string $apiKey = null): array
    {
        $return = [];
        $attributesIndexerServices = $this->getIndexerServices(attributeType: $attributeType);
        foreach ($this->getAccountCredentials(apiKey: $apiKey) as $accountCredentials) {
            try {
                foreach ($attributesIndexerServices as $action => $attributesIndexerService) {
                    $response = $attributesIndexerService->execute(
                        accountCredentials: $accountCredentials,
                        attributeType: $this->getAttributeTypeFromAction($action),
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
     * @param string|null $attributeType
     *
     * @return AttributeIndexerServiceInterface[]
     */
    private function getIndexerServices(?string $attributeType): array
    {
        return $attributeType
            ? array_filter(
                array: $this->attributesIndexerServices,
                callback: static fn (string $key): bool => str_starts_with(
                    haystack: $key,
                    needle: $attributeType . '::',
                ),
                mode: ARRAY_FILTER_USE_KEY,
            )
            : $this->attributesIndexerServices;
    }

    /**
     * @param string|null $apiKey
     *
     * @return AccountCredentials[]
     */
    private function getAccountCredentials(?string $apiKey): array
    {
        $accountCredentials = $this->accountCredentialsProvider->get();
        if ($apiKey) {
            $credentials = $accountCredentials[$apiKey] ?? null;
            $accountCredentials = $credentials
                ? [$credentials]
                : [];
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
        $actionArray = explode('::', $action);

        return array_shift($actionArray);
    }
}
