<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Cache;

use Klevu\Indexing\Cache\Attributes as AttributesCache;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class AttributesCacheConfigurationTest extends TestCase
{
    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testCacheType_IsRegistered(): void
    {
        $typeList = $this->objectManager->get(TypeListInterface::class);

        $labels = $typeList->getTypeLabels();
        $this->assertArrayHasKey(key: AttributesCache::TYPE_IDENTIFIER, array: $labels);
        $this->assertSame(expected: 'Klevu Indexing Attributes', actual: $labels[AttributesCache::TYPE_IDENTIFIER]);

        $types = $typeList->getTypes();
        $this->assertArrayHasKey(key: AttributesCache::TYPE_IDENTIFIER, array: $types);
        /** @var DataObject $cacheConfig */
        $cacheConfig = $types[AttributesCache::TYPE_IDENTIFIER];
        $this->assertSame(expected: 'Klevu Indexing Attributes', actual: $cacheConfig->getData('cache_type'));
        $this->assertSame(expected: AttributesCache::TYPE_IDENTIFIER, actual: $cacheConfig->getData('id'));
        $this->assertSame(expected: AttributesCache::CACHE_TAG, actual: $cacheConfig->getData('tags'));
        $this->assertSame(
            expected: 'Caches API Request to get list of attributes from Klevu.',
            actual: $cacheConfig->getData('description'),
        );
    }

}
