<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Cron;

use Klevu\Indexing\Cron\DiscoverEntities;
use Klevu\Indexing\Model\DiscoveryResult;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\TestFixtures\Traits\GeneratorTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Cron\Model\Config as CronConfig;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Cron\DiscoverEntities::class
 * @method DiscoverEntities instantiateTestObject(?array $arguments = null)
 */
class DiscoverEntitiesTest extends TestCase
{
    use GeneratorTrait;
    use ObjectInstantiationTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = DiscoverEntities::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testCrontabIsConfigured(): void
    {
        $cronConfig = $this->objectManager->get(CronConfig::class);
        $cronJobs = $cronConfig->getJobs();

        $this->assertArrayHasKey(key: 'klevu', array: $cronJobs);
        $klevuCronJobs = $cronJobs['klevu'];

        $this->assertArrayHasKey(key: 'klevu_indexing_discover_entities', array: $klevuCronJobs);
        $syncEntityCron = $klevuCronJobs['klevu_indexing_discover_entities'];

        $this->assertSame(expected: DiscoverEntities::class, actual: $syncEntityCron['instance']);
        $this->assertSame(expected: 'execute', actual: $syncEntityCron['method']);
        $this->assertSame(expected: 'klevu_indexing_discover_entities', actual: $syncEntityCron['name']);
        $this->assertSame(expected: '0 2 * * *', actual: $syncEntityCron['schedule']);
    }

    public function testExecute_PrintsSuccessMessage_onSuccess(): void
    {
        $mockUpdateSyncResult = $this->objectManager->create(DiscoveryResult::class, [
            'isSuccess' => true,
            'action' => Actions::UPDATE->value,
            'entityType' => 'KLEVU_PRODUCT',
        ]);
        $mockDeleteSyncResult = $this->objectManager->create(DiscoveryResult::class, [
            'isSuccess' => true,
            'action' => Actions::DELETE->value,
            'entityType' => 'KLEVU_PRODUCT',
        ]);
        $mockAddSyncResult = $this->objectManager->create(DiscoveryResult::class, [
            'isSuccess' => true,
            'action' => Actions::ADD->value,
            'entityType' => 'KLEVU_PRODUCT',
        ]);

        $mockDiscoveryOrchestrator = $this->getMockBuilder(EntityDiscoveryOrchestratorServiceInterface::class)
            ->getMock();
        $mockDiscoveryOrchestrator->expects($this->once())
            ->method('execute')
            ->willReturn(
                $this->generate([
                    $this->generate([$mockUpdateSyncResult]),
                    $this->generate([$mockDeleteSyncResult]),
                    $this->generate([$mockAddSyncResult]),
                ]),
            );

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Starting discovery of entities.'],
                ['Discovery of entities completed successfully.'],
            );
        $mockLogger->expects($this->exactly(3))
            ->method('debug')
            ->withConsecutive(
                ['Discover KLEVU_PRODUCT to Update Batch 1 Completed Successfully.'],
                ['Discover KLEVU_PRODUCT to Delete Batch 1 Completed Successfully.'],
                ['Discover KLEVU_PRODUCT to Add Batch 1 Completed Successfully.'],
            );

        $cron = $this->instantiateTestObject([
            'discoveryOrchestratorService' => $mockDiscoveryOrchestrator,
            'logger' => $mockLogger,
        ]);

        $cron->execute();
    }

    public function testExecute_PrintsFailureMessage_onFailure(): void
    {
        $mockUpdateSyncResult1 = $this->objectManager->create(DiscoveryResult::class, [
            'isSuccess' => false,
            'action' => Actions::UPDATE->value,
            'entityType' => 'KLEVU_PRODUCT',
        ]);
        $mockUpdateSyncResult2 = $this->objectManager->create(DiscoveryResult::class, [
            'isSuccess' => true,
            'action' => Actions::UPDATE->value,
            'entityType' => 'KLEVU_PRODUCT',
        ]);
        $mockDeleteSyncResult = $this->objectManager->create(DiscoveryResult::class, [
            'isSuccess' => true,
            'action' => Actions::DELETE->value,
            'entityType' => 'KLEVU_PRODUCT',
        ]);
        $mockAddSyncResult = $this->objectManager->create(DiscoveryResult::class, [
            'isSuccess' => false,
            'action' => Actions::ADD->value,
            'entityType' => 'KLEVU_PRODUCT',
        ]);

        $mockDiscoveryOrchestrator = $this->getMockBuilder(EntityDiscoveryOrchestratorServiceInterface::class)
            ->getMock();
        $mockDiscoveryOrchestrator->expects($this->once())
            ->method('execute')
            ->willReturn(
                $this->generate([
                    $this->generate([$mockUpdateSyncResult1, $mockUpdateSyncResult2]),
                    $this->generate([$mockDeleteSyncResult]),
                    $this->generate([$mockAddSyncResult]),
                ]),
            );

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Starting discovery of entities.'],
                ['Discovery of entities completed with failures.'],
            );
        $mockLogger->expects($this->exactly(4))
            ->method('debug')
            ->withConsecutive(
                ['Discover KLEVU_PRODUCT to Update Batch 1 Failed.'],
                ['Discover KLEVU_PRODUCT to Update Batch 2 Completed Successfully.'],
                ['Discover KLEVU_PRODUCT to Delete Batch 1 Completed Successfully.'],
                ['Discover KLEVU_PRODUCT to Add Batch 1 Failed.'],
            );

        $cron = $this->instantiateTestObject([
            'discoveryOrchestratorService' => $mockDiscoveryOrchestrator,
            'logger' => $mockLogger,
        ]);

        $cron->execute();
    }
}
