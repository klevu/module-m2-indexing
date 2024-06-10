<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Exception\IndexingAttributeSaveException;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\Action\SetIndexingAttributesToDeleteAction;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\Data\IndexingAttributeSearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\SetIndexingAttributesToDeleteActionInterface;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SetIndexingAttributesToDeleteActionTest extends TestCase
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

        $this->implementationFqcn = SetIndexingAttributesToDeleteAction::class;
        $this->interfaceFqcn = SetIndexingAttributesToDeleteActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @dataProvider dataProvider_testExecute_SetsIndexingEntityNextAction
     */
    public function testExecute_SetsIndexingAttributeNextActionDelete_ForIndexableAttribute(string $type): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => $type,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::IS_INDEXABLE => false,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => $type,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
        ]);

        $indexingAttributes = $this->getIndexingAttributes($apiKey, $type);
        $this->assertCount(expectedCount: 2, haystack: $indexingAttributes);
        $attributeIds = $this->getAttributeIds($indexingAttributes);

        $action = $this->instantiateTestObject();
        $action->execute($attributeIds);

        $indexingAttributes = $this->getIndexingAttributes($apiKey, $type);
        $this->assertCount(expectedCount: 2, haystack: $indexingAttributes);

        $indexingEntityArray1 = $this->filterIndexAttributes($indexingAttributes, 1);
        $indexingEntity1 = array_shift($indexingEntityArray1);
        $this->assertTrue($indexingEntity1->getIsIndexable());
        $this->assertSame(expected: Actions::DELETE, actual: $indexingEntity1->getNextAction());

        $indexingEntityArray2 = $this->filterIndexAttributes($indexingAttributes, 2);
        $indexingEntity2 = array_shift($indexingEntityArray2);
        $this->assertFalse($indexingEntity2->getIsIndexable());
        $this->assertSame(expected: Actions::DELETE, actual: $indexingEntity2->getNextAction());
    }

    /**
     * @return string[][]
     */
    public function dataProvider_testExecute_SetsIndexingEntityNextAction(): array
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

        $indexingEntity1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1234,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
        ]);
        $indexingEntity2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2345,
            IndexingAttribute::IS_INDEXABLE => false,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCTS',
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
        ]);

        $indexingEntities = $this->getIndexingAttributes($apiKey, 'KLEVU_PRODUCTS');
        $this->assertCount(expectedCount: 2, haystack: $indexingEntities);
        $entityIds = $this->getAttributeIds($indexingEntities);

        $mockSearchResult = $this->getMockBuilder(IndexingAttributeSearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([
                $indexingEntity1,
                $indexingEntity2,
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
                implode(', ', [$indexingEntity1->getId(), $indexingEntity2->getId()]),
            ),
        );

        $action = $this->instantiateTestObject([
            'indexingAttributeRepository' => $mockIndexingAttributeRepository,
            'logger' => $mockLogger,
        ]);
        $action->execute($entityIds);
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
