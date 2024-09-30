<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action\Cache;

use Klevu\Indexing\Cache\Attributes as AttributesCache;
use Klevu\Indexing\Service\Action\Cache\ClearAttributesCacheAction;
use Klevu\IndexingApi\Service\Action\Cache\CacheAttributesActionInterface;
use Klevu\IndexingApi\Service\Action\Cache\ClearAttributesCacheActionInterface;
use Klevu\IndexingApi\Service\Provider\Cache\CachedAttributesProviderInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Traits\AttributesIteratorTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers ClearAttributesCacheAction::class
 * @method ClearAttributesCacheActionInterface instantiateTestObject(?array $arguments = null)
 * @method ClearAttributesCacheActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ClearAttributesCacheActionTest extends TestCase
{
    use AttributesIteratorTrait;
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = ClearAttributesCacheAction::class;
        $this->interfaceFqcn = ClearAttributesCacheActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_ClearsCacheForAllApiKeys_WhenNoKeyProvided(): void
    {
        $apiKey = 'klevu-js-api-key';

        $this->clearCache();
        $attributesIterator = $this->createAttributeIterator();

        $cacheAction = $this->instantiateCacheAction();
        $cacheAction->execute(attributeIterator: $attributesIterator, apiKey: $apiKey);

        $cacheProvider = $this->instantiateCacheProvider();
        $cachedData = $cacheProvider->get(apiKey: $apiKey);

        $this->assertNotNull($cachedData);
        $attributeCodes = array_map(
            callback: static fn (AttributeInterface $attribute): string => $attribute->getAttributeName(),
            array: $cachedData->toArray(),
        );
        $this->assertContains(needle: 'sku', haystack: $attributeCodes);
        $this->assertContains(needle: 'name', haystack: $attributeCodes);
        $this->assertContains(needle: 'price', haystack: $attributeCodes);
        $this->assertContains(needle: 'rating', haystack: $attributeCodes);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('info')
            ->with(
                'Method: {method}, Info: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\Action\Cache\ClearAttributesCacheAction::logCacheCleared',
                    'message' => __('Attributes cached cleared for all API Keys'),
                ],
            );

        $action = $this->instantiateTestObject([
            'logger' => $mockLogger,
        ]);
        $action->execute();

        $result = $cacheProvider->get(apiKey: $apiKey);

        $this->assertNull($result);
    }

    public function testExecute_ClearsCache_ForProvidedApiKeyOnly(): void
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

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('info')
            ->with(
                'Method: {method}, Info: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\Action\Cache\ClearAttributesCacheAction::logCacheCleared',
                    'message' => __(
                        'Attributes cached cleared for API Keys: %1',
                        implode(', ', [$apiKey1]),
                    ),
                ],
            );

        $action = $this->instantiateTestObject([
            'logger' => $mockLogger,
        ]);
        $action->execute(apiKeys: [$apiKey1]);

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
