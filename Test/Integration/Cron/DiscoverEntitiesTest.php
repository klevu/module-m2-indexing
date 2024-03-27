<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Cron;

use Klevu\Indexing\Cron\DiscoverEntities;
use Klevu\IndexingApi\Api\Data\DiscoveryResultInterface;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
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
        $mockSyncResult = $this->getMockBuilder(DiscoveryResultInterface::class)
            ->getMock();
        $mockSyncResult->expects($this->once())
            ->method('isSuccess')
            ->willReturn(true);

        $mockDiscoveryOrchestrator = $this->getMockBuilder(EntityDiscoveryOrchestratorServiceInterface::class)
            ->getMock();
        $mockDiscoveryOrchestrator->expects($this->once())
            ->method('execute')
            ->with()
            ->willReturn($mockSyncResult);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Starting discovery of entities.'],
                ['Discovery of entities completed successfully.'],
            );

        $cron = $this->instantiateTestObject([
            'discoveryOrchestratorService' => $mockDiscoveryOrchestrator,
            'logger' => $mockLogger,
        ]);

        $cron->execute();
    }

    public function testExecute_PrintsFailureMessage_onFailure(): void
    {
        $mockSyncResult = $this->getMockBuilder(DiscoveryResultInterface::class)
            ->getMock();
        $mockSyncResult->expects($this->once())
            ->method('isSuccess')
            ->willReturn(false);

        $mockDiscoveryOrchestrator = $this->getMockBuilder(EntityDiscoveryOrchestratorServiceInterface::class)
            ->getMock();
        $mockDiscoveryOrchestrator->expects($this->once())
            ->method('execute')
            ->with()
            ->willReturn($mockSyncResult);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                ['Starting discovery of entities.'],
                ['Discovery of entities completed with failures. See logs for more details.'],
            );

        $cron = $this->instantiateTestObject([
            'discoveryOrchestratorService' => $mockDiscoveryOrchestrator,
            'logger' => $mockLogger,
        ]);

        $cron->execute();
    }
}
