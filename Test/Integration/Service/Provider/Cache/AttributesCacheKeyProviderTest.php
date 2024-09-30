<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider\Cache;

use Klevu\Indexing\Cache\Attributes;
use Klevu\Indexing\Service\Provider\Cache\AttributesCacheKeyProvider;
use Klevu\IndexingApi\Service\Provider\Cache\AttributesCacheKeyProviderInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers AttributesCacheKeyProvider::class
 * @method AttributesCacheKeyProviderInterface instantiateTestObject(?array $arguments = null)
 * @method AttributesCacheKeyProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributesCacheKeyProviderTest extends TestCase
{
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

        $this->implementationFqcn = AttributesCacheKeyProvider::class;
        $this->interfaceFqcn = AttributesCacheKeyProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
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

    public function testGet_ReturnsCacheKey(): void
    {
        $apiKey = 'klevu-js-api-key';

        $encryptor = $this->objectManager->get(Encryptor::class);

        $expectedCacheKey = Attributes::TYPE_IDENTIFIER
            . AttributesCacheKeyProvider::CACHE_CONCATENATION_STRING
            . $encryptor->hash(data: $apiKey, version: Encryptor::HASH_VERSION_SHA256);

        $provider = $this->instantiateTestObject();
        $cacheKey = $provider->get(apiKey: $apiKey);
        $this->assertSame(expected: $expectedCacheKey, actual: $cacheKey);
    }
}
