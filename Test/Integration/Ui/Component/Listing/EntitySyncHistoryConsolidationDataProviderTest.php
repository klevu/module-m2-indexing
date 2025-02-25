<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Ui\Component\Listing;

use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\Indexing\Test\Integration\Traits\SyncHistoryEntitiesConsolidationTrait;
use Klevu\Indexing\Ui\Component\Listing\EntitySyncHistoryConsolidationDataProvider;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers EntitySyncHistoryConsolidationDataProvider
 * @method DataProviderInterface instantiateTestObject(?array $arguments = null)
 * @method DataProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntitySyncHistoryConsolidationDataProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
    use SyncHistoryEntitiesConsolidationTrait;
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

        $this->implementationFqcn = EntitySyncHistoryConsolidationDataProvider::class;
        $this->interfaceFqcn = DataProviderInterface::class;
        $this->constructorArgumentDefaults = [
            'name' => 'klevu_entity_sync_history_consolidation',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
            'entityType' => 'KLEVU_PRODUCT',
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGetData_ReturnsArray_WhenTargetIdNotSetInRequest(): void
    {
        $dataProvider = $this->instantiateTestObject();
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key: 'totalRecords', array: $result);
        $this->assertSame(expected: 0, actual: $result['totalRecords']);
        $this->assertArrayHasKey(key:'items', array: $result);
        $this->assertCount(expectedCount: 0, haystack: $result['items']);
    }

    public function testGetData_ReturnsArray_WhenNoHistoryRecords(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $request = $this->objectManager->get(RequestInterface::class);
        $request->setParams(['target_id' => 1]);

        $dataProvider = $this->instantiateTestObject([
            'name' => 'klevu_entity_sync_history',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
            'request' => $request,
            'entityType' => 'KLEVU_PRODUCT',
        ]);
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key:'items', array: $result);
        $recordsArray = array_filter(
            array: $result['items'],
            callback: static fn (array $record): bool => (
                $record[SyncHistoryEntityConsolidationRecord::API_KEY] === $apiKey
            ),
        );
        $this->assertCount(expectedCount: 0, haystack:$recordsArray);
    }

    public function testGetData_ReturnsOnlyProductHistoryForTargetId(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => $apiKey,
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2024-05-18 04:07:30',
                    SyncHistoryEntityRecord::ACTION => Actions::ADD,
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Accepted',
                ],
                [
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2024-05-18 05:12:42',
                    SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                    SyncHistoryEntityRecord::IS_SUCCESS => false,
                    SyncHistoryEntityRecord::MESSAGE => 'Rejected',
                ],
            ],
            SyncHistoryEntityConsolidationRecord::DATE => date('Y-m-d'),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => $apiKey,
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 2,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => '2024-05-18 06:32:21',
                    SyncHistoryEntityRecord::ACTION => Actions::ADD,
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Accepted',
                ],

            ],
            SyncHistoryEntityConsolidationRecord::DATE => date('Y-m-d'),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => $apiKey,
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s', timestamp: time() - 3600),
                    SyncHistoryEntityRecord::ACTION => Actions::DELETE,
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Success',
                ],

            ],
            SyncHistoryEntityConsolidationRecord::DATE => date('Y-m-d'),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                    SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Accepted',
                ],
            ],
            SyncHistoryEntityConsolidationRecord::DATE => date('Y-m-d'),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => $apiKey,
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_CAMS',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(format: 'Y-m-d H:i:s'),
                    SyncHistoryEntityRecord::ACTION => Actions::DELETE,
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Accepted',
                ],
            ],
            SyncHistoryEntityConsolidationRecord::DATE => date('Y-m-d'),
        ]);

        $request = $this->objectManager->get(RequestInterface::class);
        $request->setParams(['target_id' => 1]);

        $dataProvider = $this->instantiateTestObject([
            'name' => 'klevu_entity_sync_history',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
            'request' => $request,
            'entityType' => 'KLEVU_PRODUCT',
        ]);
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key: 'totalRecords', array: $result);
        $this->assertSame(expected: 2, actual: $result['totalRecords']);

        $this->assertArrayHasKey(key:'items', array: $result);
        $this->assertCount(expectedCount: 2, haystack: $result['items']);

        $record1Array = array_filter(
            array: $result['items'],
            callback: static fn (array $record): bool => (
                $record[SyncHistoryEntityConsolidationRecord::API_KEY] === $apiKey
                && $record[SyncHistoryEntityConsolidationRecord::TARGET_ID] === 1
                && $record[SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID] === null
            ),
        );
        $record1 = array_shift($record1Array);
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $record1[SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE] ?? null,
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $record1[SyncHistoryEntityConsolidationRecord::API_KEY] ?? null,
        );
        $this->assertSame(
            expected: 1,
            actual: $record1[SyncHistoryEntityConsolidationRecord::TARGET_ID] ?? null,
        );
        $this->assertNull($record1[SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID]);
        $this->assertSame(
            expected: date('M j, Y'),
            actual: $record1[SyncHistoryEntityConsolidationRecord::DATE] ?? null,
        );
        $history1 = $record1[SyncHistoryEntityConsolidationRecord::HISTORY]
            ? str_replace("\u{202F}", ' ', $record1[SyncHistoryEntityConsolidationRecord::HISTORY])
            : null;
        $this->assertSame(
            expected: 'May 18, 2024, 4:07:30 AM - Add - Success - Accepted'
                . '<br/>'
                . 'May 18, 2024, 5:12:42 AM - Update - Failed - Rejected'
                . '<br/>',
            actual: $history1,
        );

        $record2Array = array_filter(
            array: $result['items'],
            callback: static fn (array $record): bool => (
                $record[SyncHistoryEntityConsolidationRecord::TARGET_ID] === 1
                && $record[SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID] === 2
            ),
        );
        $record2 = array_shift($record2Array);
        $this->assertSame(
            expected: 'KLEVU_PRODUCT',
            actual: $record2[SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE] ?? null,
        );
        $this->assertSame(
            expected: $apiKey,
            actual: $record2[SyncHistoryEntityConsolidationRecord::API_KEY] ?? null,
        );
        $this->assertSame(
            expected: 1,
            actual: $record2[SyncHistoryEntityConsolidationRecord::TARGET_ID] ?? null,
        );
        $this->assertSame(
            expected: 2,
            actual: $record2[SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID],
        );

        $this->assertSame(
            expected: date('M j, Y'),
            actual: $record2[SyncHistoryEntityConsolidationRecord::DATE] ?? null,
        );
        $history2 = $record2[SyncHistoryEntityConsolidationRecord::HISTORY]
            ? str_replace("\u{202F}", ' ', $record2[SyncHistoryEntityConsolidationRecord::HISTORY])
            : null;
        $this->assertSame(
            expected: 'May 18, 2024, 6:32:21 AM - Add - Success - Accepted<br/>',
            actual: $history2,
        );

        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);
    }

    public function testGetData_ReturnsInjectedDateFormat(): void
    {
        $time = time();
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => $apiKey,
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 2,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date('Y-m-d H:i:s', $time),
                    SyncHistoryEntityRecord::ACTION => Actions::ADD,
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Accepted',
                ],

            ],
            SyncHistoryEntityConsolidationRecord::DATE => date('Y-m-d', $time),
        ]);

        $request = $this->objectManager->get(RequestInterface::class);
        $request->setParams(['target_id' => 1]);

        $dataProvider = $this->instantiateTestObject([
            'name' => 'klevu_entity_sync_history',
            'primaryFieldName' => 'entity_id',
            'requestFieldName' => 'target_id',
            'request' => $request,
            'entityType' => 'KLEVU_PRODUCT',
            'dateFormat' => \IntlDateFormatter::SHORT,
        ]);
        $result = $dataProvider->getData();

        $this->assertArrayHasKey(key:'items', array: $result);
        $recordsArray = array_filter(
            array: $result['items'],
            callback: static fn (array $record): bool => (
                $record[SyncHistoryEntityConsolidationRecord::API_KEY] === $apiKey
            ),
        );
        $this->assertCount(expectedCount: 1, haystack:$recordsArray);

        $record = array_shift($recordsArray);

        $this->assertSame(
            expected: date('n/j/y', $time),
            actual: $record[SyncHistoryEntityConsolidationRecord::DATE] ?? null,
        );
        $history = $record[SyncHistoryEntityConsolidationRecord::HISTORY]
            ? str_replace("\u{202F}", ' ', $record[SyncHistoryEntityConsolidationRecord::HISTORY])
            : null;
        $this->assertSame(
            expected: sprintf('%s - Add - Success - Accepted<br/>', date('n/j/y, g:i:s A', $time)),
            actual: $history,
        );

        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);
    }
}
