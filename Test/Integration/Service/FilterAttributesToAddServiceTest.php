<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\FilterAttributesToAddService;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterfaceFactory;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterAttributesToAddServiceInterface;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\AttributeApiCallTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Service\FilterAttributesToAddService::class
 * @method FilterAttributesToAddServiceInterface instantiateTestObject(?array $arguments = null)
 * @method FilterAttributesToAddServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FilterAttributesToAddServiceTest extends TestCase
{
    use AttributeApiCallTrait;
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

        $this->implementationFqcn = FilterAttributesToAddService::class;
        $this->interfaceFqcn = FilterAttributesToAddServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->cleanIndexingAttributes('klevu-api-key%');

        $this->clearAttributeCache();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanIndexingAttributes('klevu-api-key%');
        $this->storeFixturesPool->rollback();

        $this->removeSharedApiInstances();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_logsError_WhenApiExceptionInterfaceThrown(): void
    {
        $apiKey = 'klevu-123456789';
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'ABCDEFGHI1234567890',
        );

        $magentoAttributeInterfaceFactory = $this->objectManager->get(MagentoAttributeInterfaceFactory::class);
        $magentoAttributes[$apiKey][1] = $magentoAttributeInterfaceFactory->create([
            'attributeId' => 1,
            'attributeCode' => 'klevu_test_attribute_1',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name1',
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\FilterAttributesToAddService::execute',
                    'message' => 'Authentication failed. Please ensure your credentials are valid and try again.',
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
        ]);
        $service->execute($magentoAttributes, 'KLEVU_PRODUCTS');
    }

    public function testExecute_RemovesMagentoAttributesAlreadyInKlevuAttributes(): void
    {
        $this->mockSdkAttributeGetApiCall();

        $apiKey = 'klevu-api-key';
        $authKey = 'klevu-rest-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
        ]);

        $magentoAttributeInterfaceFactory = $this->objectManager->get(MagentoAttributeInterfaceFactory::class);
        $magentoAttributes[$apiKey][1] = $magentoAttributeInterfaceFactory->create([
            'attributeId' => 1,
            'attributeCode' => 'klevu_test_attribute_1',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name1',
        ]);
        $magentoAttributes[$apiKey][2] = $magentoAttributeInterfaceFactory->create([
            'attributeId' => 2,
            'attributeCode' => 'klevu_test_attribute_2',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name2',
        ]);
        $magentoAttributes[$apiKey][3] = $magentoAttributeInterfaceFactory->create([
            'attributeId' => 3,
            'attributeCode' => 'klevu_test_attribute_3',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'name3',
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute($magentoAttributes, 'KLEVU_PRODUCTS');

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertCount(expectedCount: 2, haystack: $result[$apiKey]);
        $magentoAttributeIds = array_map(
            callback: static function (MagentoAttributeInterface $magentoAttribute): int {
                return (int)$magentoAttribute->getAttributeId();
            },
            array: $result[$apiKey],
        );
        $this->assertContains(needle: 1, haystack: $magentoAttributeIds);
        $this->assertNotContains(needle: 2, haystack: $magentoAttributeIds);
        $this->assertContains(needle: 3, haystack: $magentoAttributeIds);
    }

    public function testExecute_RemovesKlevuStandardAttributes(): void
    {
        $this->mockSdkAttributeGetApiCall();

        $apiKey = 'klevu-api-key';
        $authKey = 'klevu-rest-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());

        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
        ]);

        $magentoAttributeInterfaceFactory = $this->objectManager->get(MagentoAttributeInterfaceFactory::class);
        $magentoAttributes[$apiKey][1] = $magentoAttributeInterfaceFactory->create([
            'attributeId' => 1,
            'attributeCode' => 'klevu_test_attribute_1',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name1',
        ]);
        $magentoAttributes[$apiKey][2] = $magentoAttributeInterfaceFactory->create([
            'attributeId' => 2,
            'attributeCode' => 'klevu_test_attribute_2',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'description',
        ]);
        $magentoAttributes[$apiKey][3] = $magentoAttributeInterfaceFactory->create([
            'attributeId' => 3,
            'attributeCode' => 'klevu_test_attribute_3',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'sku',
        ]);

        $attributes = [
            $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_custom_attribute',
                    'datatype' => DataType::NUMBER->value,
                    'searchable' => false,
                    'filterable' => true,
                    'returnable' => false,
                    'immutable' => false,
                ],
            ),
        ];
        $this->mockSdkAttributeGetApiCall($attributes);

        $service = $this->instantiateTestObject();
        $result = $service->execute($magentoAttributes, 'KLEVU_PRODUCTS');

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertCount(expectedCount: 1, haystack: $result[$apiKey]);
        $magentoAttributeIds = array_map(
            callback: static function (MagentoAttributeInterface $magentoAttribute): int {
                return (int)$magentoAttribute->getAttributeId();
            },
            array: $result[$apiKey],
        );
        $this->assertContains(needle: 1, haystack: $magentoAttributeIds);
        $this->assertNotContains(needle: 2, haystack: $magentoAttributeIds);
        $this->assertNotContains(needle: 3, haystack: $magentoAttributeIds);
    }

    /**
     * @param mixed[] $data
     *
     * @return IndexingAttributeInterface
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createIndexingAttribute(array $data): IndexingAttributeInterface
    {
        $repository = $this->objectManager->get(IndexingAttributeRepositoryInterface::class);
        $indexingAttribute = $repository->create();
        $indexingAttribute->setTargetId((int)$data[IndexingAttribute::TARGET_ID]);
        $indexingAttribute->setTargetCode($data[IndexingAttribute::TARGET_CODE]);
        $indexingAttribute->setTargetAttributeType(
            $data[IndexingAttribute::TARGET_ATTRIBUTE_TYPE] ?? 'KLEVU_PRODUCT',
        );
        $indexingAttribute->setApiKey($data[IndexingAttribute::API_KEY] ?? 'klevu-js-api-key');
        $indexingAttribute->setNextAction($data[IndexingAttribute::NEXT_ACTION] ?? Actions::NO_ACTION);
        $indexingAttribute->setLastAction($data[IndexingAttribute::LAST_ACTION] ?? Actions::NO_ACTION);
        $indexingAttribute->setLastActionTimestamp(
            $data[IndexingAttribute::LAST_ACTION_TIMESTAMP] ?? null,
        );
        $indexingAttribute->setLockTimestamp($data[IndexingAttribute::LOCK_TIMESTAMP] ?? null);
        $indexingAttribute->setIsIndexable($data[IndexingAttribute::IS_INDEXABLE] ?? true);

        return $repository->save($indexingAttribute);
    }
}
