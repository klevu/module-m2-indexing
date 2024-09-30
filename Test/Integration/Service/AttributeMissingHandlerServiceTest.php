<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Configuration\Exception\ApiKeyNotFoundException;
use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Constants;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\AttributesMissingHandlerService;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\AttributesMissingHandlerServiceInterface;
use Klevu\IndexingApi\Service\Provider\Sdk\AttributesProviderInterface;
use Klevu\PhpSDK\Exception\Api\BadRequestException;
use Klevu\PhpSDK\Exception\Api\BadResponseException;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\AttributeIterator;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers AttributesMissingHandlerService::class
 * @method AttributesMissingHandlerServiceInterface instantiateTestObject(?array $arguments = null)
 * @method AttributesMissingHandlerServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeMissingHandlerServiceTest extends TestCase
{
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

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

        $this->implementationFqcn = AttributesMissingHandlerService::class;
        $this->interfaceFqcn = AttributesMissingHandlerServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testExecute_HandlesApiKeyNotFoundException(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $exceptionMessage = 'API Key not found';

        $mockAttributesProvider = $this->getMockBuilder(AttributesProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributesProvider->expects($this->once())
            ->method('get')
            ->willThrowException(new ApiKeyNotFoundException(__($exceptionMessage)));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\AttributesMissingHandlerService::getAttributeCodesFromKlevu',
                    'message' => $exceptionMessage,
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'sdkAttributesProvider' => $mockAttributesProvider,
        ]);
        $service->execute();
    }

    public function testExecute_HandlesBadRequestException(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $exceptionMessage = 'Bad Request';

        $mockAttributesProvider = $this->getMockBuilder(AttributesProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributesProvider->expects($this->once())
            ->method('get')
            ->willThrowException(new BadRequestException(message: $exceptionMessage, code: 400));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\AttributesMissingHandlerService::getAttributeCodesFromKlevu',
                    'message' => $exceptionMessage,
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'sdkAttributesProvider' => $mockAttributesProvider,
        ]);
        $service->execute();
    }

    public function testExecute_HandlesBadResponseException(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $exceptionMessage = 'Bad Response';

        $mockAttributesProvider = $this->getMockBuilder(AttributesProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockAttributesProvider->expects($this->once())
            ->method('get')
            ->willThrowException(new BadResponseException(message: $exceptionMessage, code: 500));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\AttributesMissingHandlerService::getAttributeCodesFromKlevu',
                    'message' => $exceptionMessage,
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'sdkAttributesProvider' => $mockAttributesProvider,
        ]);
        $service->execute();
    }

    public function testExecute_DoesNotDispatchEvent_WhenAllAttributesMapped(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $this->createAttribute([
            'index_as' => IndexType::INDEX,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');
        $attribute = $attributeFixture->getAttribute();
        $this->cleanIndexingAttributes(apiKey: $apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => $attribute->getAttributeId(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::TARGET_CODE => $attribute->getAttributeCode(),
        ]);

        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                'klevu_notifications_deleteNotification',
                [
                    'notification_data' => [
                        'type' => Constants::NOTIFICATION_TYPE_MISSING_ATTRIBUTES,
                    ],
                ],
            );

        $mockSdkAttributesProvider = $this->getMockBuilder(AttributesProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockSdkAttributesProvider->expects($this->once())
            ->method('get')
            ->willReturn(
                new AttributeIterator([
                    new Attribute(
                        attributeName: $attribute->getAttributeCode(),
                        datatype: DataType::STRING->value,
                        label: [
                            'default' => $attribute->getDefaultFrontendLabel(),
                        ],
                        searchable: true,
                        filterable: true,
                        returnable: true,
                        immutable: false,
                    ),
                ]),
            );

        $service = $this->instantiateTestObject([
            'sdkAttributesProvider' => $mockSdkAttributesProvider,
            'eventManager' => $mockEventManager,
        ]);
        $service->execute();
    }

    public function testExecute_DispatchesEvent_WhenAttributeMappingRequired(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-key',
        );

        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $mockEventManager = $this->getMockBuilder(ManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                'klevu_notifications_upsertNotification',
                [
                    'notification_data' => [
                        'type' => Constants::NOTIFICATION_TYPE_MISSING_ATTRIBUTES,
                        'severity' => MessageInterface::SEVERITY_NOTICE,
                        'status' => 4,
                        'message' => 'Attributes exist in Klevu, but are not set to be indexable in Magento: ',
                        'details' => 'API Key: "klevu-js-api-key"' . PHP_EOL
                            . 'Attribute Codes: some_attribute_only_in_klevu' . PHP_EOL,
                        'date' => date('Y-m-d H:i:s'),
                        'delete_after_view' => false,
                    ],
                ],
            );

        $mockSdkAttributesProvider = $this->getMockBuilder(AttributesProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockSdkAttributesProvider->expects($this->once())
            ->method('get')
            ->willReturn(
                new AttributeIterator([
                    new Attribute(
                        attributeName: 'some_attribute_only_in_klevu',
                        datatype: DataType::STRING->value,
                        label: [
                            'default' => 'Some Attribute Only In Klevu',
                        ],
                        searchable: true,
                        filterable: true,
                        returnable: true,
                        immutable: false,
                    ),
                ]),
            );

        $service = $this->instantiateTestObject([
            'sdkAttributesProvider' => $mockSdkAttributesProvider,
            'eventManager' => $mockEventManager,
        ]);
        $service->execute();
    }
}
