<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\FilterAttributesToDeleteService;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterfaceFactory;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterAttributesToDeleteServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\FilterAttributesToDeleteService::class
 * @method FilterAttributesToDeleteServiceInterface instantiateTestObject(?array $arguments = null)
 * @method FilterAttributesToDeleteServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FilterAttributesToDeleteServiceTest extends TestCase
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

        $this->implementationFqcn = FilterAttributesToDeleteService::class;
        $this->interfaceFqcn = FilterAttributesToDeleteServiceInterface::class;
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

    public function testExecute_ReturnsArrayOfIntegers(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute3 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_3',
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute4 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_4',
            IndexingAttribute::IS_INDEXABLE => false,
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
            'isIndexable' => false,
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

        $this->assertCount(expectedCount: 2, haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute1->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute2->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute3->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute4->getId(), haystack: $result);
    }

    public function testExecute_RemovesKlevuStandardAttributes(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_1',
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_2',
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute3 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_3',
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute4 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_4',
            IndexingAttribute::IS_INDEXABLE => true,
        ]);
        $indexingAttribute5 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 5,
            IndexingAttribute::TARGET_CODE => 'klevu_test_attribute_5',
            IndexingAttribute::IS_INDEXABLE => true,
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
            'isIndexable' => false,
            'klevuAttributeName' => 'description',
        ]);
        $magentoAttributes[$apiKey][3] = $magentoAttributeInterfaceFactory->create([
            'attributeId' => 3,
            'attributeCode' => 'klevu_test_attribute_3',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'shortDescription',
        ]);
        $magentoAttributes[$apiKey][4] = $magentoAttributeInterfaceFactory->create([
            'attributeId' => 4,
            'attributeCode' => 'klevu_test_attribute_5',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'name4',
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute($magentoAttributes, 'KLEVU_PRODUCTS');

        $this->assertCount(expectedCount: 2, haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute1->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute2->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute3->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute4->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute5->getId(), haystack: $result);
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
