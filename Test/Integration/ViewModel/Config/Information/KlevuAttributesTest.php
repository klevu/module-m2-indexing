<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\ViewModel\Config\Information;

use Klevu\Configuration\Exception\ApiKeyNotFoundException;
use Klevu\Configuration\Service\Provider\ApiKeyProvider;
use Klevu\Configuration\Service\Provider\AuthKeyProvider;
use Klevu\Indexing\ViewModel\Config\Information\KlevuAttributes;
use Klevu\IndexingApi\Service\Provider\Sdk\AttributesProviderInterface;
use Klevu\IndexingApi\ViewModel\Config\Information\KlevuAttributesInterface;
use Klevu\PhpSDK\Exception\Api\BadRequestException;
use Klevu\PhpSDK\Exception\Api\BadResponseException;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\AttributeIterator;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers KlevuAttributes
 * @method KlevuAttributesInterface instantiateTestObject(?array $arguments = null)
 * @method KlevuAttributesInterface instantiateTestObjectWithMockedDependencies(?array $arguments = null)
 */
class KlevuAttributesTest extends TestCase
{
    use ObjectInstantiationTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = KlevuAttributes::class;
        $this->interfaceFqcn = KlevuAttributesInterface::class;

        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);

        ConfigFixture::setGlobal(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: '',
        );
        ConfigFixture::setGlobal(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: '',
        );
        /** @var StoreManagerInterface $storeManager */
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        foreach ($storeManager->getStores() as $store) {
            ConfigFixture::setForStore(
                path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
                value: '',
                storeCode: $store->getCode(),
            );
            ConfigFixture::setForStore(
                path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
                value: '',
                storeCode: $store->getCode(),
            );
        }
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testGetAttributesByApiKey_ReturnsEmptyArray_WhenNoStoresIntegrated(): void
    {
        $this->createStore();

        $mockLogger = $this->getMockLogger(
            expectedLogLevels: [],
        );
        $mockAttributeProvider = $this->getMockAttributesProvider();
        $mockAttributeProvider->expects($this->never())
            ->method('get');

        $viewModel = $this->instantiateTestObject(
            arguments: [
                'logger' => $mockLogger,
                'attributesProvider' => $mockAttributeProvider,
            ],
        );
        $result = $viewModel->getAttributesByApiKey();

        $this->assertSame(
            expected: [],
            actual: $result,
        );
    }

    /**
     * @return array<array<string, string>>
     */
    public static function dataProvider_testGetAttributesByApiKey_ReturnsEmptyArray_WhenAttributesProviderThrowsException(): array // phpcs:ignore Generic.Files.LineLength.TooLong
    {
        return [
            [
                'exception' => new ApiKeyNotFoundException(
                    phrase: __('API key not found'),
                ),
            ],
            [
                'exception' => new BadRequestException(
                    message: 'API Exception',
                    code: 0,
                ),
            ],
            [
                'exception' => new BadResponseException(
                    message: 'API Exception',
                    code: 0,
                ),
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testGetAttributesByApiKey_ReturnsEmptyArray_WhenAttributesProviderThrowsException
     * @magentoAppIsolation enabled
     */
    public function testGetAttributesByApiKey_ReturnsEmptyArray_WhenAttributesProviderThrowsException(
        \Throwable $exception,
    ): void {
        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'klevu_test_store_1',
        ]);
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: 'klevu-1234567890',
            storeCode: 'klevu_test_store_1',
        );
        ConfigFixture::setForStore(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: 'ABCDE1234567890',
            storeCode: 'klevu_test_store_1',
        );

        $mockLogger = $this->getMockLogger(
            expectedLogLevels: ['error'],
        );
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                message: 'Failed to get attributes for API key: {apiKey}',
                context: [
                    'exception' => $exception,
                    'error' => $exception->getMessage(),
                    'apiKey' => 'klevu-1234567890',
                ],
            );

        $mockAttributeProvider = $this->getMockAttributesProvider();
        $mockAttributeProvider->expects($this->exactly(1))
            ->method('get')
            ->willThrowException(
                exception: $exception,
            );

        $viewModel = $this->instantiateTestObject(
            arguments: [
                'logger' => $mockLogger,
                'attributesProvider' => $mockAttributeProvider,
            ],
        );
        $result = $viewModel->getAttributesByApiKey();

        $this->assertSame(
            expected: [
                'klevu-1234567890' => [],
            ],
            actual: $result,
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @group wipm
     */
    public function testGetAttributesByApiKey_ReturnsResultsOnSuccess(): void
    {
        $this->createStore([
            'code' => 'klevu_test_store_1',
            'key' => 'klevu_test_store_1',
        ]);
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: 'klevu-1234567890',
            storeCode: 'klevu_test_store_1',
        );
        ConfigFixture::setForStore(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: 'ABCDE1234567890',
            storeCode: 'klevu_test_store_1',
        );

        // Some store(s) use same API key
        $this->createStore([
            'code' => 'klevu_test_store_2',
            'key' => 'klevu_test_store_2',
        ]);
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: 'klevu-1234567890',
            storeCode: 'klevu_test_store_2',
        );
        ConfigFixture::setForStore(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: 'ABCDE1234567890',
            storeCode: 'klevu_test_store_2',
        );

        $this->createStore([
            'code' => 'klevu_test_store_3',
            'key' => 'klevu_test_store_3',
        ]);
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: 'klevu-9876543210',
            storeCode: 'klevu_test_store_3',
        );
        ConfigFixture::setForStore(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: 'EDCBA9876543210',
            storeCode: 'klevu_test_store_3',
        );

        // Some stores are not integrated
        $this->createStore([
            'code' => 'klevu_test_store_4',
            'key' => 'klevu_test_store_4',
        ]);

        $this->createStore([
            'code' => 'klevu_test_store_5',
            'key' => 'klevu_test_store_5',
        ]);
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: 'klevu-1122334455',
            storeCode: 'klevu_test_store_5',
        );
        ConfigFixture::setForStore(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: 'ZYXWV1122334455',
            storeCode: 'klevu_test_store_5',
        );

        $attributeFixtures = [
            'additionalDataToReturn' => new Attribute(
                attributeName: 'additionalDataToReturn',
                datatype: DataType::JSON->value,
                label: [
                    'default' => 'Display',
                ],
                searchable: false,
                filterable: false,
                returnable: true,
                immutable: true,
            ),
            'custom_text_attribute' => new Attribute(
                attributeName: 'custom_text_attribute',
                datatype: DataType::STRING->value,
                label: [
                    'default' => 'Custom Text Attribute',
                ],
            ),
            'custom_text_attribute_edited' => new Attribute(
                attributeName: 'custom_text_attribute',
                datatype: DataType::STRING->value,
                label: [
                    'default' => 'Custom Text Attribute (Edited)',
                ],
                searchable: false,
                returnable: false,
            ),
            'rangeable_number' => new Attribute(
                attributeName: 'rangeable_number',
                datatype: DataType::MULTIVALUE_NUMBER->value,
                label: [
                    'default' => 'Rangeable multi-value number',
                ],
                filterable: true,
                rangeable: true,
            ),
        ];
        $exceptionFixture = new BadRequestException(
            message: 'API Exception',
            code: 0,
        );

        $mockLogger = $this->getMockLogger(
            expectedLogLevels: ['error'],
        );
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                message: 'Failed to get attributes for API key: {apiKey}',
                context: [
                    'exception' => $exceptionFixture,
                    'error' => $exceptionFixture->getMessage(),
                    'apiKey' => 'klevu-9876543210',
                ],
            );

        $mockAttributeProvider = $this->getMockAttributesProvider();
        $mockAttributeProvider->expects($this->exactly(3))
            ->method('get')
            ->willReturnCallback(
                callback: function (string $apiKey) use ($attributeFixtures, $exceptionFixture): AttributeIterator {
                    $this->assertContains(
                        needle: $apiKey,
                        haystack: [
                            'klevu-1234567890',
                            'klevu-9876543210',
                            'klevu-1122334455',
                        ],
                    );

                    return match ($apiKey) {
                        'klevu-1234567890' => new AttributeIterator(
                            data: [
                                $attributeFixtures['additionalDataToReturn'],
                                $attributeFixtures['custom_text_attribute'],
                            ],
                        ),
                        // Attribute Provider throws exception for one store
                        'klevu-9876543210' => throw $exceptionFixture,
                        'klevu-1122334455' => new AttributeIterator(
                            data: [
                                $attributeFixtures['custom_text_attribute_edited'],
                                $attributeFixtures['additionalDataToReturn'],
                                $attributeFixtures['rangeable_number'],
                            ],
                        ),
                    };
                },
            );

        $viewModel = $this->instantiateTestObject(
            arguments: [
                'logger' => $mockLogger,
                'attributesProvider' => $mockAttributeProvider,
            ],
        );
        $result = $viewModel->getAttributesByApiKey();

        $this->assertIsArray($result);
        $this->assertCount(
            expectedCount: 3,
            haystack: $result,
        );

        $this->assertArrayHasKey(
            key: 'klevu-1234567890',
            array: $result,
        );
        $this->assertInstanceOf(
            expected: AttributeIterator::class,
            actual: $result['klevu-1234567890'],
        );
        $this->assertCount(
            expectedCount: 2,
            haystack: $result['klevu-1234567890']->toArray(),
        );
        $this->assertContains(
            needle: $attributeFixtures['additionalDataToReturn'],
            haystack: $result['klevu-1234567890']->toArray(),
        );
        $this->assertContains(
            needle: $attributeFixtures['custom_text_attribute'],
            haystack: $result['klevu-1234567890']->toArray(),
        );

        $this->assertArrayHasKey(
            key: 'klevu-9876543210',
            array: $result,
        );
        $this->assertEmpty($result['klevu-9876543210']);

        $this->assertArrayHasKey(
            key: 'klevu-1122334455',
            array: $result,
        );
        $this->assertInstanceOf(
            expected: AttributeIterator::class,
            actual: $result['klevu-1122334455'],
        );
        $this->assertCount(
            expectedCount: 3,
            haystack: $result['klevu-1122334455']->toArray(),
        );
        $this->assertContains(
            needle: $attributeFixtures['additionalDataToReturn'],
            haystack: $result['klevu-1122334455']->toArray(),
        );
        $this->assertContains(
            needle: $attributeFixtures['custom_text_attribute_edited'],
            haystack: $result['klevu-1122334455']->toArray(),
        );
        $this->assertContains(
            needle: $attributeFixtures['rangeable_number'],
            haystack: $result['klevu-1122334455']->toArray(),
        );
    }

    /**
     * @param string[] $expectedLogLevels
     *
     * @return MockObject&LoggerInterface
     */
    private function getMockLogger(array $expectedLogLevels = []): MockObject
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $notExpectedLogLevels = array_diff(
            [
                'emergency',
                'alert',
                'critical',
                'error',
                'warning',
                'notice',
                'info',
                'debug',
            ],
            $expectedLogLevels,
        );
        foreach ($notExpectedLogLevels as $notExpectedLogLevel) {
            $mockLogger->expects($this->never())
                ->method($notExpectedLogLevel);
        }

        return $mockLogger;
    }

    /**
     * @return MockObject&AttributesProviderInterface
     */
    private function getMockAttributesProvider(): MockObject {
        return $this->getMockBuilder(AttributesProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
