<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\RecordIndexingEntityHistoryService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\Indexing\Test\Integration\Traits\SyncHistoryEntitiesTrait;
use Klevu\IndexingApi\Api\SyncHistoryEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\BatchResponderServiceInterface;
use Klevu\PhpSDKPipelines\Model\ApiPipelineResult;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers RecordIndexingEntityHistoryService
 * @method BatchResponderServiceInterface instantiateTestObject(?array $arguments = null)
 * @method BatchResponderServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class RecordIndexingEntityHistoryServiceTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use SyncHistoryEntitiesTrait;
    use TestImplementsInterfaceTrait;

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

        $this->implementationFqcn = RecordIndexingEntityHistoryService::class;
        $this->interfaceFqcn = BatchResponderServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_NOOP_WhenNoIndexingEntitiesProvided(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities($apiKey);
        $this->clearSyncHistoryEntities($apiKey);

        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'messages' => ['Batch accepted successfully'],
        ]);

        $service = $this->instantiateTestObject();
        $service->execute(
            apiPipelineResult: $mockApiResult,
            action: Actions::ADD,
            indexingEntities: [],
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );

        $records = $this->getIndexingEntityHistory(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $this->assertCount(expectedCount: 0, haystack: $records);
        $this->clearSyncHistoryEntities(apiKey: $apiKey);
        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    public function testExecute_RecordsEntityHistory(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities($apiKey);
        $this->clearSyncHistoryEntities($apiKey);

        $indexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'messages' => ['Batch accepted successfully'],
        ]);

        $service = $this->instantiateTestObject();
        $service->execute(
            apiPipelineResult: $mockApiResult,
            action: Actions::ADD,
            indexingEntities: [
                $indexingEntity,
            ],
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );

        $records = $this->getIndexingEntityHistory(type: 'KLEVU_PRODUCT', apiKey: $apiKey);
        $this->assertCount(expectedCount: 1, haystack: $records);
        foreach ($records as $record) {
            $this->assertTrue(condition: $record->getIsSuccess());
            $this->assertSame(expected: 'Batch accepted successfully', actual: $record->getMessage());
            $this->assertSame(expected: Actions::ADD, actual: $record->getAction());
            $this->assertNotNull(actual: $record->getActionTimestamp());
            $this->assertSame(expected: $indexingEntity->getTargetId(), actual: $record->getTargetId());
            $this->assertSame(expected: $indexingEntity->getTargetParentId(), actual: $record->getTargetParentId());
            $this->assertSame(expected: $indexingEntity->getTargetEntityType(), actual: $record->getTargetEntityType());
            $this->assertSame(expected: $indexingEntity->getApiKey(), actual: $record->getApiKey());
        }
        $this->clearSyncHistoryEntities(apiKey: $apiKey);
        $this->cleanIndexingEntities(apiKey: $apiKey);
    }

    public function testExecute_LogsError_WhenSyncHistorySaveThrowsException(): void
    {
        $apiKey = 'klevu-test-api-key';
        $this->cleanIndexingEntities($apiKey);
        $this->clearSyncHistoryEntities($apiKey);

        $indexingEntity = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 123,
            IndexingEntity::TARGET_PARENT_ID => 456,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);

        $exception = new LocalizedException(__('An Exception Occurred'));

        $mockSyncHistoryRepository = $this->getMockBuilder(SyncHistoryEntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockSyncHistoryRepository->expects($this->once())
            ->method('save')
            ->willThrowException($exception);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method}, Error: {message}',
                [
                    'method' => "Klevu\Indexing\Service\RecordIndexingEntityHistoryService::saveHistoryRecord",
                    'message' => 'An Exception Occurred',
                ],
            );

        $mockApiResult = $this->objectManager->create(ApiPipelineResult::class, [
            'success' => true,
            'messages' => ['Batch accepted successfully'],
        ]);

        $service = $this->instantiateTestObject([
            'syncHistoryEntityRepository' => $mockSyncHistoryRepository,
            'logger' => $mockLogger,
        ]);
        $service->execute(
            apiPipelineResult: $mockApiResult,
            action: Actions::ADD,
            indexingEntities: [
                $indexingEntity,
            ],
            entityType: 'KLEVU_PRODUCT',
            apiKey: $apiKey,
        );
    }
}
