<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\FilterAttributesToSetToNotIndexableService;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterfaceFactory;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterAttributesToSetToNotIndexableServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers FilterAttributesToSetToNotIndexableService::class
 * @method FilterAttributesToSetToNotIndexableServiceInterface instantiateTestObject(?array $arguments = null)
 * @method FilterAttributesToSetToNotIndexableServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FilterAttributesToSetToNotIndexableServiceTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
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

        $this->implementationFqcn = FilterAttributesToSetToNotIndexableService::class;
        $this->interfaceFqcn = FilterAttributesToSetToNotIndexableServiceInterface::class;
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

    public function testExecute_ReturnsAttributeIdsToSetToNotIndexable_whichHaveBeenDisabled(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $indexingAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $indexingAttribute3 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
        ]);
        $indexingAttribute4 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::IS_INDEXABLE => false,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
        ]);

        $MagentoAttributeInterfaceFactory = $this->objectManager->get(MagentoAttributeInterfaceFactory::class);
        $magentoAttributes[$apiKey][1] = $MagentoAttributeInterfaceFactory->create([
            'attributeId' => 1,
            'attributeCode' => 'attribute_code_1',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'attribute_code_1',
        ]);
        $magentoAttributes[$apiKey][2] = $MagentoAttributeInterfaceFactory->create([
            'attributeId' => 2,
            'attributeCode' => 'attribute_code_2',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'attribute_code_2',
        ]);
        $magentoAttributes[$apiKey][3] = $MagentoAttributeInterfaceFactory->create([
            'attributeId' => 3,
            'attributeCode' => 'attribute_code_3',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'attribute_code_3',
        ]);
        $magentoAttributes[$apiKey][4] = $MagentoAttributeInterfaceFactory->create([
            'attributeId' => 4,
            'attributeCode' => 'attribute_code_4',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'attribute_code_4',
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute($magentoAttributes, 'KLEVU_PRODUCTS');

        $this->assertCount(expectedCount: 2, haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute1->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute2->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute3->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute4->getId(), haystack: $result);
    }

    public function testExecute_ReturnsAttributeIdsToSetToNotIndexable_whichHaveBeenDeleted(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $indexingAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $indexingAttribute3 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
        ]);
        $indexingAttribute4 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::IS_INDEXABLE => false,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
        ]);

        $MagentoAttributeInterfaceFactory = $this->objectManager->get(MagentoAttributeInterfaceFactory::class);
        $magentoAttributes[$apiKey][1] = $MagentoAttributeInterfaceFactory->create([
            'attributeId' => 1,
            'attributeCode' => 'attribute_code_1',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'attribute_code_1',
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute($magentoAttributes, 'KLEVU_PRODUCTS');

        $this->assertCount(expectedCount: 2, haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute1->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute2->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute3->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute4->getId(), haystack: $result);
    }

    public function testExecute_DoesNotReturnsAttributeIdsToSetTonotIndexale_whichHaveBeenSynced(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
        ]);
        $indexingAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $indexingAttribute3 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
        ]);
        $indexingAttribute4 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::IS_INDEXABLE => false,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
        ]);

        $MagentoAttributeInterfaceFactory = $this->objectManager->get(MagentoAttributeInterfaceFactory::class);
        $magentoAttributes[$apiKey][1] = $MagentoAttributeInterfaceFactory->create([
            'attributeId' => 1,
            'attributeCode' => 'attribute_code_1',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'attribute_code_1',
        ]);
        $magentoAttributes[$apiKey][2] = $MagentoAttributeInterfaceFactory->create([
            'attributeId' => 2,
            'attributeCode' => 'attribute_code_2',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'attribute_code_2',
        ]);
        $magentoAttributes[$apiKey][3] = $MagentoAttributeInterfaceFactory->create([
            'attributeId' => 3,
            'attributeCode' => 'attribute_code_3',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'attribute_code_3',
        ]);
        $magentoAttributes[$apiKey][4] = $MagentoAttributeInterfaceFactory->create([
            'attributeId' => 4,
            'attributeCode' => 'attribute_code_4',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'attribute_code_4',
        ]);

        $service = $this->instantiateTestObject();
        $result = $service->execute($magentoAttributes, 'KLEVU_PRODUCTS');

        $this->assertCount(expectedCount: 2, haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute1->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute2->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingAttribute3->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingAttribute4->getId(), haystack: $result);
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
        $indexingAttribute->setTargetAttributeType($data[IndexingAttribute::TARGET_ATTRIBUTE_TYPE] ?? 'KLEVU_PRODUCT');
        $indexingAttribute->setApiKey($data[IndexingAttribute::API_KEY] ?? 'klevu-js-api-key');
        $indexingAttribute->setNextAction($data[IndexingAttribute::NEXT_ACTION] ?? Actions::NO_ACTION);
        $indexingAttribute->setLastAction($data[IndexingAttribute::LAST_ACTION] ?? Actions::NO_ACTION);
        $indexingAttribute->setLastActionTimestamp($data[IndexingAttribute::LAST_ACTION_TIMESTAMP] ?? null);
        $indexingAttribute->setLockTimestamp($data[IndexingAttribute::LOCK_TIMESTAMP] ?? null);
        $indexingAttribute->setIsIndexable($data[IndexingAttribute::IS_INDEXABLE] ?? true);

        return $repository->save($indexingAttribute);
    }
}
