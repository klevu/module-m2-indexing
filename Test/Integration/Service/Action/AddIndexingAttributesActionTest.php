<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Exception\IndexingAttributeSaveException;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\Action\AddIndexingAttributesAction;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\Action\AddIndexingAttributesActionInterface;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AddIndexingAttributesActionTest extends TestCase
{
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = AddIndexingAttributesAction::class;
        $this->interfaceFqcn = AddIndexingAttributesActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @dataProvider dataProvider_testExecute_SavesNewProductIndexingAttribute
     */
    public function testExecute_SavesNewIndexingAttribute(string $type): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);
        $magentoAttributes = [];
        $magentoAttributes[] = $this->objectManager->create(MagentoAttributeInterface::class, [
            'attributeId' => 1,
            'attributeCode' => 'klevu_test_attribute_1',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name1',
        ]);
        $magentoAttributes[] = $this->objectManager->create(MagentoAttributeInterface::class, [
            'attributeId' => 2,
            'attributeCode' => 'klevu_test_attribute_2',
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'klevuAttributeName' => 'name2',
        ]);
        $action = $this->instantiateTestObject();
        $action->execute(type: $type, magentoAttributes: $magentoAttributes);

        $indexingAttributes = $this->getIndexingAttributes($apiKey, $type);
        $this->assertCount(expectedCount: 2, haystack: $indexingAttributes);
        $targetIds = $this->getTargetIds($indexingAttributes);
        $this->assertContains(1, $targetIds);
        $this->assertContains(2, $targetIds);

        $indexingAttributeArray1 = $this->filterIndexAttributes($indexingAttributes, 1);
        $indexingAttribute1 = array_shift($indexingAttributeArray1);
        $this->assertTrue($indexingAttribute1->getIsIndexable());

        $indexingAttributeArray2 = $this->filterIndexAttributes($indexingAttributes, 2);
        $indexingAttribute2 = array_shift($indexingAttributeArray2);
        $this->assertFalse($indexingAttribute2->getIsIndexable());
    }

    /**
     * @return string[][]
     */
    public function dataProvider_testExecute_SavesNewProductIndexingAttribute(): array
    {
        return [
            ['KLEVU_CATEGORY'],
            ['KLEVU_CMS'],
            ['KLEVU_PRODUCT'],
        ];
    }

    public function testExecute_LogsError_WhenSaveExceptionIsThrown(): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);
        /** @var MagentoAttributeInterface $magentoAttribute */
        $magentoAttribute = $this->objectManager->create(MagentoAttributeInterface::class, [
            'attributeId' => 1,
            'attributeCode' => 'klevu_test_attribute_1',
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'klevuAttributeName' => 'name1',
        ]);
        $magentoAttributes = [$magentoAttribute];

        $mockIndexingAttributeRepository = $this->getMockBuilder(IndexingAttributeRepositoryInterface::class)
            ->getMock();
        $mockIndexingAttributeRepository->expects($this->once())
            ->method('save')
            ->willThrowException(new \Exception('Exception thrown by repo'));
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error');

        $this->expectException(IndexingAttributeSaveException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Failed to save Indexing Attributes for Magento Attribute IDs (%s). See log for details.',
                $magentoAttribute->getAttributeId(),
            ),
        );

        $action = $this->instantiateTestObject([
            'indexingAttributeRepository' => $mockIndexingAttributeRepository,
            'logger' => $mockLogger,
        ]);
        $action->execute(type: 'KLEVU_PRODUCT', magentoAttributes: $magentoAttributes);
    }

    /**
     * @param string $apiKey
     * @param string $type
     *
     * @return IndexingAttributeInterface[]
     */
    private function getIndexingAttributes(string $apiKey, string $type): array
    {
        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: IndexingAttribute::TARGET_ATTRIBUTE_TYPE,
            value: $type,
        );
        $searchCriteriaBuilder->addFilter(
            field: IndexingAttribute::API_KEY,
            value: $apiKey,
        );
        $searchCriteria = $searchCriteriaBuilder->create();
        $repository = $this->objectManager->get(IndexingAttributeRepositoryInterface::class);
        $searchResult = $repository->getList($searchCriteria);

        return $searchResult->getItems();
    }

    /**
     * @param IndexingAttributeInterface[] $indexingAttributes
     *
     * @return int[]
     */
    private function getTargetIds(array $indexingAttributes): array
    {
        return array_map(static fn (IndexingAttributeInterface $indexingAttribute): int => (
            $indexingAttribute->getTargetId()
        ), $indexingAttributes);
    }

    /**
     * @param IndexingAttributeInterface[] $indexingAttributes
     * @param int $attributeId
     *
     * @return IndexingAttributeInterface[]
     */
    private function filterIndexAttributes(array $indexingAttributes, int $attributeId): array
    {
        return array_filter(
            array: $indexingAttributes,
            callback: static function (IndexingAttributeInterface $indexingAttribute) use ($attributeId) {
                return $attributeId === (int)$indexingAttribute->getTargetId();
            },
        );
    }
}
