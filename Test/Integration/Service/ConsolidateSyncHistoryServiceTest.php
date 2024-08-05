<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\Indexing\Service\ConsolidateSyncHistoryService;
use Klevu\Indexing\Test\Integration\Traits\SyncHistoryEntitiesConsolidationTrait;
use Klevu\Indexing\Test\Integration\Traits\SyncHistoryEntitiesTrait;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityConsolidationRepositoryInterface;
use Klevu\IndexingApi\Api\SyncHistoryEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\ConsolidateSyncHistoryServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers ConsolidateSyncHistoryService
 * @method ConsolidateSyncHistoryServiceInterface instantiateTestObject(?array $arguments = null)
 * @method ConsolidateSyncHistoryServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ConsolidateSyncHistoryServiceTest extends TestCase
{
    use ObjectInstantiationTrait;
    use SyncHistoryEntitiesTrait;
    use SyncHistoryEntitiesConsolidationTrait;
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

        $this->implementationFqcn = ConsolidateSyncHistoryService::class;
        $this->interfaceFqcn = ConsolidateSyncHistoryServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_DoesNothingWhenNoHistoryForDate(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $service = $this->instantiateTestObject();
        $service->execute();

        $productConsolidationEntities = $this->getIndexingEntityHistoryConsolidation(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 0, haystack: $productConsolidationEntities);

        $this->clearSyncHistoryEntities(apiKey: $apiKey);
    }

    public function testExecute_ConsolidatesHistory(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $timestamp = date('Y-m-d H:i:s', time() - (24 * 60 * 60));

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Batch accepted successfully',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => false,
            SyncHistoryEntityRecord::MESSAGE => 'Batch rejected',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => 2,
            SyncHistoryEntityRecord::ACTION => Actions::DELETE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Batch accepted successfully',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 3,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2023-12-12 12:12:12',
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Batch accepted successfully',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            SyncHistoryEntityRecord::TARGET_ID => 5,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Batch accepted successfully',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityRecord::TARGET_ID => 10,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::DELETE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => false,
            SyncHistoryEntityRecord::MESSAGE => 'Batch rejected',
        ]);

        $service = $this->instantiateTestObject();
        $service->execute();

        $productConsolidationEntities = $this->getIndexingEntityHistoryConsolidation(
            type: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 3, haystack: $productConsolidationEntities);

        $record1Array = array_filter(
            array: $productConsolidationEntities,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                null === $record->getTargetParentId()
            ),
        );
        $record1 = array_shift($record1Array);
        $this->assertSame(expected: $apiKey, actual: $record1->getApiKey());
        $this->assertSame(expected: 1, actual: $record1->getTargetId());
        $this->assertNull(actual: $record1->getTargetParentId());
        $this->assertSame(expected: 'KLEVU_PRODUCT', actual: $record1->getTargetEntityType());
        $this->assertSame(
            expected: date('Y-m-d', time() - (24 * 60 * 60)),
            actual: $record1->getDate(),
        );
        $expectedHistory = '['
            . '{"action_timestamp":"%s","action":"Add","is_success":true,"message":"Batch accepted successfully"},'
            . '{"action_timestamp":"%s","action":"Update","is_success":false,"message":"Batch rejected"}'
            . ']';
        $this->assertSame(
            expected: sprintf(
                $expectedHistory,
                $timestamp,
                $timestamp,
            ),
            actual: $record1->getHistory(),
        );

        $record2Array = array_filter(
            array: $productConsolidationEntities,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                2 === $record->getTargetParentId()
            ),
        );
        $record2 = array_shift($record2Array);
        $this->assertSame(expected: $apiKey, actual: $record2->getApiKey());
        $this->assertSame(expected: 1, actual: $record2->getTargetId());
        $this->assertSame(expected: 2, actual: $record2->getTargetParentId());
        $this->assertSame(expected: 'KLEVU_PRODUCT', actual: $record2->getTargetEntityType());
        $this->assertSame(
            expected: date('Y-m-d', time() - (24 * 60 * 60)),
            actual: $record2->getDate(),
        );
        $expectedHistory = '['
            . '{"action_timestamp":"%s","action":"Delete","is_success":true,"message":"Batch accepted successfully"}'
            . ']';
        $this->assertSame(
            expected: sprintf(
                $expectedHistory,
                $timestamp,
            ),
            actual: $record2->getHistory(),
        );

        $record3Array = array_filter(
            array: $productConsolidationEntities,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                3 === $record->getTargetId()
            ),
        );
        $record3 = array_shift($record3Array);
        $this->assertSame(expected: $apiKey, actual: $record3->getApiKey());
        $this->assertSame(expected: 3, actual: $record3->getTargetId());
        $this->assertNull(actual: $record3->getTargetParentId());
        $this->assertSame(expected: 'KLEVU_PRODUCT', actual: $record3->getTargetEntityType());
        $this->assertSame(
            expected: '2023-12-12',
            actual: $record3->getDate(),
        );
        $expectedHistory = '[{'
            . '"action_timestamp":"2023-12-12 12:12:12",'
            . '"action":"Add",'
            . '"is_success":true,'
            . '"message":"Batch accepted successfully"'
            . '}]';
        $this->assertSame(
            expected: $expectedHistory,
            actual: $record3->getHistory(),
        );

        $syncRecords = $this->getIndexingEntityHistory(apiKey: $apiKey);
        $this->assertCount(expectedCount: 0, haystack: $syncRecords);

        $this->clearSyncHistoryEntities(apiKey: $apiKey);
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);
    }

    public function testExecute_logsError_WhenLocalizedExceptionThrown(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $timestamp = date('Y-m-d H:i:s', time() - (24 * 60 * 60));

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityRecord::TARGET_ID => 10,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::DELETE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => false,
            SyncHistoryEntityRecord::MESSAGE => 'Batch rejected',
        ]);

        $exceptionMessage = 'Something went wrong';
        $exception = new LocalizedException(__($exceptionMessage));

        $mockSyncConsolidationRepo = $this->getMockBuilder(
            className: SyncHistoryEntityConsolidationRepositoryInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();
        $mockSyncConsolidationRepo->method('save')
            ->willThrowException($exception);

        $mockSyncRepo = $this->getMockBuilder(SyncHistoryEntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockSyncRepo->expects($this->never())
            ->method('deleteById');

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\ConsolidateSyncHistoryService::persistConsolidationData',
                    'message' => $exceptionMessage,
                ],
            );

        $service = $this->instantiateTestObject([
            'syncHistoryEntityConsolidationRepository' => $mockSyncConsolidationRepo,
            'syncHistoryEntityRepository' => $mockSyncRepo,
            'logger' => $mockLogger,
        ]);
        $service->execute();
    }
}
