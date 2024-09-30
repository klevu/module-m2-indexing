<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Model\SyncHistoryEntityRecord;
use Klevu\Indexing\Service\Provider\SyncHistoryEntityByDateProvider;
use Klevu\Indexing\Test\Integration\Traits\SyncHistoryEntitiesTrait;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Provider\SyncHistoryEntityByDateProviderInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers SyncHistoryEntityByDateProvider
 * @method SyncHistoryEntityByDateProviderInterface instantiateTestObject(?array $arguments = null)
 * @method SyncHistoryEntityByDateProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class SyncHistoryEntityByDateProviderTest extends TestCase
{
    use ObjectInstantiationTrait;
    use SyncHistoryEntitiesTrait;
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

        $this->implementationFqcn = SyncHistoryEntityByDateProvider::class;
        $this->interfaceFqcn = SyncHistoryEntityByDateProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testGet_ReturnsEmptyArray_WhenNoRecords(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $provider = $this->instantiateTestObject();
        $result = $provider->get(date('Y-m-d'));
        if (count($result)) {
            if (array_key_exists(key: 'KLEVU_PRODUCT', array: $result)) {
                $this->assertCount(
                    expectedCount: 0,
                    haystack: array_filter(
                        array: $result['KLEVU_PRODUCT'],
                        callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                            $record->getApiKey() === $apiKey
                        ),
                    ),
                );
            }
            if (array_key_exists(key: 'KLEVU_CATEGORY', array: $result)) {
                $this->assertCount(
                    expectedCount: 0,
                    haystack: array_filter(
                        array: $result['KLEVU_CATEGORY'],
                        callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                            $record->getApiKey() === $apiKey
                        ),
                    ),
                );
            }
            if (array_key_exists(key: 'KLEVU_CMS', array: $result)) {
                $this->assertCount(
                    expectedCount: 0,
                    haystack: array_filter(
                        array: $result['KLEVU_CMS'],
                        callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                            $record->getApiKey() === $apiKey
                        ),
                    ),
                );
            }
        } else {
            $this->assertCount(expectedCount: 0, haystack: $result);
        }
    }

    public function testGet_ForDateComparitor_Like(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Batch accepted successfully',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => 2,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            SyncHistoryEntityRecord::IS_SUCCESS => false,
            SyncHistoryEntityRecord::MESSAGE => 'Batch rejected',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            SyncHistoryEntityRecord::TARGET_ID => 5,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Batch accepted successfully',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            SyncHistoryEntityRecord::TARGET_ID => 10,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::DELETE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            SyncHistoryEntityRecord::IS_SUCCESS => false,
            SyncHistoryEntityRecord::MESSAGE => 'Batch rejected',
        ]);

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityByDateProvider::LIKE,
        ]);
        $result = $provider->get(date('Y-m-d'));

        $this->assertArrayHasKey(key: 'KLEVU_CATEGORY', array: $result);
        $records = array_filter(
            array: $result['KLEVU_CATEGORY'],
            callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $records);
        /** @var SyncHistoryEntityRecordInterface $categoryRecord */
        $categoryRecord = array_shift($records);
        $this->assertSame(expected: $apiKey, actual: $categoryRecord->getApiKey());
        $this->assertSame(expected: 5, actual: $categoryRecord->getTargetId());
        $this->assertSame(expected: Actions::UPDATE, actual: $categoryRecord->getAction());
        $this->assertTrue(condition: $categoryRecord->getIsSuccess());
        $this->assertSame(expected: 'Batch accepted successfully', actual: $categoryRecord->getMessage());

        $this->assertArrayHasKey(key: 'KLEVU_CMS', array: $result);
        $records = array_filter(
            array: $result['KLEVU_CMS'],
            callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        $this->assertCount(expectedCount: 1, haystack: $records);
        /** @var SyncHistoryEntityRecordInterface $cmsRecord */
        $cmsRecord = array_shift($records);
        $this->assertSame(expected: $apiKey, actual: $cmsRecord->getApiKey());
        $this->assertSame(expected: 5, actual: $categoryRecord->getTargetId());
        $this->assertSame(expected: Actions::DELETE, actual: $cmsRecord->getAction());
        $this->assertFalse(condition: $cmsRecord->getIsSuccess());
        $this->assertSame(expected: 'Batch rejected', actual: $cmsRecord->getMessage());

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result);
        $records = array_filter(
            array: $result['KLEVU_PRODUCT'],
            callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        $this->assertCount(expectedCount: 2, haystack: $records);

        $this->clearSyncHistoryEntities(apiKey: $apiKey);
    }

    public function testGet_ForDateComparitor_NotLike(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $timestamp = date('Y-m-d H:i:s');
        $timestamp2 = date('Y-m-d H:i:s', time() - 24 * 60 * 60);

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should NOT be returned',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp2,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should be returned',
        ]);

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityByDateProvider::NOT_LIKE,
        ]);
        $result = $provider->get(date('Y-m-d'));

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result);
        $records = array_filter(
            array: $result['KLEVU_PRODUCT'],
            callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        /** @var SyncHistoryEntityRecordInterface $record */
        $record = array_shift($records);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertSame(expected: Actions::UPDATE, actual: $record->getAction());
        $this->assertSame(expected: $timestamp2, actual: $record->getActionTimestamp());
        $this->assertTrue(condition: $record->getIsSuccess());
        $this->assertSame(expected: 'Should be returned', actual: $record->getMessage());

        $this->clearSyncHistoryEntities(apiKey: $apiKey);
    }

    public function testGet_ForDateComparitor_Equal(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $timestamp = date('Y-m-d H:i:s');

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should be returned',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => date('Y-m-d H:i:s', time() - 24 * 60 * 60),
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should not be returned',
        ]);

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityByDateProvider::EQUALS,
        ]);
        $result = $provider->get($timestamp);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result);
        $records = array_filter(
            array: $result['KLEVU_PRODUCT'],
            callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        /** @var SyncHistoryEntityRecordInterface $record */
        $record = array_shift($records);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertSame(expected: Actions::ADD, actual: $record->getAction());
        $this->assertSame(expected: $timestamp, actual: $record->getActionTimestamp());
        $this->assertTrue(condition: $record->getIsSuccess());
        $this->assertSame(expected: 'Should be returned', actual: $record->getMessage());
    }

    public function testGet_ForDateComparitor_NotEqual(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $timestamp = date('Y-m-d H:i:s');
        $timestamp2 = date('Y-m-d H:i:s', time() - 24 * 60 * 60);

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should NOT be returned',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp2,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should be returned',
        ]);

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityByDateProvider::NOT_EQUALS,
        ]);
        $result = $provider->get($timestamp);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result);
        $records = array_filter(
            array: $result['KLEVU_PRODUCT'],
            callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        /** @var SyncHistoryEntityRecordInterface $record */
        $record = array_shift($records);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertSame(expected: Actions::UPDATE, actual: $record->getAction());
        $this->assertSame(expected: $timestamp2, actual: $record->getActionTimestamp());
        $this->assertTrue(condition: $record->getIsSuccess());
        $this->assertSame(expected: 'Should be returned', actual: $record->getMessage());
    }

    public function testGet_ForDateComparitor_GreaterThan(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $timestamp = date('Y-m-d H:i:s');
        $timestamp2 = date('Y-m-d H:i:s', time() - 24 * 60 * 60);

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should be returned',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp2,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should not be returned',
        ]);

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityByDateProvider::GREATER_THAN,
        ]);
        $result = $provider->get($timestamp2);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result);
        $records = array_filter(
            array: $result['KLEVU_PRODUCT'],
            callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        /** @var SyncHistoryEntityRecordInterface $record */
        $record = array_shift($records);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertSame(expected: Actions::ADD, actual: $record->getAction());
        $this->assertSame(expected: $timestamp, actual: $record->getActionTimestamp());
        $this->assertTrue(condition: $record->getIsSuccess());
        $this->assertSame(expected: 'Should be returned', actual: $record->getMessage());
    }

    public function testGet_ForDateComparitor_GreaterThanOrEqual(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $timestamp = date('Y-m-d H:i:s');
        $timestamp2 = date('Y-m-d H:i:s', time() - 24 * 60 * 60);

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should be returned',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp2,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should not be returned',
        ]);

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityByDateProvider::GREATER_THAN_OR_EQUALS,
        ]);
        $result = $provider->get($timestamp);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result);
        $records = array_filter(
            array: $result['KLEVU_PRODUCT'],
            callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        /** @var SyncHistoryEntityRecordInterface $record */
        $record = array_shift($records);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertSame(expected: Actions::ADD, actual: $record->getAction());
        $this->assertSame(expected: $timestamp, actual: $record->getActionTimestamp());
        $this->assertTrue(condition: $record->getIsSuccess());
        $this->assertSame(expected: 'Should be returned', actual: $record->getMessage());
    }

    public function testGet_ForDateComparitor_LessThan(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $timestamp1 = date('Y-m-d H:i:s');
        $timestamp2 = date('Y-m-d H:i:s', time() - 24 * 60 * 60);

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp1,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should NOT be returned',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp2,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should be returned',
        ]);

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityByDateProvider::LESS_THAN,
        ]);
        $result = $provider->get($timestamp1);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result);
        $records = array_filter(
            array: $result['KLEVU_PRODUCT'],
            callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        /** @var SyncHistoryEntityRecordInterface $record */
        $record = array_shift($records);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertSame(expected: Actions::UPDATE, actual: $record->getAction());
        $this->assertSame(expected: $timestamp2, actual: $record->getActionTimestamp());
        $this->assertTrue(condition: $record->getIsSuccess());
        $this->assertSame(expected: 'Should be returned', actual: $record->getMessage());
    }

    public function testGet_ForDateComparitor_LessThanOrEqual(): void
    {
        $apiKey = 'klevu-js-api-key';
        $this->clearSyncHistoryEntities(apiKey: $apiKey);

        $timestamp1 = date('Y-m-d H:i:s');
        $timestamp2 = date('Y-m-d H:i:s', time() - 24 * 60 * 60);

        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::ADD,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp1,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should NOT be returned',
        ]);
        $this->createSyncHistoryEntity([
            SyncHistoryEntityRecord::API_KEY => $apiKey,
            SyncHistoryEntityRecord::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            SyncHistoryEntityRecord::TARGET_ID => 1,
            SyncHistoryEntityRecord::TARGET_PARENT_ID => null,
            SyncHistoryEntityRecord::ACTION => Actions::UPDATE,
            SyncHistoryEntityRecord::ACTION_TIMESTAMP => $timestamp2,
            SyncHistoryEntityRecord::IS_SUCCESS => true,
            SyncHistoryEntityRecord::MESSAGE => 'Should be returned',
        ]);

        $provider = $this->instantiateTestObject([
            'dateComparator' => SyncHistoryEntityByDateProvider::LESS_THAN_OR_EQUALS,
        ]);
        $result = $provider->get($timestamp2);

        $this->assertArrayHasKey(key: 'KLEVU_PRODUCT', array: $result);
        $records = array_filter(
            array: $result['KLEVU_PRODUCT'],
            callback: static fn (SyncHistoryEntityRecordInterface $record): bool => (
                $record->getApiKey() === $apiKey
            ),
        );
        /** @var SyncHistoryEntityRecordInterface $record */
        $record = array_shift($records);
        $this->assertSame(expected: $apiKey, actual: $record->getApiKey());
        $this->assertSame(expected: 1, actual: $record->getTargetId());
        $this->assertSame(expected: Actions::UPDATE, actual: $record->getAction());
        $this->assertSame(expected: $timestamp2, actual: $record->getActionTimestamp());
        $this->assertTrue(condition: $record->getIsSuccess());
        $this->assertSame(expected: 'Should be returned', actual: $record->getMessage());
    }
}
