<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Cache;

use Klevu\Indexing\Cache\Attributes as AttributesCache;
use Klevu\IndexingApi\Service\Action\Cache\CacheAttributesActionInterface;
use Klevu\IndexingApi\Service\Provider\Cache\CachedAttributesProviderInterface;
use Klevu\PhpSDK\Api\Model\Indexing\AttributeInterface;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Traits\AttributesIteratorTrait;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeList;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class AttributesCacheTest extends TestCase
{
    use AttributesIteratorTrait;

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

        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_SavesDataToCache_CacheEmpty(): void
    {
        $this->clearCache();
        $apiKey = 'klevu-js-api-key';

        $provider = $this->instantiateCacheProvider();
        $result = $provider->get(apiKey: $apiKey);
        $this->assertNull($result);

        $attributeIterator = $this->createAttributeIterator();

        $action = $this->instantiateCacheAction();
        $action->execute(attributeIterator: $attributeIterator, apiKey: $apiKey);

        $result = $provider->get(apiKey: $apiKey);
        $this->assertNotNull($result);

        $attributeCodes = array_map(
            callback: static fn (AttributeInterface $attribute): string => $attribute->getAttributeName(),
            array: $result->toArray(),
        );

        $this->assertContains(needle: 'sku', haystack: $attributeCodes);
        $this->assertContains(needle: 'name', haystack: $attributeCodes);
        $this->assertContains(needle: 'price', haystack: $attributeCodes);
        $this->assertContains(needle: 'rating', haystack: $attributeCodes);
    }

    public function testExecute_SavesDataToCache_CachePopulated(): void
    {
        $this->clearCache();
        $apiKey = 'klevu-js-api-key';

        $provider = $this->instantiateCacheProvider();
        $result = $provider->get(apiKey: $apiKey);
        $this->assertNull($result);

        $attributeIterator = $this->createAttributeIterator();

        $action = $this->instantiateCacheAction();
        $action->execute(attributeIterator: $attributeIterator, apiKey: $apiKey);

        $result = $provider->get(apiKey: $apiKey);
        $this->assertNotNull($result);

        $attributeCodes = array_map(
            callback: static fn (AttributeInterface $attribute): string => $attribute->getAttributeName(),
            array: $result->toArray(),
        );

        $this->assertNotContains(needle: 'my_custom_attribute', haystack: $attributeCodes);
        $this->assertContains(needle: 'sku', haystack: $attributeCodes);
        $this->assertContains(needle: 'name', haystack: $attributeCodes);
        $this->assertContains(needle: 'price', haystack: $attributeCodes);
        $this->assertContains(needle: 'rating', haystack: $attributeCodes);

        $newAttributeIterator = $this->createAttributeIterator([
            $this->objectManager->create(
                type: Attribute::class,
                arguments: [
                    'attributeName' => 'my_custom_attribute',
                    'datatype' => DataType::STRING->value,
                    'searchable' => true,
                    'filterable' => false,
                    'returnable' => true,
                    'immutable' => false,
                ],
            ),
        ]);
        $action->execute(attributeIterator: $newAttributeIterator, apiKey: $apiKey);

        $result = $provider->get(apiKey: $apiKey);
        $this->assertNotNull($result);

        $attributeCodes = array_map(
            callback: static fn (AttributeInterface $attribute): string => $attribute->getAttributeName(),
            array: $result->toArray(),
        );

        $this->assertContains(needle: 'my_custom_attribute', haystack: $attributeCodes);
        $this->assertContains(needle: 'sku', haystack: $attributeCodes);
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
