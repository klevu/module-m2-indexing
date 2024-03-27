<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action\Sdk\Attribute;

use Klevu\Indexing\Exception\InvalidAccountCredentialsException;
use Klevu\IndexingApi\Api\Data\SyncResultInterface;
use Klevu\IndexingApi\Api\Data\SyncResultInterfaceFactory;
use Klevu\IndexingApi\Service\Action\Sdk\Attribute\ActionInterface;
use Klevu\PhpSDK\Api\Model\ApiResponseInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface as SdkAttributeInterface;
use Klevu\PhpSDK\Api\Service\Indexing\AttributesServiceInterface;
use Klevu\PhpSDK\Exception\Api\BadRequestException;
use Klevu\PhpSDK\Exception\Api\BadResponseException;
use Klevu\PhpSDK\Exception\ValidationException;
use Klevu\PhpSDK\Model\AccountCredentials;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Psr\Log\LoggerInterface;

class DeleteAction implements ActionInterface
{
    public const KLEVU_INDEXING_ATTRIBUTES_ACTION_DELETE_AFTER = 'klevu_indexing_attributes_action_delete_after';

    /**
     * @var AttributesServiceInterface
     */
    private readonly AttributesServiceInterface $attributesService;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var SyncResultInterfaceFactory
     */
    private readonly SyncResultInterfaceFactory $syncResultInterfaceFactory;
    /**
     * @var EventManagerInterface
     */
    private readonly EventManagerInterface $eventManager;

    /**
     * @param AttributesServiceInterface $attributesService
     * @param LoggerInterface $logger
     * @param SyncResultInterfaceFactory $syncResultInterfaceFactory
     * @param EventManagerInterface $eventManager
     */
    public function __construct(
        AttributesServiceInterface $attributesService,
        LoggerInterface $logger,
        SyncResultInterfaceFactory $syncResultInterfaceFactory,
        EventManagerInterface $eventManager,
    ) {
        $this->attributesService = $attributesService;
        $this->logger = $logger;
        $this->syncResultInterfaceFactory = $syncResultInterfaceFactory;
        $this->eventManager = $eventManager;
    }

    /**
     * @param AccountCredentials $accountCredentials
     * @param SdkAttributeInterface $attribute
     * @param string $attributeType
     *
     * @return SyncResultInterface
     * @throws InvalidAccountCredentialsException
     */
    public function execute(
        AccountCredentials $accountCredentials,
        SdkAttributeInterface $attribute,
        string $attributeType,
    ): SyncResultInterface {
        $response = $this->deleteAttributeViaSdk(
            accountCredentials: $accountCredentials,
            attribute: $attribute,
        );
        $this->handleResponse(
            response: $response,
            attribute: $attribute,
            accountCredentials: $accountCredentials,
            attributeType: $attributeType,
        );

        return $response;
    }

    /**
     * @param AccountCredentials $accountCredentials
     * @param SdkAttributeInterface $attribute
     *
     * @return SyncResultInterface
     * @throws InvalidAccountCredentialsException
     */
    private function deleteAttributeViaSdk(
        AccountCredentials $accountCredentials,
        SdkAttributeInterface $attribute,
    ): SyncResultInterface {
        try {
            $response = $this->attributesService->delete(
                accountCredentials: $accountCredentials,
                attribute: $attribute,
            );
        } catch (ValidationException) {
            throw new InvalidAccountCredentialsException(
                phrase: __(
                    'Invalid account credentials provided. '
                    . 'Check the JS API Key (%1) and Rest Auth Key (%2).',
                    $accountCredentials->jsApiKey,
                    $accountCredentials->restAuthKey,
                ),
            );
        } catch (BadRequestException | BadResponseException $exception) {
            return $this->createSyncResultFromException(exception: $exception);
        }

        return $this->createSyncResult(response: $response);
    }

    /**
     * @param ApiResponseInterface $response
     *
     * @return SyncResultInterface
     */
    private function createSyncResult(ApiResponseInterface $response): SyncResultInterface
    {
        return $this->syncResultInterfaceFactory->create(data: [
            'isSuccess' => $response->isSuccess(),
            'code' => (int)$response->getResponseCode(),
            'messages' => $response->getMessages(),
        ]);
    }

    /**
     * @param \Exception $exception
     *
     * @return SyncResultInterface
     */
    private function createSyncResultFromException(\Exception $exception): SyncResultInterface
    {
        return $this->syncResultInterfaceFactory->create(data: [
            'isSuccess' => false,
            'code' => (int)$exception->getCode(),
            'messages' => [$exception->getMessage()],
        ]);
    }

    /**
     * @param SyncResultInterface $response
     * @param SdkAttributeInterface $attribute
     * @param AccountCredentials $accountCredentials
     * @param string $attributeType
     *
     * @return void
     */
    private function handleResponse(
        SyncResultInterface $response,
        SdkAttributeInterface $attribute,
        AccountCredentials $accountCredentials,
        string $attributeType,
    ): void {
        if ($response->isSuccess()) {
            $this->dispatchEvent(
                attribute: $attribute,
                accountCredentials: $accountCredentials,
                attributeType: $attributeType,
            );

            return;
        }
        $this->logFailure(response: $response, attribute: $attribute);
    }

    /**
     * @param SdkAttributeInterface $attribute
     * @param AccountCredentials $accountCredentials
     * @param string $attributeType
     *
     * @return void
     */
    private function dispatchEvent(
        SdkAttributeInterface $attribute,
        AccountCredentials $accountCredentials,
        string $attributeType,
    ): void {
        $this->eventManager->dispatch(
            static::KLEVU_INDEXING_ATTRIBUTES_ACTION_DELETE_AFTER,
            [
                'attribute_name' => $attribute->getAttributeName(),
                'api_key' => $accountCredentials->jsApiKey,
                'attribute_type' => $attributeType,
            ],
        );
    }

    /**
     * @param SyncResultInterface $response
     * @param SdkAttributeInterface $attribute
     *
     * @return void
     */
    private function logFailure(
        SyncResultInterface $response,
        SdkAttributeInterface $attribute,
    ): void {
        $this->logger->error(
            message: 'Method: {method}, Error: {message}',
            context: [
                'method' => __METHOD__,
                'message' => sprintf(
                    '%s: Attribute %s failed to delete from Klevu: %s',
                    $response->getCode(),
                    $attribute->getAttributeName(),
                    implode($response->getMessages()),
                ),
            ],
        );
    }
}
