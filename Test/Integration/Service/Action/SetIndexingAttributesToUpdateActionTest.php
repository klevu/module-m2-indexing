<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Exception\IndexingAttributeSaveException;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Service\Action\SetIndexingAttributesToUpdateAction;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\Data\IndexingAttributeSearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\SetIndexingAttributesToUpdateActionInterface;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Service\Action\SetIndexingAttributesToUpdateAction::class
 * @method SetIndexingAttributesToUpdateActionInterface instantiateTestObject(?array $arguments = null)
 * @method SetIndexingAttributesToUpdateActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SetIndexingAttributesToUpdateActionTest extends TestCase
{
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

        $this->implementationFqcn = SetIndexingAttributesToUpdateAction::class;
        $this->interfaceFqcn = SetIndexingAttributesToUpdateActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @testWith ["KLEVU_CATEGORY"]
     *           ["KLEVU_PRODUCT"]
     */
    public function testExecute_SetsIndexingAttributeNextActionUpdate_ForIndexableAttributes(string $type): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);

        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => $type,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => $type,
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::UPDATE,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 3,
            IndexingAttribute::IS_INDEXABLE => false,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => $type,
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::DELETE,
        ]);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 4,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => $type,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $indexingAttributes = $this->getIndexingAttributes($type, $apiKey);
        $this->assertCount(expectedCount: 4, haystack: $indexingAttributes);
        $attributeIds = $this->getAttributeIds($indexingAttributes);

        $action = $this->instantiateTestObject();
        $action->execute($attributeIds);

        $indexingAttributes = $this->getIndexingAttributes($type, $apiKey);
        $this->assertCount(expectedCount: 4, haystack: $indexingAttributes);

        $indexingAttributeArray1 = $this->filterIndexAttributes($indexingAttributes, 1);
        $indexingAttribute1 = array_shift($indexingAttributeArray1);
        $this->assertTrue($indexingAttribute1->getIsIndexable());
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingAttribute1->getNextAction());

        $indexingAttributeArray2 = $this->filterIndexAttributes($indexingAttributes, 2);
        $indexingAttribute2 = array_shift($indexingAttributeArray2);
        $this->assertTrue($indexingAttribute2->getIsIndexable());
        $this->assertSame(expected: Actions::UPDATE, actual: $indexingAttribute2->getNextAction());

        $indexingAttributeArray3 = $this->filterIndexAttributes($indexingAttributes, 3);
        $indexingAttribute3 = array_shift($indexingAttributeArray3);
        $this->assertFalse($indexingAttribute3->getIsIndexable());
        $this->assertSame(expected: Actions::NO_ACTION, actual: $indexingAttribute3->getNextAction());

        $indexingAttributeArray4 = $this->filterIndexAttributes($indexingAttributes, 4);
        $indexingAttribute4 = array_shift($indexingAttributeArray4);
        $this->assertTrue($indexingAttribute4->getIsIndexable());
        $this->assertSame(expected: Actions::ADD, actual: $indexingAttribute4->getNextAction());
    }

    public function testExecute_LogsError_WhenSaveExceptionIsThrown(): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);

        $indexingAttribute1 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 1,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::NO_ACTION,
            IndexingAttribute::LAST_ACTION => Actions::ADD,
        ]);
        $indexingAttribute2 = $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => 2,
            IndexingAttribute::IS_INDEXABLE => true,
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::TARGET_ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
            IndexingAttribute::NEXT_ACTION => Actions::DELETE,
            IndexingAttribute::LAST_ACTION => Actions::UPDATE,
        ]);

        $indexingAttributes = $this->getIndexingAttributes(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
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
    }

    /**
     * @param IndexingAttributeInterface[] $indexingAttributes
     *
     * @return int[]
     */
    private function getAttributeIds(array $indexingAttributes): array
    {
        return array_map(
            static fn (IndexingAttributeInterface $indexingAttribute): int => ((int)$indexingAttribute->getId()),
            $indexingAttributes,
        );
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
