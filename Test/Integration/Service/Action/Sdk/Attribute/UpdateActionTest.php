<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action\Sdk\Attribute;

use Klevu\Indexing\Exception\InvalidAccountCredentialsException;
use Klevu\Indexing\Service\Action\Sdk\Attribute\UpdateAction;
use Klevu\IndexingApi\Service\Action\Sdk\Attribute\ActionInterface;
use Klevu\PhpSDK\Api\Model\ApiResponseInterface;
use Klevu\PhpSDK\Api\Service\Indexing\AttributesServiceInterface;
use Klevu\PhpSDK\Exception\Api\BadRequestException;
use Klevu\PhpSDK\Model\AccountCredentials;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers UpdateAction
 * @method ActionInterface instantiateTestObject(?array $arguments = null)
 * @method ActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class UpdateActionTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = UpdateAction::class;
        $this->interfaceFqcn = ActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_ThrowsInvalidAccountCredentialsException_WhenInvalidAccountCredentialProvided(): void
    {
        /** @var AccountCredentials $accountCredentials */
        $accountCredentials = $this->objectManager->create(AccountCredentials::class, [
            'jsApiKey' => 'klevu-js-api-key',
            'restAuthKey' => 'klevu-rest-auth-key',
        ]);
        $attribute = $this->objectManager->create(Attribute::class, [
            'attributeName' => 'klevu_test_attribute',
            'datatype' => DataType::STRING->value,
        ]);

        $this->expectException(InvalidAccountCredentialsException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid account credentials provided. '
                . 'Check the JS API Key (%s) and Rest Auth Key (%s).',
                $accountCredentials->jsApiKey,
                $accountCredentials->restAuthKey,
            ),
        );

        $action = $this->instantiateTestObject();
        $action->execute($accountCredentials, $attribute, 'KLEVU_PRODUCT');
    }

    public function testExecute_LogsErrors_WhenAccountServicePutFails(): void
    {
        /** @var AccountCredentials $accountCredentials */
        $accountCredentials = $this->objectManager->create(AccountCredentials::class, [
            'jsApiKey' => 'klevu-js-api-key',
            'restAuthKey' => 'klevu-rest-auth-key',
        ]);
        $attribute = $this->objectManager->create(Attribute::class, [
            'attributeName' => 'klevu_test_attribute',
            'datatype' => DataType::STRING->value,
        ]);

        $mockSdkResponse = $this->getMockBuilder(ApiResponseInterface::class)
            ->getMock();
        $mockSdkResponse->expects($this->once())
            ->method('isSuccess')
            ->willReturn(false);
        $mockSdkResponse->expects($this->once())
            ->method('getResponseCode')
            ->willReturn(401);
        $mockSdkResponse->expects($this->once())
            ->method('getMessages')
            ->willReturn(['Something went wrong.']);

        $mockSdkAttributeService = $this->getMockBuilder(AttributesServiceInterface::class)
            ->getMock();
        $mockSdkAttributeService->expects($this->once())
            ->method('put')
            ->with($accountCredentials, $attribute)
            ->willReturn($mockSdkResponse);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\Action\Sdk\Attribute\UpdateAction::logFailure',
                    'message' => '401: Attribute klevu_test_attribute failed to sync with Klevu: '
                        . 'Something went wrong.',
                ],
            );

        $mockEventManager = $this->getMockBuilder(EventManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->never())
            ->method('dispatch');

        $action = $this->instantiateTestObject([
            'attributesService' => $mockSdkAttributeService,
            'logger' => $mockLogger,
            'eventManager' => $mockEventManager,
        ]);
        $result = $action->execute($accountCredentials, $attribute, 'KLEVU_PRODUCT');

        $this->assertFalse($result->isSuccess());
        $this->assertContains(needle: 'Something went wrong.', haystack: $result->getMessages());
    }

    public function testExecute_WhenAccountServicePutSucceeds(): void
    {
        /** @var AccountCredentials $accountCredentials */
        $accountCredentials = $this->objectManager->create(AccountCredentials::class, [
            'jsApiKey' => 'klevu-js-api-key',
            'restAuthKey' => 'klevu-rest-auth-key',
        ]);
        $attribute = $this->objectManager->create(Attribute::class, [
            'attributeName' => 'klevu_test_attribute',
            'datatype' => DataType::STRING->value,
        ]);

        $mockSdkResponse = $this->getMockBuilder(ApiResponseInterface::class)
            ->getMock();
        $mockSdkResponse->expects($this->once())
            ->method('isSuccess')
            ->willReturn(true);
        $mockSdkResponse->expects($this->once())
            ->method('getResponseCode')
            ->willReturn(200);
        $mockSdkResponse->expects($this->once())
            ->method('getMessages')
            ->willReturn([]);

        $mockSdkAttributeService = $this->getMockBuilder(AttributesServiceInterface::class)
            ->getMock();
        $mockSdkAttributeService->expects($this->once())
            ->method('put')
            ->with($accountCredentials, $attribute)
            ->willReturn($mockSdkResponse);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->never())
            ->method('error');

        $mockEventManager = $this->getMockBuilder(EventManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                UpdateAction::KLEVU_INDEXING_ATTRIBUTES_ACTION_UPDATE_AFTER,
                [
                    'attribute_name' => 'klevu_test_attribute',
                    'api_key' => 'klevu-js-api-key',
                    'attribute_type' => 'KLEVU_PRODUCT',
                ],
            );

        $action = $this->instantiateTestObject([
            'attributesService' => $mockSdkAttributeService,
            'logger' => $mockLogger,
            'eventManager' => $mockEventManager,
        ]);
        $result = $action->execute($accountCredentials, $attribute, 'KLEVU_PRODUCT');

        $this->assertTrue($result->isSuccess());
        $this->assertEmpty(actual: $result->getMessages());
    }

    public function testExecute_LogsError_WhenBadRequestExceptionThrown(): void
    {
        /** @var AccountCredentials $accountCredentials */
        $accountCredentials = $this->objectManager->create(AccountCredentials::class, [
            'jsApiKey' => 'klevu-js-api-key',
            'restAuthKey' => 'klevu-rest-auth-key',
        ]);
        $attribute = $this->objectManager->create(Attribute::class, [
            'attributeName' => 'klevu_test_attribute',
            'datatype' => DataType::NUMBER->value,
        ]);

        $mockSdkAttributeService = $this->getMockBuilder(AttributesServiceInterface::class)
            ->getMock();
        $mockSdkAttributeService->expects($this->once())
            ->method('put')
            ->with($accountCredentials, $attribute)
            ->willThrowException(
                new BadRequestException(
                    message: "API request rejected by Klevu API [400] "
                    . "Validation error in field 'datatype': 'NUMBER' is not a valid value",
                    code: 400,
                ),
            );

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\Action\Sdk\Attribute\UpdateAction::logFailure',
                    'message' => "400: Attribute klevu_test_attribute failed to sync with Klevu: "
                        . "API request rejected by Klevu API "
                        . "[400] Validation error in field 'datatype': 'NUMBER' is not a valid value",
                ],
            );

        $mockEventManager = $this->getMockBuilder(EventManagerInterface::class)
            ->getMock();
        $mockEventManager->expects($this->never())
            ->method('dispatch');

        $action = $this->instantiateTestObject([
            'attributesService' => $mockSdkAttributeService,
            'logger' => $mockLogger,
            'eventManager' => $mockEventManager,
        ]);
        $result = $action->execute($accountCredentials, $attribute, 'KLEVU_PRODUCT');

        $this->assertFalse($result->isSuccess());
        $this->assertContains(
            needle: "API request rejected by Klevu API [400] "
            . "Validation error in field 'datatype': 'NUMBER' is not a valid value",
            haystack: $result->getMessages(),
        );
    }
}
