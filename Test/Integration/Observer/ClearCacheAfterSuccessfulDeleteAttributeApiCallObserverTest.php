<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Observer;

use Klevu\Indexing\Cache\Attributes as AttributesCache;
use Klevu\Indexing\Observer\ClearCacheAfterSuccessfulAttributeApiCallObserver;
use Klevu\Indexing\Service\Action\Sdk\Attribute\DeleteAction;
use Klevu\IndexingApi\Service\Action\Cache\CacheAttributesActionInterface;
use Klevu\IndexingApi\Service\Provider\Cache\CachedAttributesProviderInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Traits\AttributesIteratorTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeList;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\Manager as EventManager;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers ClearCacheAfterSuccessfulUpdateAttributeApiCallObserver::class
 * @method ObserverInterface instantiateTestObject(?array $arguments = null)
 * @method ObserverInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ClearCacheAfterSuccessfulDeleteAttributeApiCallObserverTest extends TestCase
{
    use AttributesIteratorTrait;
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_Indexing_ClearCacheAfterSuccessfulDeleteAttributeApiCall';
    private const EVENT_NAME = 'klevu_indexing_attributes_action_delete_after';

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

        $this->implementationFqcn = ClearCacheAfterSuccessfulAttributeApiCallObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: ClearCacheAfterSuccessfulAttributeApiCallObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    public function testExecute_ClearsCacheOnlyForProvidedApiKey(): void
    {
        $apiKey1 = 'klevu-js-api-key-1';
        $apiKey2 = 'klevu-js-api-key-2';

        $this->clearCache();
        $attributesIterator1 = $this->createAttributeIterator();
        $attributesIterator2 = $this->createAttributeIterator([
            $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_custom_attribute',
                    'datatype' => DataType::STRING->value,
                    'searchable' => false,
                    'filterable' => true,
                    'returnable' => true,
                    'immutable' => true,
                ],
            ),
        ]);

        $cacheAction = $this->instantiateCacheAction();
        $cacheAction->execute(attributeIterator: $attributesIterator1, apiKey: $apiKey1);
        $cacheAction->execute(attributeIterator: $attributesIterator2, apiKey: $apiKey2);

        $cacheProvider = $this->instantiateCacheProvider();
        $cachedData1 = $cacheProvider->get(apiKey: $apiKey1);

        $this->assertNotNull($cachedData1);
        $attributeCodes = array_map(
            callback: static fn (AttributeInterface $attribute): string => $attribute->getAttributeName(),
            array: $cachedData1->toArray(),
        );
        $this->assertContains(needle: 'sku', haystack: $attributeCodes);
        $this->assertContains(needle: 'name', haystack: $attributeCodes);
        $this->assertContains(needle: 'price', haystack: $attributeCodes);
        $this->assertContains(needle: 'rating', haystack: $attributeCodes);

        $cachedData2 = $cacheProvider->get(apiKey: $apiKey2);

        $this->assertNotNull($cachedData2);
        $attributeCodes = array_map(
            callback: static fn (AttributeInterface $attribute): string => $attribute->getAttributeName(),
            array: $cachedData2->toArray(),
        );
        $this->assertContains(needle: 'my_custom_attribute', haystack: $attributeCodes);
        $this->assertContains(needle: 'name', haystack: $attributeCodes);
        $this->assertContains(needle: 'price', haystack: $attributeCodes);
        $this->assertContains(needle: 'rating', haystack: $attributeCodes);

        $eventManager = $this->objectManager->create(EventManager::class);
        $eventManager->dispatch(
            eventName: DeleteAction::KLEVU_INDEXING_ATTRIBUTES_ACTION_DELETE_AFTER,
            data: [
                'attribute_name' => 'some_attribute_code', // is not use for this observer
                'api_key' => $apiKey1,
                'attribute_type' => 'KLEVU_PRODUCT', // is not use for this observer
            ],
        );

        $result1 = $cacheProvider->get(apiKey: $apiKey1);
        $this->assertNull($result1);

        $result2 = $cacheProvider->get(apiKey: $apiKey2);
        $this->assertNotNull($result2);
        $attributeCodes = array_map(
            callback: static fn (AttributeInterface $attribute): string => $attribute->getAttributeName(),
            array: $result2->toArray(),
        );
        $this->assertContains(needle: 'my_custom_attribute', haystack: $attributeCodes);
        $this->assertContains(needle: 'name', haystack: $attributeCodes);
        $this->assertContains(needle: 'price', haystack: $attributeCodes);
        $this->assertContains(needle: 'rating', haystack: $attributeCodes);
    }

    /**
     * @param mixed[] $arguments
     *
     * @return CacheAttributesActionInterface
     */
    private function instantiateCacheAction(array $arguments = []): CacheAttributesActionInterface
    {
        return $this->objectManager->create(
            type: CacheAttributesActionInterface::class,
            arguments: $arguments,
        );
    }

    /**
     * @param mixed[] $arguments
     *
     * @return CachedAttributesProviderInterface
     */
    private function instantiateCacheProvider(array $arguments = []): CachedAttributesProviderInterface
    {
        return $this->objectManager->create(
            type: CachedAttributesProviderInterface::class,
            arguments: $arguments,
        );
    }

    /**
     * @return void
     */
    private function clearCache(): void
    {
        $cacheState = $this->objectManager->get(type: StateInterface::class);
        $cacheState->setEnabled(cacheType: AttributesCache::TYPE_IDENTIFIER, isEnabled: true);

        $typeList = $this->objectManager->get(TypeList::class);
        $typeList->cleanType(AttributesCache::TYPE_IDENTIFIER);
    }
}
