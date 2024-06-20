<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\FilterAttributesToUpdateService;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Service\FilterAttributesToUpdateServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\FilterAttributesToUpdateService::class
 * @method FilterAttributesToUpdateServiceInterface instantiateTestObject(?array $arguments = null)
 * @method FilterAttributesToUpdateServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FilterAttributesToUpdateServiceTest extends TestCase
{
    use IndexingAttributesTrait;
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

        $this->implementationFqcn = FilterAttributesToUpdateService::class;
        $this->interfaceFqcn = FilterAttributesToUpdateServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->cleanIndexingAttributes('klevu-api-key%');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanIndexingAttributes('klevu-api-key%');
    }

    public function testExecute_ReturnsArrayOfIndexingAttributesIds(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute3 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CMS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute4 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute5 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => 'another-key',
            IndexingAttribute::TARGET_ID => 5,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            type: 'KLEVU_PRODUCT',
            attributeIds: [1, 3, 4, 5, 999],
            apiKeys: [$apiKey],
        );

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute1->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute2->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute3->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute4->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute5->getId(), haystack: $result);
    }
}
