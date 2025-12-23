<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Cron;

use Klevu\Indexing\Cron\ProcessRequireUpdateEntities;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Determiner\RequiresUpdateDeterminer;
use Klevu\Indexing\Service\FilterEntitiesRequireUpdateService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Determiner\RequiresUpdateCriteriaInterface;
use Klevu\IndexingApi\Service\FilterEntitiesRequireUpdateServiceInterface;
use Klevu\IndexingApi\Service\Provider\IndexingEntityProviderInterface;
use Klevu\TestFixtures\Catalog\ProductTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Catalog\Model\Product\Attribute\Source\Status as SourceStatus;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Cron\Model\Config as CronConfig;
use Magento\Framework\App\Config\Storage\Writer as ConfigWriter;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Catalog\ProductFixturePool;
use TddWizard\Fixtures\Core\ConfigFixture;

class ProcessRequireUpdateEntitiesTest extends TestCase
{
    use IndexingEntitiesTrait;
    use ObjectInstantiationTrait;
    use ProductTrait;
    use StoreTrait;

    private const REQUIRES_UPDATE_CRITERIA_IDENTIFIER = 'phpunit_has_change';

    /**
     * @var ObjectManager|ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var CronConfig 
     */
    private CronConfig $cronConfig;
    /**
     * @var ConfigWriter|null
     */
    private ?ConfigWriter $configWriter = null;
    /**
     * @var IndexingEntityProviderInterface|null
     */
    private ?IndexingEntityProviderInterface $indexingEntityProvider;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = ProcessRequireUpdateEntities::class;

        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->productFixturePool = $this->objectManager->get(ProductFixturePool::class);

        $this->cronConfig = $this->objectManager->get(CronConfig::class);
        $this->configWriter = $this->objectManager->get(ConfigWriter::class);
        $this->indexingEntityProvider = $this->objectManager->get(IndexingEntityProviderInterface::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->productFixturePool->rollback();
        $this->storeFixturesPool->rollback();
        $this->cleanIndexingEntities('klevu-1234567890');
    }

    public function testCrontabIsConfigured(): void
    {
        $cronJobs = $this->cronConfig->getJobs();
        
        $this->assertArrayHasKey('klevu', $cronJobs);
        $klevuCronJobs = $cronJobs['klevu'];
        
        $this->assertArrayHasKey('klevu_indexing_process_require_update_entities', $klevuCronJobs);
        $processRequireUpdateEntitiesCron = $klevuCronJobs['klevu_indexing_process_require_update_entities'];

        $this->assertSame(
            expected: ProcessRequireUpdateEntities::class,
            actual: $processRequireUpdateEntitiesCron['instance'],
        );
        $this->assertSame(
            expected: 'execute',
            actual: $processRequireUpdateEntitiesCron['method'],
        );
        $this->assertSame(
            expected: 'klevu_indexing_process_require_update_entities',
            actual: $processRequireUpdateEntitiesCron['name'],
        );
        $this->assertArrayNotHasKey('schedule', $processRequireUpdateEntitiesCron);
        $this->assertArrayHasKey('config_path', $processRequireUpdateEntitiesCron);
        $this->assertSame(
            expected: 'klevu/indexing/process_require_update_entities_cron_expr',
            actual: $processRequireUpdateEntitiesCron['config_path'],
        );
    }

    /**
     * @note Given this test runs for all integrated API keys, dirty data may cause it to fail
     *          There is no way we can exclude stores given the current configuration
     * @return void
     */
    public function testExecute_NoStoresConfigured(): void
    {
        $mockLogger = $this->getMockLogger([
            'info',
        ]);
        $expectation = $this->exactly(2);
        $mockLogger->expects($expectation)
            ->method('info')
            ->willReturnCallback(
                // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
                callback: function (string $message, array $context) use ($expectation): void {
                    $invocationCount = match (true) {
                        method_exists($expectation, 'getInvocationCount') => $expectation->getInvocationCount(),
                        method_exists($expectation, 'numberOfInvocations') => $expectation->numberOfInvocations(),
                        default => throw new \RuntimeException('Cannot determine invocation count from matcher'),
                    };

                    switch ($invocationCount) {
                        case 1:
                            $this->assertSame(
                                expected: '[CRON] Starting processing of entities requiring update.',
                                actual: $message,
                            );
                            break;

                        case 2:
                            $this->assertSame(
                                expected: '[CRON] Processing of entities requiring update completed successfully.',
                                actual: $message,
                            );
                            break;
                    }
                },
            );

        /** @var ProcessRequireUpdateEntities $processRequireUpdateEntitiesCron */
        $processRequireUpdateEntitiesCron = $this->instantiateTestObject([
            'logger' => $mockLogger,
        ]);

        $processRequireUpdateEntitiesCron->execute();
    }

