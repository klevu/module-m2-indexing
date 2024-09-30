<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Exception\IndexingAttributeSaveException;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\Action\SetIndexingAttributesToNotBeIndexableAction;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\Data\IndexingAttributeSearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\SetIndexingAttributesToNotBeIndexableActionInterface;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers \Klevu\Indexing\Service\Action\SetIndexingAttributesToNotBeIndexableAction::class
 * @method SetIndexingAttributesToNotBeIndexableActionInterface instantiateTestObject(?array $arguments = null)
 * @method SetIndexingAttributesToNotBeIndexableActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SetIndexingAttributesToNotBeIndexableActionTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
    use IndexingAttributesTrait;
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

        $this->implementationFqcn = SetIndexingAttributesToNotBeIndexableAction::class;
        $this->interfaceFqcn = SetIndexingAttributesToNotBeIndexableActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith ["KLEVU_CATEGORY"]
     *           ["KLEVU_PRODUCT"]
     */
    public function testExecute_SetsIndexingAttributeToBeIndexable_ForNoneIndexableEntities(string $type): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => $type,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => $type,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::IS_INDEXABLE => false,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => $type,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
        ]);

        $indexingAttributes = $this->getIndexingAttributes($apiKey, $type);
        $this->assertCount(expectedCount: 3, haystack: $indexingAttributes);
        $attributeIds = $this->getAttributeIds($indexingAttributes);

        $action = $this->instantiateTestObject();
        $action->execute($attributeIds);

        $indexingAttributes = $this->getIndexingAttributes($apiKey, $type);
        $this->assertCount(expectedCount: 3, haystack: $indexingAttributes);

        $indexingAttributeArray1 = $this->filterIndexAttributes($indexingAttributes, 1);
        $indexingAttribute1 = array_shift($indexingAttributeArray1);
        $this->assertFalse($indexingAttribute1->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute1->getNextAction());

        $indexingAttributeArray2 = $this->filterIndexAttributes($indexingAttributes, 2);
        $indexingAttribute2 = array_shift($indexingAttributeArray2);
        $this->assertFalse($indexingAttribute2->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute2->getNextAction());

        $indexingAttributeArray3 = $this->filterIndexAttributes($indexingAttributes, 3);
        $indexingAttribute3 = array_shift($indexingAttributeArray3);
        $this->assertFalse($indexingAttribute3->getIsIndexable());
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingAttribute3->getNextAction());

        $this->cleanIndexingAttributes(apiKey: $apiKey);
    }

    public function testExecute_LogsError_WhenSaveExceptionIsThrown(): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);
        $this->cleanIndexingAttributes(apiKey: $apiKey);

        $indexingAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1234,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
        ]);
        $indexingAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2345,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
        ]);

        $indexingAttributes = $this->getIndexingAttributes($apiKey, 'KLEVU_PRODUCTS');
        $this->assertCount(expectedCount: 2, haystack: $indexingAttributes);
        $attributeIds = $this->getAttributeIds($indexingAttributes);

        $mockSearchResult = $this->getMockBuilder(IndexingAttributeSearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([
                $indexingAttribute1,
                $indexingAttribute2,
            ]);
        $mockIndexingAttributeRepository = $this->getMockBuilder(IndexingAttributeRepositoryInterface::class)
            ->getMock();
        $mockIndexingAttributeRepository->expects($this->once())
            ->method('getList')
            ->willReturn($mockSearchResult);
        $mockIndexingAttributeRepository->expects($this->exactly(2))
            ->method('save')
            ->willThrowException(new \Exception('Exception thrown by repo'));

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->exactly(2))
            ->method('error');

        $this->expectException(IndexingAttributeSaveException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Indexing attributes (%s) failed to save. See log for details.',
                implode(', ', [$indexingAttribute1->getId(), $indexingAttribute2->getId()]),
            ),
        );

        $action = $this->instantiateTestObject([
            'indexingAttributeRepository' => $mockIndexingAttributeRepository,
            'logger' => $mockLogger,
        ]);
        $action->execute($attributeIds);

        $this->cleanIndexingAttributes(apiKey: $apiKey);
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
        $repository = $this->objectManager->create(IndexingAttributeRepositoryInterface::class);
        $searchResult = $repository->getList($searchCriteria);

        return $searchResult->getItems();
    }

    /**
     * @param IndexingAttributeInterface[] $indexingAttributes
     *
     * @return int[]
     */
    private function getAttributeIds(array $indexingAttributes): array
    {
        return array_map(static fn (IndexingAttributeInterface $indexingAttribute): int => (
            (int)$indexingAttribute->getId()
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
