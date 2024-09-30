<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Observer;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection;
use Klevu\Indexing\Model\Update\Entity as EntityUpdate;
use Klevu\Indexing\Observer\UpdateEntitiesObserver;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Model\Update\EntityInterfaceFactory;
use Klevu\TestFixtures\Cms\PageFixturesPool;
use Klevu\TestFixtures\Cms\PageTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Observer\UpdateEntitiesObserver::class
 * @method UpdateEntitiesObserver instantiateTestObject(?array $arguments = null)
 * @method UpdateEntitiesObserver instantiateTestObjectFromInterface(?array $arguments = null)
 */
class UpdateEntitiesObserverTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use PageTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_Indexing_entityUpdate';
    private const EVENT_NAME = 'klevu_indexing_entity_update';

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

        $this->implementationFqcn = UpdateEntitiesObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->pageFixturesPool = $this->objectManager->get(PageFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->pageFixturesPool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testInvalidateCustomerDataObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: UpdateEntitiesObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    /**
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/js_api_key klevu-js-api-key
     * @magentoConfigFixture klevu_test_store_1_store klevu_configuration/auth_keys/rest_auth_key klevu-rest-auth-key
     */
    public function testObserver_ChangesIndexingEntityNewActionToUpdate(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-api-key',
            restAuthKey: 'klevu-rest-auth-key',
        );

        $this->createPage([
            'store_id' => $storeFixture->getId(),
        ]);
        $pageFixture = $this->pageFixturesPool->get('test_page');

        $this->removeIndexingEntities(); // page creation triggers creation of entities

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => $pageFixture->getId(),
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::API_KEY => 'klevu-js-api-key',
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $entityUpdateFactory = $this->objectManager->get(EntityInterfaceFactory::class);
        $entityUpdate = $entityUpdateFactory->create([
            'data' => [
                EntityUpdate::ENTITY_TYPE => 'KLEVU_CMS',
                EntityUpdate::ENTITY_IDS => [(int)$pageFixture->getId()],
                EntityUpdate::STORE_IDS => [(int)$storeFixture->getId()],
                EntityUpdate::ATTRIBUTES => ['price', 'stock'],
                EntityUpdate::ENTITY_SUBTYPES => [],
            ],
        ]);

        $this->dispatchEvent(
            event: self::EVENT_NAME,
            entityUpdate: $entityUpdate,
        );

        $collection = $this->objectManager->create(Collection::class);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ID, ['eq' => $pageFixture->getId()]);
        $collection->addFieldToFilter(IndexingEntity::API_KEY, ['eq' => 'klevu-js-api-key']);
        $collection->addFieldToFilter(IndexingEntity::TARGET_ENTITY_TYPE, ['eq' => 'KLEVU_CMS']);
        $collection->addFieldToFilter(IndexingEntity::TARGET_PARENT_ID, ['null' => null]);
        $indexingEntities = $collection->getItems();

        /** @var IndexingEntityInterface $indexingEntity */
        $indexingEntity = array_shift($indexingEntities);
        $this->assertSame(expected: $pageFixture->getId(), actual: $indexingEntity->getTargetId());
        $this->assertSame(expected: Actions::UPDATE->value, actual: $indexingEntity->getNextAction()->value);
    }

    /**
     * @param string $event
     * @param EntityUpdate $entityUpdate
     *
     * @return void
     */
    private function dispatchEvent(
        string $event,
        EntityUpdate $entityUpdate,
    ): void {
        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(type: EventManager::class);
        $eventManager->dispatch(
            $event,
            [
                'entityUpdate' => $entityUpdate,
            ],
        );
    }

    /**
     * @param string[] $apiKeys
     *
     * @return void
     */
    private function removeIndexingEntities(array $apiKeys = []): void
    {
        $repository = $this->objectManager->get(IndexingEntityRepositoryInterface::class);

        $collection = $this->objectManager->create(Collection::class);
        if ($apiKeys) {
            $collection->addFieldToFilter(IndexingEntity::API_KEY, ['in', implode(',', $apiKeys)]);
        }
        $indexingEntities = $collection->getItems();
        foreach ($indexingEntities as $indexingEntity) {
            try {
                $repository->delete($indexingEntity);
            } catch (LocalizedException) {
                // this is fine
            }
        }
    }
}
