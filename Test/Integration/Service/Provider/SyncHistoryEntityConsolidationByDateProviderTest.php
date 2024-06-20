<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Model\SyncHistoryEntityConsolidationRecord;
use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\Indexing\Service\Provider\SyncHistoryEntityConsolidatedByDateProvider;
use Klevu\Indexing\Test\Integration\Traits\SyncHistoryEntitiesConsolidationTrait;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\SyncHistoryEntityConsolidatedByDateProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers SyncHistoryEntityConsolidatedByDateProvider
 * @method SyncHistoryEntityConsolidatedByDateProviderInterface instantiateTestObject(?array $arguments = null)
 * @method SyncHistoryEntityConsolidatedByDateProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SyncHistoryEntityConsolidationByDateProviderTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
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

        $this->implementationFqcn = SyncHistoryEntityConsolidatedByDateProvider::class;
        $this->interfaceFqcn = SyncHistoryEntityConsolidatedByDateProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGet_ReturnsEmptyArray_WhenNoRecords(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $provider = $this->instantiateTestObject();
        $result = $provider->get(date: date(format: 'Y-m-d'));

        $recordsArray = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        $this->assertCount(expectedCount: 0, haystack: $recordsArray);
    }

    public function testGet_ForDateComparitor_Like(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::ADD,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(
                        format: 'Y-m-d H:i:s',
                        timestamp: time() - 48 * 3600,
                    ),
                    SyncHistoryEntityRecord::IS_SUCCESS => true,
                    SyncHistoryEntityRecord::MESSAGE => 'Success',
                ],
                [
                    SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(
                        format: 'Y-m-d H:i:s',
                        timestamp: time() - 48 * 3600,
                    ),
                    SyncHistoryEntityRecord::IS_SUCCESS => false,
                    SyncHistoryEntityRecord::MESSAGE => 'Rejected',
                ],
            ],
            SyncHistoryEntityConsolidationRecord::DATE => date(format: 'Y-m-d', timestamp: time() - 48 * 3600),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 4,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::ADD,
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
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 5,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
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
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 10,
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

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityConsolidatedByDateProvider::LIKE,
        ]);
        $result = $provider->get(date: date(format: 'Y-m-d', timestamp: time() - 48 * 3600));

        $this->assertCount(expectedCount: 3, haystack: $result);

        $categoryRecords = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getTargetEntityType() === 'KLEVU_CATEGORY'
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $categoryRecords);
        /** @var SyncHistoryEntityConsolidationRecordInterface $categoryRecord */
        $categoryRecord = array_shift($categoryRecords);
        $this->assertSame(expected: $apiKey, actual: $categoryRecord->getApiKey());
        $this->assertSame(expected: 5, actual: $categoryRecord->getTargetId());
        $this->assertNull(actual: $categoryRecord->getTargetParentId());
        $this->assertSame(
            expected: date(format: 'Y-m-d', timestamp: time() - 48 * 3600),
            actual: $categoryRecord->getDate(),
        );

        $cmsRecords = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getTargetEntityType() === 'KLEVU_CMS'
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $cmsRecords);
        /** @var SyncHistoryEntityConsolidationRecordInterface $cmsRecord */
        $cmsRecord = array_shift($cmsRecords);
        $this->assertSame(expected: $apiKey, actual: $cmsRecord->getApiKey());
        $this->assertSame(expected: 10, actual: $cmsRecord->getTargetId());
        $this->assertNull(actual: $cmsRecord->getTargetParentId());
        $this->assertSame(
            expected: date(format: 'Y-m-d', timestamp: time() - 48 * 3600),
            actual: $cmsRecord->getDate(),
        );

        $productRecords = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getTargetEntityType() === 'KLEVU_PRODUCT'
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $productRecords);
        /** @var SyncHistoryEntityConsolidationRecordInterface $productRecord */
        $productRecord = array_shift($productRecords);
        $this->assertSame(expected: $apiKey, actual: $productRecord->getApiKey());
        $this->assertSame(expected: 1, actual: $productRecord->getTargetId());
        $this->assertNull(actual: $productRecord->getTargetParentId());
        $this->assertSame(
            expected: date(format: 'Y-m-d', timestamp: time() - 48 * 3600),
            actual: $productRecord->getDate(),
        );

        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);
    }

    public function testGet_ForDateComparitor_NotLike(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

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
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 4,
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

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityConsolidatedByDateProvider::NOT_LIKE,
        ]);
        $result = $provider->get(date: date(format: 'Y-m-d', timestamp: time() - 24 * 3600));

        $productRecords = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getTargetEntityType() === 'KLEVU_PRODUCT'
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $productRecords);
        /** @var SyncHistoryEntityConsolidationRecordInterface $record */
        $record = array_shift($productRecords);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertNull(actual: $record->getTargetParentId());
        $this->assertSame(expected: date(format: 'Y-m-d'), actual: $record->getDate());

        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);
    }

    public function testGet_ForDateComparitor_Equal(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

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
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 4,
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

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityConsolidatedByDateProvider::EQUALS,
        ]);
        $result = $provider->get(date: date(format: 'Y-m-d', timestamp: time() - 24 * 3600));

        $productRecords = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getTargetEntityType() === 'KLEVU_PRODUCT'
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $productRecords);
        /** @var SyncHistoryEntityConsolidationRecordInterface $record */
        $record = array_shift($productRecords);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 3, actual: $record->getTargetId());
        $this->assertSame(expected: 4, actual: $record->getTargetParentId());
        $this->assertSame(expected: date(format: 'Y-m-d', timestamp: time() - 24 * 3600), actual: $record->getDate());
    }

    public function testGet_ForDateComparitor_NotEqual(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

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
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 4,
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

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityConsolidatedByDateProvider::NOT_EQUALS,
        ]);
        $result = $provider->get(date: date(format: 'Y-m-d', timestamp: time() - 24 * 3600));

        $productRecords = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getTargetEntityType() === 'KLEVU_PRODUCT'
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $productRecords);
        /** @var SyncHistoryEntityConsolidationRecordInterface $record */
        $record = array_shift($productRecords);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertNull(actual: $record->getTargetParentId());
        $this->assertSame(expected: date(format: 'Y-m-d'), actual: $record->getDate());
    }

    public function testGet_ForDateComparitor_GreaterThan(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

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
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 4,
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

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityConsolidatedByDateProvider::GREATER_THAN,
        ]);
        $result = $provider->get(date: date(format: 'Y-m-d', timestamp: time() - 24 * 3600));

        $productRecords = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getTargetEntityType() === 'KLEVU_PRODUCT'
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $productRecords);
        /** @var SyncHistoryEntityConsolidationRecordInterface $record */
        $record = array_shift($productRecords);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertNull(actual: $record->getTargetParentId());
        $this->assertSame(expected: date(format: 'Y-m-d'), actual: $record->getDate());
    }

    public function testGet_ForDateComparitor_GreaterThanOrEqual(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
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
                [
                    SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(
                        format: 'Y-m-d H:i:s',
                        timestamp: time() - 24 * 3600,
                    ),
                    SyncHistoryEntityRecord::IS_SUCCESS => false,
                    SyncHistoryEntityRecord::MESSAGE => 'Rejected',
                ],
            ],
            SyncHistoryEntityConsolidationRecord::DATE => date(format: 'Y-m-d', timestamp: time() - 24 * 3600),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 4,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::ADD,
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

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityConsolidatedByDateProvider::GREATER_THAN_OR_EQUALS,
        ]);
        $result = $provider->get(date: date(format: 'Y-m-d', timestamp: time() - 24 * 3600));

        $productRecords = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getTargetEntityType() === 'KLEVU_PRODUCT'
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $productRecords);
        /** @var SyncHistoryEntityConsolidationRecordInterface $record */
        $record = array_shift($productRecords);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertNull(actual: $record->getTargetParentId());
        $this->assertSame(expected: date(format: 'Y-m-d', timestamp: time() - 24 * 3600), actual: $record->getDate());
    }

    public function testGet_ForDateComparitor_LessThan(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
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
                [
                    SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(
                        format: 'Y-m-d H:i:s',
                        timestamp: time() - 24 * 3600,
                    ),
                    SyncHistoryEntityRecord::IS_SUCCESS => false,
                    SyncHistoryEntityRecord::MESSAGE => 'Rejected',
                ],
            ],
            SyncHistoryEntityConsolidationRecord::DATE => date(format: 'Y-m-d', timestamp: time() - 24 * 3600),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 4,
            SyncHistoryEntityConsolidationRecord::HISTORY => [
                [
                    SyncHistoryEntityRecord::ACTION => Actions::ADD,
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

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityConsolidatedByDateProvider::LESS_THAN,
        ]);
        $result = $provider->get(date: date(format: 'Y-m-d', timestamp: time() - 24 * 3600));

        $productRecords = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getTargetEntityType() === 'KLEVU_PRODUCT'
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $productRecords);
        /** @var SyncHistoryEntityConsolidationRecordInterface $record */
        $record = array_shift($productRecords);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 3, actual: $record->getTargetId());
        $this->assertSame(expected: 4, actual: $record->getTargetParentId());
        $this->assertSame(expected: date(format: 'Y-m-d', timestamp: time() - 48 * 3600), actual: $record->getDate());

        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);
    }

    public function testGet_ForDateComparitor_LessThanOrEqual(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);

        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 1,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => null,
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
                [
                    SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
                    SyncHistoryEntityRecord::ACTION_TIMESTAMP => date(
                        format: 'Y-m-d H:i:s',
                        timestamp: time() - 24 * 3600,
                    ),
                    SyncHistoryEntityRecord::IS_SUCCESS => false,
                    SyncHistoryEntityRecord::MESSAGE => 'Rejected',
                ],
            ],
            SyncHistoryEntityConsolidationRecord::DATE => date(format: 'Y-m-d', timestamp: time() - 24 * 3600),
        ]);
        $this->createSyncHistoryConsolidationEntity([
            SyncHistoryEntityConsolidationRecord::API_KEY => 'klevu-js-api-key',
            SyncHistoryEntityConsolidationRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityConsolidationRecord::TARGET_ID => 3,
            SyncHistoryEntityConsolidationRecord::TARGET_PARENT_ID => 4,
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
            SyncHistoryEntityConsolidationRecord::DATE => date(format: 'Y-m-d', timestamp: time() - 48 * 3600),
        ]);

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityConsolidatedByDateProvider::LESS_THAN_OR_EQUALS,
        ]);
        $result = $provider->get(date: date(format: 'Y-m-d', timestamp: time() - 48 * 3600));

        $productRecords = array_filter(
            array: $result,
            callback: static fn (SyncHistoryEntityConsolidationRecordInterface $record): bool => (
                $record->getTargetEntityType() === 'KLEVU_PRODUCT'
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $productRecords);
        /** @var SyncHistoryEntityConsolidationRecordInterface $record */
        $record = array_shift($productRecords);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 3, actual: $record->getTargetId());
        $this->assertSame(expected: 4, actual: $record->getTargetParentId());
        $this->assertSame(expected: date(format: 'Y-m-d', timestamp: time() - 48 * 3600), actual: $record->getDate());

        $this->clearSyncHistoryConsolidationEntities(apiKey: $apiKey);
    }
}
