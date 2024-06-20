<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\FilterAttributesToSetToIndexableService;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\MagentoAttributeInterfaceFactory;
use Klevu\IndexingApi\Service\FilterAttributesToSetToIndexableServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\FilterAttributesToSetToIndexableService::class
 * @method FilterAttributesToSetToIndexableServiceInterface instantiateTestObject(?array $arguments = null)
 * @method FilterAttributesToSetToIndexableServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FilterAttributesToSetToIndexableServiceTest extends TestCase
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

        $this->implementationFqcn = FilterAttributesToSetToIndexableService::class;
        $this->interfaceFqcn = FilterAttributesToSetToIndexableServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->cleanIndexingAttributes('klevu-api-key%');
        $this->cleanIndexingAttributes('another-key%');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanIndexingAttributes('klevu-api-key%');
        $this->cleanIndexingAttributes('another-key%');
    }

    public function testExecute_RemovesMagentoAttributesAlreadyIndexable(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $indexingAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $indexingAttribute3 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_3',
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $indexingAttribute4 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => 'another-key',
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_4',
            IndexingAttribute::IS_INDEXABLE => false,
        ]);

        $magentoAttributeFactory = $this->objectManager->get(MagentoAttributeInterfaceFactory::class);
        $magentoAttributes[$apiKey][1] = $magentoAttributeFactory->create([
            'attributeId' => 1,
            'attributeCode' => 'klevu_test_attribute_1',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name1',
        ]);
        $magentoAttributes[$apiKey][2] = $magentoAttributeFactory->create([
            'attributeId' => 2,
            'attributeCode' => 'klevu_test_attribute_2',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name2',
        ]);
        $magentoAttributes[$apiKey][3] = $magentoAttributeFactory->create([
            'attributeId' => 3,
            'attributeCode' => 'klevu_test_attribute_3',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'name3',
        ]);
        $magentoAttributes[$apiKey][4] = $magentoAttributeFactory->create([
            'attributeId' => 4,
            'attributeCode' => 'klevu_test_attribute_4',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name4',
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            magentoAttributesByApiKey: $magentoAttributes,
            type: 'KLEVU_PRODUCT',
        );

        $this->assertCount(expectedCount: 2, haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute1->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute2->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute3->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute4->getId(), haystack: $result);
    }

    public function testExecute_RemovesStandardKlevuAttributes(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $indexingAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $indexingAttribute3 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);
        $indexingAttribute4 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::API_KEY => 'another-key',
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::IS_INDEXABLE => false,
        ]);

        $magentoAttributeFactory = $this->objectManager->get(MagentoAttributeInterfaceFactory::class);
        $magentoAttributes[$apiKey][1] = $magentoAttributeFactory->create([
            'attributeId' => 1,
            'attributeCode' => 'klevu_test_attribute_1',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name1',
        ]);
        $magentoAttributes[$apiKey][2] = $magentoAttributeFactory->create([
            'attributeId' => 2,
            'attributeCode' => 'klevu_test_attribute_2',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'description',
        ]);
        $magentoAttributes[$apiKey][3] = $magentoAttributeFactory->create([
            'attributeId' => 3,
            'attributeCode' => 'klevu_test_attribute_3',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'shortDescription',
        ]);
        $magentoAttributes[$apiKey][4] = $magentoAttributeFactory->create([
            'attributeId' => 4,
            'attributeCode' => 'klevu_test_attribute_4',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name4',
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute(
            magentoAttributesByApiKey: $magentoAttributes,
            type: 'KLEVU_PRODUCT',
        );

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute1->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute2->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute3->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute4->getId(), haystack: $result);
    }
}