    public function testExecute(): void
    {
        $this->createStore(
            storeData: [
                'key' => 'klevu_test_process_req_upd',
                'code' => 'klevu_test_process_req_upd',
                'name' => 'Klevu Test: Process Require Update Entities Cron',
                'is_active' => true,
            ],
        );
        $storeFixture = $this->storeFixturesPool->get('klevu_test_process_req_upd');

        ConfigFixture::setForStore(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            storeCode: 'klevu_test_process_req_upd',
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/js_api_key',
            value: 'klevu-1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixture->getId(),
        );
        $this->configWriter->save(
            path: 'klevu_configuration/auth_keys/rest_auth_key',
            value: 'ABCDE1234567890',
            scope: ScopeInterface::SCOPE_STORES,
            scopeId: $storeFixture->getId(),
        );

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_process_require_update_1',
                'sku' => 'klevu_test_process_require_update_1',
                'name' => 'Klevu Test: Process Require Update Entities Cron (1)',
                'description' => 'Requires Update; No change',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture1 = $this->productFixturePool->get('klevu_test_process_require_update_1');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_process_require_update_2',
                'sku' => 'klevu_test_process_require_update_2',
                'name' => 'Klevu Test: Process Require Update Entities Cron (2)',
                'description' => 'Requires Update; With change',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture2 = $this->productFixturePool->get('klevu_test_process_require_update_2');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_process_require_update_3',
                'sku' => 'klevu_test_process_require_update_3',
                'name' => 'Klevu Test: Process Require Update Entities Cron (3)',
                'description' => 'Not Requires Update; With change',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture3 = $this->productFixturePool->get('klevu_test_process_require_update_3');

        $this->createProduct(
            productData: [
                'key' => 'klevu_test_process_require_update_4',
                'sku' => 'klevu_test_process_require_update_4',
                'name' => 'Klevu Test: Process Require Update Entities Cron (4)',
                'description' => 'Not Requires Update; No change',
                'status' => SourceStatus::STATUS_ENABLED,
                'visibility' => Visibility::VISIBILITY_BOTH,
                'in_stock' => true,
                'qty' => 100,
                'price' => 100.00,
                'website_ids' => array_unique([
                    $storeFixture->getWebsiteId(),
                ]),
                'type_id' => Type::TYPE_SIMPLE,
            ],
        );
        $productFixture4 = $this->productFixturePool->get('klevu_test_process_require_update_4');

        $this->cleanIndexingEntities('klevu-1234567890');

        $indexingEntity1 = $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => (int)$productFixture1->getId(),
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => true,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [
                    self::REQUIRES_UPDATE_CRITERIA_IDENTIFIER => false,
                ],
            ],
        );
        $indexingEntity2 = $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => (int)$productFixture2->getId(),
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => true,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [
                    self::REQUIRES_UPDATE_CRITERIA_IDENTIFIER => true,
                ],
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => (int)$productFixture3->getId(),
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [
                    self::REQUIRES_UPDATE_CRITERIA_IDENTIFIER => true,
                ],
            ],
        );
        $this->createIndexingEntity(
            data: [
                IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
                IndexingEntity::API_KEY => 'klevu-1234567890',
                IndexingEntity::TARGET_ID => (int)$productFixture4->getId(),
                IndexingEntity::IS_INDEXABLE => true,
                IndexingEntity::REQUIRES_UPDATE => false,
                IndexingEntity::REQUIRES_UPDATE_ORIG_VALUES => [
                    self::REQUIRES_UPDATE_CRITERIA_IDENTIFIER => false,
                ],
            ],
        );

        $mockLogger = $this->getMockLogger([
            'info',
            'debug',
        ]);
        $expectation = $this->exactly(2);
        $mockLogger->expects($expectation)
            ->method('info')
            ->willReturnCallback(
                // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
                callback: function (string $message, array $context) use ($expectation): void {
                    $invocationCount = match (true) {
                        method_exists($expectation, 'getInvocationCount') => $expectation->getInvocationCount(),
                        method_exists($expectation, 'numberOfInvocations') => $expectation->numberOfInvocations(),
                        default => throw new \RuntimeException('Cannot determine invocation count from matcher'),
                    };

                    switch ($invocationCount) {
                        case 1:
                            $this->assertSame(
                                expected: '[CRON] Starting processing of entities requiring update.',
                                actual: $message,
                            );
                            break;

                        case 2:
                            $this->assertSame(
                                expected: '[CRON] Processing of entities requiring update completed successfully.',
                                actual: $message,
                            );
                            break;
                    }
                },
            );
        $mockLogger->expects($this->exactly(3))
            ->method('debug')
            ->willReturnCallback(
                callback: function (string $message, array $context) use ($indexingEntity1, $indexingEntity2): void {
                    $this->assertSame(
                        expected: '[CRON] Successfully processed entities requiring update.',
                        actual: $message,
                    );

                    $this->assertArrayHasKey('action', $context);
                    $this->assertTrue(
                        condition: in_array(
                            needle: $context['action'],
                            haystack: [Actions::UPDATE->value, Actions::DELETE->value, Actions::ADD->value],
                            strict: true,
                        ),
                    );

                    $this->assertArrayHasKey('apiKey', $context);
                    $this->assertSame('klevu-1234567890', $context['apiKey']);

                    $this->assertArrayHasKey('entityTargetIds', $context);
                    $this->assertEquals(
                        expected: [
                            $indexingEntity1->getTargetId(),
                            $indexingEntity2->getTargetId(),
                        ],
                        actual: $context['entityTargetIds'],
                    );

                    $this->assertArrayHasKey('processedEntityIds', $context);
                    $this->assertEquals(
                        expected: match ($context['action']) {
                            Actions::UPDATE->value => [
                                $indexingEntity2->getId() => $indexingEntity2->getId(),
                            ],
                            default => [],
                        },
                        actual: $context['processedEntityIds'],
                    );
                },
            );

        /** @var ProcessRequireUpdateEntities $processRequireUpdateEntitiesCron */
        $processRequireUpdateEntitiesCron = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'filterEntitiesRequireUpdateService' => $this->getFilterEntitiesRequireUpdateService(),
        ]);

        $processRequireUpdateEntitiesCron->execute();

        $indexingEntities = $this->indexingEntityProvider->get(
            entityType: 'KLEVU_PRODUCT',
            apiKeys: ['klevu-1234567890'],
            entityIds: [
                $productFixture1->getId(),
                $productFixture2->getId(),
                $productFixture3->getId(),
                $productFixture4->getId(),
            ],
        );
        $this->assertCount(4, $indexingEntities);
        foreach ($indexingEntities as $indexingEntity) {
            $this->assertTrue($indexingEntity->getIsIndexable());
            $this->assertFalse($indexingEntity->getRequiresUpdate());

            switch ($indexingEntity->getTargetId()) {
                case $productFixture1->getId():
                    $this->assertSame(
                        expected: Actions::NO_ACTION,
                        actual: $indexingEntity->getNextAction(),
                    );
                    $this->assertEmpty($indexingEntity->getRequiresUpdateOrigValues());
                    break;

                case $productFixture2->getId():
                    $this->assertSame(
                        expected: Actions::UPDATE,
                        actual: $indexingEntity->getNextAction(),
                    );
                    $this->assertEmpty($indexingEntity->getRequiresUpdateOrigValues());
                    break;

                case $productFixture3->getId():
                case $productFixture4->getId():
                    $this->assertSame(
                        expected: Actions::NO_ACTION,
                        actual: $indexingEntity->getNextAction(),
                    );
                    $this->assertNotEmpty($indexingEntity->getRequiresUpdateOrigValues());
                    break;

                default:
                    $this->fail(sprintf(
                        'Unexpected indexing target id: %s',
                        $indexingEntity->getTargetId(),
                    ));
                    break;
            }
        }
    }

    /**
     * @param string[] $expectedLogLevels
     *
     * @return MockObject&LoggerInterface
     */
    private function getMockLogger(array $expectedLogLevels = []): MockObject
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $notExpectedLogLevels = array_diff(
            [
                'emergency',
                'alert',
                'critical',
                'error',
                'warning',
                'notice',
                'info',
                'debug',
            ],
            $expectedLogLevels,
        );
        foreach ($notExpectedLogLevels as $notExpectedLogLevel) {
            $mockLogger->expects($this->never())
                ->method($notExpectedLogLevel);
        }

        return $mockLogger;
    }

    /**
     * @return MockObject&RequiresUpdateCriteriaInterface
     */
    private function getMockRequiresUpdateCriteria(
        string $entityType,
        string $criteriaIdentifier,
    ): MockObject {
        $requiresUpdateCriteria = $this->getMockBuilder(RequiresUpdateCriteriaInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $requiresUpdateCriteria->method('getEntityType')
            ->willReturn($entityType);
        $requiresUpdateCriteria->method('getCriteriaIdentifier')
            ->willReturn($criteriaIdentifier);

        return $requiresUpdateCriteria;
    }

    /**
     * @return FilterEntitiesRequireUpdateServiceInterface
     */
    private function getFilterEntitiesRequireUpdateService(): FilterEntitiesRequireUpdateServiceInterface
    {
        $requiresUpdateCriteriaMockProduct = $this->getMockRequiresUpdateCriteria(
            entityType: 'KLEVU_PRODUCT',
            criteriaIdentifier: self::REQUIRES_UPDATE_CRITERIA_IDENTIFIER,
        );
        $requiresUpdateCriteriaMockProduct->method('execute')
            ->willReturnCallback(
                callback: static function (IndexingEntityInterface $indexingEntity): bool {
                    if ('KLEVU_PRODUCT' !== $indexingEntity->getTargetEntityType()) {
                        return false;
                    }

                    $origValues = $indexingEntity->getRequiresUpdateOrigValues();
                    if (!array_key_exists(self::REQUIRES_UPDATE_CRITERIA_IDENTIFIER, $origValues)) {
                        return false;
                    }

                    return (bool)$origValues[self::REQUIRES_UPDATE_CRITERIA_IDENTIFIER];
                },
            );

        $requiresUpdateCriteriaMockCms = $this->getMockRequiresUpdateCriteria(
            entityType: 'KLEVU_CMS',
            criteriaIdentifier: self::REQUIRES_UPDATE_CRITERIA_IDENTIFIER,
        );
        $requiresUpdateCriteriaMockCms->method('execute')
            ->willReturnCallback(
                callback: static function (IndexingEntityInterface $indexingEntity): bool {
                    if ('KLEVU_CMS' !== $indexingEntity->getTargetEntityType()) {
                        return false;
                    }

                    $origValues = $indexingEntity->getRequiresUpdateOrigValues();
                    if (!array_key_exists(self::REQUIRES_UPDATE_CRITERIA_IDENTIFIER, $origValues)) {
                        return false;
                    }

                    return !$origValues[self::REQUIRES_UPDATE_CRITERIA_IDENTIFIER];
                },
            );

        $requiresUpdateDeterminer = $this->objectManager->create(
            type: RequiresUpdateDeterminer::class,
            arguments: [
                'criteriaServices' => [
                    'phpunit_product' => $requiresUpdateCriteriaMockProduct,
                    'phpunit_cms' => $requiresUpdateCriteriaMockCms,
                ],
            ],
        );
        
        return $this->objectManager->create(
            type: FilterEntitiesRequireUpdateService::class,
            arguments: [
                'requiresUpdateDeterminer' => $requiresUpdateDeterminer,
            ],
        );
    }
}
