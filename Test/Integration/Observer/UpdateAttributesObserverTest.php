<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Observer;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Model\ResourceModel\IndexingAttribute\Collection;
use Klevu\Indexing\Model\Update\Attribute as AttributeUpdate;
use Klevu\Indexing\Observer\UpdateAttributesObserver;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Model\Update\AttributeInterfaceFactory;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Observer\UpdateAttributesObserver::class
 * @method UpdateAttributesObserver instantiateTestObject(?array $arguments = null)
 * @method UpdateAttributesObserver instantiateTestObjectFromInterface(?array $arguments = null)
 */
class UpdateAttributesObserverTest extends TestCase
{
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_Indexing_attributeUpdate';
    private const EVENT_NAME = 'klevu_indexing_attribute_update';

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

        $this->implementationFqcn = UpdateAttributesObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testInvalidateCustomerDataObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: UpdateAttributesObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    /**
     * @magentoDbIsolation disabled
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testObserver_ChangesIndexingAttributeNewActionToUpdate(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: 'klevu-rest-auth-key',
        );

        $this->createAttribute([
            'attribute_type' => 'text',
            'key' => 'test_attribute',
            'code' => 'klevu_test_attribute_' . random_int(1, 9999999),
            'index_as' => IndexType::INDEX,
        ]);
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->cleanIndexingAttributes($apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => (int)$attributeFixture->getAttributeId(),
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $attributeUpdateFactory = $this->objectManager->get(AttributeInterfaceFactory::class);
        $attributeUpdate = $attributeUpdateFactory->create([
            'data' => [
                AttributeUpdate::ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
                AttributeUpdate::ATTRIBUTE_IDS => [(int)$attributeFixture->getAttributeId()],
                AttributeUpdate::STORE_IDS => [(int)$storeFixture->getId()],
            ],
        ]);

        $this->dispatchEvent(
            event: self::EVENT_NAME,
            attributeUpdate: $attributeUpdate,
        );

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingAttribute::TARGET_ID, ['eq' => $attributeFixture->getAttributeId()]);
        $collection->addFieldToFilter(IndexingAttribute::API_KEY, ['eq' => $apiKey]);
        $collection->addFieldToFilter(IndexingAttribute::TARGET_ATTRIBUTE_TYPE, ['eq' => 'KLEVU_PRODUCT']);
        $indexingAttributes = $collection->getItems();

        $indexingAttribute = array_shift($indexingAttributes);
        $this->assertInstanceOf(expected: IndexingAttributeInterface::class, actual: $indexingAttribute);
        $this->assertSame(expected: $attributeFixture->getAttributeId(), actual: $indexingAttribute->getTargetId());
        $this->assertSame(expected: Actions::UPDATE->value, actual: $indexingAttribute->getNextAction()->value);
    }

    /**
     * @param string $event
     * @param AttributeUpdate $attributeUpdate
     *
     * @return void
     */
    private function dispatchEvent(
        string $event,
        AttributeUpdate $attributeUpdate,
    ): void {
        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(type: EventManager::class);
        $eventManager->dispatch(
            $event,
            [
                'attributeUpdate' => $attributeUpdate,
            ],
        );
    }
}
