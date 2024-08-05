<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ApiKeyProvider;
use Klevu\Configuration\Service\Provider\AuthKeyProvider;
use Klevu\Indexing\Constants;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\AttributeConflictHandlerService;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\AttributeConflictHandlerServiceInterface;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingApi\Service\Provider\DuplicateAttributeMappingProviderInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers AttributeConflictHandlerService::class
 * @method AttributeConflictHandlerServiceInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeConflictHandlerServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeConflictHandlerServiceTest extends TestCase
{
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

    private const FIXTURE_API_KEY = 'klevu-1234567890';

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var array<string, MagentoToKlevuAttributeMapperInterface>
     */
    private ?array $mockAttributeMappers = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = AttributeConflictHandlerService::class;
        $this->interfaceFqcn = AttributeConflictHandlerServiceInterface::class;

        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->mockAttributeMappers = [];
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
     * @magentoDbIsolation enabled
     */
    public function testExecute_NoConflicting_NoDuplicate(): void
    {
        $this->initStoreForTest();

        $eventManager = $this->getMockEventManagerWithEvents(
            conflictingAttributes: false,
            duplicateAttributes: false,
        );
        $attributeConflictHandlerService = $this->instantiateTestObject([
            'eventManager' => $eventManager,
            'attributeMappers' => $this->mockAttributeMappers,
        ]);
        $attributeConflictHandlerService->execute();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testExecute_NoConflicting_Duplicate(): void
    {
        $this->initStoreForTest();

        $this->createDuplicateAttributeFixtures();
        $eventManager = $this->getMockEventManagerWithEvents(
            conflictingAttributes: false,
            duplicateAttributes: true,
        );

        $duplicateAttributeMappingProvider = $this->objectManager->create(
            type: DuplicateAttributeMappingProviderInterface::class,
            arguments: [
                'attributeMappers' => $this->mockAttributeMappers,
            ],
        );
        $attributeConflictHandlerService = $this->instantiateTestObject([
            'eventManager' => $eventManager,
            'duplicateAttributeMappingProvider' => $duplicateAttributeMappingProvider,
        ]);
        $attributeConflictHandlerService->execute();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testExecute_Conflicting_NoDuplicate(): void
    {
        $this->initStoreForTest();

        $this->createConflictingIndexingAttributeFixtures();
        $eventManager = $this->getMockEventManagerWithEvents(
            conflictingAttributes: true,
            duplicateAttributes: false,
        );

        $attributeConflictHandlerService = $this->instantiateTestObject([
            'eventManager' => $eventManager,
        ]);
        $attributeConflictHandlerService->execute();
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testExecute_Conflicting_Duplicate(): void
    {
        $this->initStoreForTest();

        $this->createConflictingIndexingAttributeFixtures();
        $this->createDuplicateAttributeFixtures();
        $eventManager = $this->getMockEventManagerWithEvents(
            conflictingAttributes: true,
            duplicateAttributes: true,
        );

        $duplicateAttributeMappingProvider = $this->objectManager->create(
            type: DuplicateAttributeMappingProviderInterface::class,
            arguments: [
                'attributeMappers' => $this->mockAttributeMappers,
            ],
        );
        $attributeConflictHandlerService = $this->instantiateTestObject([
            'eventManager' => $eventManager,
            'duplicateAttributeMappingProvider' => $duplicateAttributeMappingProvider,
        ]);
        $attributeConflictHandlerService->execute();
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function initStoreForTest(): void
    {
        $this->createStore([
            'code' => 'klevu_indexing_test_store_1',
            'key' => 'klevu_indexing_test_store_1',
        ]);
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: self::FIXTURE_API_KEY,
            storeCode: 'klevu_indexing_test_store_1',
        );
        ConfigFixture::setForStore(
            path: AuthKeyProvider::CONFIG_XML_PATH_REST_AUTH_KEY,
            value: 'ABCDE1234567890',
            storeCode: 'klevu_indexing_test_store_1',
        );
    }

    /**
     * @return void
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createConflictingIndexingAttributeFixtures(): void
    {
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => self::FIXTURE_API_KEY,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'cat__klevu_test_attribute_1',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => self::FIXTURE_API_KEY,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
    }

    /**
     * @return void
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createDuplicateAttributeFixtures(): void
    {
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => self::FIXTURE_API_KEY,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => self::FIXTURE_API_KEY,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $mockAttributeMapperProduct = $this->getMockBuilder(MagentoToKlevuAttributeMapperInterface::class)
            ->getMock();
        $mockAttributeMapperProduct->method('getByCode')
            ->willReturnCallback(
                static fn (string $attributeCode): string => match ($attributeCode) {
                    'klevu_test_attribute_2' => 'description',
                    default => $attributeCode,
                },
            );

        $mockAttributeMapperCategory = $this->getMockBuilder(MagentoToKlevuAttributeMapperInterface::class)
            ->getMock();
        $mockAttributeMapperCategory->method('getByCode')
            ->willReturnCallback(
                static fn (string $attributeCode): string => match ($attributeCode) {
                    'klevu_test_attribute_2' => 'name',
                    default => $attributeCode,
                },
            );

        $this->mockAttributeMappers['KLEVU_PRODUCT'] = $mockAttributeMapperProduct;
        $this->mockAttributeMappers['KLEVU_CATEGORY'] = $mockAttributeMapperCategory;
    }

    /**
     * @param bool $conflictingAttributes
     * @param bool $duplicateAttributes
     *
     * @return MockObject&EventManagerInterface
     */
    private function getMockEventManagerWithEvents(
        bool $conflictingAttributes,
        bool $duplicateAttributes,
    ): MockObject {
        $mockEventManager = $this->getMockBuilder(EventManagerInterface::class)
            ->getMock();

        $expectedEvents = [
            1 => $conflictingAttributes
                ? 'klevu_notifications_upsertNotification'
                : 'klevu_notifications_deleteNotification',
            2 => $duplicateAttributes
                ? 'klevu_notifications_upsertNotification'
                : 'klevu_notifications_deleteNotification',
        ];

        $expectedData = [
            1 => $conflictingAttributes
                ? [
                    'notification_data' => [
                        'type' => Constants::NOTIFICATION_TYPE_CONFLICTING_ATTRIBUTE_NAMES,
                        'severity' => MessageInterface::SEVERITY_MAJOR,
                        'status' => 3,
                        'message' => 'Conflicting Klevu attribute configuration detected: '
                            . 'multiple attributes map to same attribute name',
                        'details' => 'API Key "klevu-1234567890"
Attribute "cat__klevu_test_attribute_1" mapping clash for KLEVU_CATEGORY, KLEVU_PRODUCT entity types
',
                        'date' => date('Y-m-d H:i:s'),
                        'delete_after_view' => false,
                    ],
                ]
                : [
                    'notification_data' => [
                        'type' => 'Klevu_Indexing::conflicting_attribute_names',
                    ],
                ],
            2 => $duplicateAttributes
                ? [
                    'notification_data' => [
                        'type' => Constants::NOTIFICATION_TYPE_DUPLICATE_ATTRIBUTE_MAPPING,
                        'severity' => MessageInterface::SEVERITY_MAJOR,
                        // Magic number prevents dependency. See \Klevu\Notifications\Model\Notification::STATUS_ERROR
                        'status' => 3,
                        'message' => 'Conflicting Klevu attribute configuration detected: '
                            . 'multiple Magento attributes mapped to same Klevu attribute',
                        'details' => self::FIXTURE_API_KEY . '
KLEVU_CATEGORY
Attribute "name" mapped 2 time(s)
KLEVU_PRODUCT
Attribute "description" mapped 2 time(s)
',
                        'date' => date('Y-m-d H:i:s'),
                        'delete_after_view' => false,
                    ],
                ]
                : [
                    'notification_data' => [
                        'type' => 'Klevu_Indexing::duplicate_attribute_mapping',
                    ],
                ],
        ];

        $expectation = $this->exactly(2);
        $mockEventManager->expects($expectation)
            ->method('dispatch')
            ->with(
                $this->callback(
                    function (mixed $eventName) use ($expectation, $expectedEvents): bool {
                        $this->assertIsString($eventName);
                        $this->assertArrayHasKey(
                            key: $expectation->getInvocationCount(),
                            array: $expectedEvents,
                        );
                        $this->assertSame(
                            expected: $expectedEvents[$expectation->getInvocationCount()],
                            actual: $eventName,
                        );

                        return true;
                    },
                ),
                $this->callback(
                    function (mixed $data) use ($expectation, $expectedData): bool {
                        $this->assertIsArray($data);
                        $this->assertArrayHasKey('notification_data', $data);
                        $this->assertIsArray($data['notification_data']);

                        $invocation = $expectation->getInvocationCount();
                        $this->assertArrayHasKey(
                            key: $invocation,
                            array: $expectedData,
                        );

                        $dataDate = $data['notification_data']['date'] ?? null;
                        $expectedDataDate = $expectedData[$invocation]['notification_data']['date'] ?? null;

                        unset(
                            $data['notification_data']['date'],
                            $expectedData[$invocation]['notification_data']['date'],
                        );

                        $this->assertSame(
                            expected: $expectedData[$invocation],
                            actual: $data,
                        );
                        if ($dataDate && $expectedDataDate) {
                            $dataDateUnixtime = strtotime($dataDate);
                            $expectedDataDateUnixtime = strtotime($expectedDataDate);

                            $this->assertLessThanOrEqual($expectedDataDateUnixtime, $dataDateUnixtime - 60);
                            $this->assertGreaterThanOrEqual($expectedDataDateUnixtime, $dataDateUnixtime + 60);
                        } else {
                            $this->assertSame($expectedDataDate, $dataDate);
                        }

                        return true;
                    },
                ),
            );

        return $mockEventManager;
    }
}
