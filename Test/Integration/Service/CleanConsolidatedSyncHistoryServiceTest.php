<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\Indexing\Service\CleanConsolidatedSyncHistoryService;
use Klevu\Indexing\Test\Integration\Traits\SyncHistoryEntitiesConsolidationTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\CleanConsolidatedSyncHistoryServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers CleanConsolidatedSyncHistoryService
 * @method CleanConsolidatedSyncHistoryServiceInterface instantiateTestObject(?array $arguments = null)
 * @method CleanConsolidatedSyncHistoryServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CleanConsolidatedSyncHistoryServiceTest extends TestCase
{
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = CleanConsolidatedSyncHistoryService::class;
        $this->interfaceFqcn = CleanConsolidatedSyncHistoryServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_DoesNothing_WhenNoRecordsPresent(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $service = $this->instantiateTestObject();
        $service->execute();

        $productConsolidationEntities = $this->getIndexingEntityHistoryConsolidation(
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 0, haystack: $productConsolidationEntities);

        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);
    }

    public function testExecute_DoesNothing_WhenNoRecordsOlderThanLimit(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $this->createSyncHistoryConsolidationEntity();

        $productConsolidationEntities = $this->getIndexingEntityHistoryConsolidation(
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 1, haystack: $productConsolidationEntities);

        $service = $this->instantiateTestObject();
        $service->execute();

        $productConsolidationEntities = $this->getIndexingEntityHistoryConsolidation(
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 1, haystack: $productConsolidationEntities);

        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_RemovesRecordsOlderThanLimit(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        ConfigFixture::setGlobal(
            path: 'klevu/indexing/remove_indexing_history_after_days',
            value: 2,
        );

        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::ADD,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Success',
                ],
                [
                    SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                    SyncHistoryEntityRecord::IS_SUCCESS => false,
                    SyncHistoryEntityRecord::MESSAGE => 'Rejected',
                ],
            ],
            SyncHistoryEntityConsolidationRecord::DATE => date(format: 'Y-m-d'),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 2,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::ADD,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(
                        format: 'Y-m-d H:i:s',
                        timestamp: time() - 24 * 3600,
                    ),
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Success',
                ],

            ],
            SyncHistoryEntityConsolidationRecord::DATE => date(format: 'Y-m-d', timestamp: time() - 24 * 3600),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::DELETE,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(
                        format: 'Y-m-d H:i:s',
                        timestamp: time() - 48 * 3600,
                    ),
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Success',
                ],

            ],
            SyncHistoryEntityConsolidationRecord::DATE => date(format: 'Y-m-d', timestamp: time() - 48 * 3600),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(
                        format: 'Y-m-d H:i:s',
                        timestamp: time() - 72 * 3600,
                    ),
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Success',
                ],
            ],
            SyncHistoryEntityConsolidationRecord::DATE => date(format: 'Y-m-d', timestamp: time() - 72 * 3600),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::DELETE,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(
                        format: 'Y-m-d H:i:s',
                        timestamp: time() - 36 * 3600,
                    ),
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Success',
                ],
            ],
            SyncHistoryEntityConsolidationRecord::DATE => date(format: 'Y-m-d', timestamp: time() - 36 * 3600),
        ]);

        $productConsolidationEntities = $this->getIndexingEntityHistoryConsolidation(
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 5, haystack: $productConsolidationEntities);

        $service = $this->instantiateTestObject();
        $service->execute();

        $allConsolidationEntities = $this->getIndexingEntityHistoryConsolidation(
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 4, haystack: $allConsolidationEntities);

        $categoryConsolidationEntities = $this->getIndexingEntityHistoryConsolidation(
            type: 'KLEVU_CATEGORY',
            apiKey: $apiKey,
        );
        $this->assertCount(expectedCount: 0, haystack: $categoryConsolidationEntities);

        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);
    }
}
