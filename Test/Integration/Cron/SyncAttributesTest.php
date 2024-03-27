<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Cron;

use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Cron\SyncAttributes;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Test\Integration\Traits\IndexingAttributesTrait;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\TestFixtures\Catalog\Attribute\AttributeFixturePool;
use Klevu\TestFixtures\Catalog\AttributeTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\PipelineAttributeApiCallTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Magento\Cron\Model\Config as CronConfig;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers SyncAttributes
 * @method SyncAttributes instantiateTestObject(?array $arguments = null)
 */
class SyncAttributesTest extends TestCase
{
    use AttributeTrait;
    use IndexingAttributesTrait;
    use ObjectInstantiationTrait;
    use PipelineAttributeApiCallTrait;
    use SetAuthKeysTrait;
    use StoreTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = SyncAttributes::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
        $this->attributeFixturePool = $this->objectManager->get(AttributeFixturePool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->attributeFixturePool->rollback();
        $this->storeFixturesPool->rollback();
    }

    public function testCrontabIsConfigured(): void
    {
        $cronConfig = $this->objectManager->get(CronConfig::class);
        $cronJobs = $cronConfig->getJobs();

        $this->assertArrayHasKey(key: 'klevu', array: $cronJobs);
        $klevuCronJobs = $cronJobs['klevu'];

        $this->assertArrayHasKey(key: 'klevu_indexing_sync_attributes', array: $klevuCronJobs);
        $syncEntityCron = $klevuCronJobs['klevu_indexing_sync_attributes'];

        $this->assertSame(expected: SyncAttributes::class, actual: $syncEntityCron['instance']);
        $this->assertSame(expected: 'execute', actual: $syncEntityCron['method']);
        $this->assertSame(expected: 'klevu_indexing_sync_attributes', actual: $syncEntityCron['name']);
        $this->assertSame(expected: '12,42 * * * *', actual: $syncEntityCron['schedule']);
    }

    public function testExecute_PrintsSuccessMessage_onSuccess(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );
        $scopeProvider->unsetCurrentScope();

        $this->mockSdkAttributePutApiCall(isCalled: true, isSuccessful: true);

        $this->createAttribute();
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->cleanIndexingAttributes($apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture->getAttributeId(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::ADD,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Starting sync of attributes.'],
                [
                    sprintf(
                        'Sync of attributes for apiKey: %s, KLEVU_PRODUCT::add %s: completed successfully.',
                        $apiKey,
                        $attributeFixture->getAttributeCode(),
                    ),
                ],
            );

        $cron = $this->instantiateTestObject([
            'logger' => $mockLogger,
        ]);
        $cron->execute();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_PrintsFailureMessage_onFailure(): void
    {
        $apiKey = 'klevu-js-api-key';
        $authKey = 'klevu-rest-auth-key';

        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope(scope: $storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: $apiKey,
            restAuthKey: $authKey,
        );
        $scopeProvider->unsetCurrentScope();

        $this->mockSdkAttributePutApiCall(isCalled: true, isSuccessful: false);

        $this->createAttribute();
        $attributeFixture = $this->attributeFixturePool->get('test_attribute');

        $this->cleanIndexingAttributes($apiKey);
        $this->createIndexingAttribute([
            IndexingAttribute::TARGET_ID => $attributeFixture->getAttributeId(),
            IndexingAttribute::API_KEY => $apiKey,
            IndexingAttribute::NEXT_ACTION => Actions::UPDATE,
            IndexingAttribute::LAST_ACTION => Actions::NO_ACTION,
            IndexingAttribute::IS_INDEXABLE => true,
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Starting sync of attributes.'],
                [
                    sprintf(
                        'Sync of attributes for apiKey: %s, KLEVU_PRODUCT::update %s: completed with failures. See logs for more details.', // phpcs:ignore Generic.Files.LineLength.TooLong
                        $apiKey,
                        $attributeFixture->getAttributeCode(),
                    ),
                ],
            );

        $cron = $this->instantiateTestObject([
            'logger' => $mockLogger,
        ]);
        $cron->execute();
    }
}
