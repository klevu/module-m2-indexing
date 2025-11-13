<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Cron;

use Klevu\Indexing\Cron\DiscoverAttributes;
use Klevu\IndexingApi\Api\Data\DiscoveryResultInterface;
use Klevu\IndexingApi\Service\AttributeDiscoveryOrchestratorServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Cron\Model\Config as CronConfig;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Cron\DiscoverEntities::class
 * @method DiscoverAttributes instantiateTestObject(?array $arguments = null)
 */
class DiscoverAttributesTest extends TestCase
{
    use ObjectInstantiationTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = DiscoverAttributes::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testCrontabIsConfigured(): void
    {
        $cronConfig = $this->objectManager->get(CronConfig::class);
        $cronJobs = $cronConfig->getJobs();

        $this->assertArrayHasKey(key: 'klevu', array: $cronJobs);
        $klevuCronJobs = $cronJobs['klevu'];

        $this->assertArrayHasKey(key: 'klevu_indexing_discover_attributes', array: $klevuCronJobs);
        $syncEntityCron = $klevuCronJobs['klevu_indexing_discover_attributes'];

        $this->assertSame(expected: DiscoverAttributes::class, actual: $syncEntityCron['instance']);
        $this->assertSame(expected: 'execute', actual: $syncEntityCron['method']);
        $this->assertSame(expected: 'klevu_indexing_discover_attributes', actual: $syncEntityCron['name']);
        $this->assertSame(expected: '52 1 * * *', actual: $syncEntityCron['schedule']);
    }

    public function testExecute_PrintsSuccessMessage_onSuccess(): void
    {
        $mockSyncResult = $this->getMockBuilder(DiscoveryResultInterface::class)
            ->getMock();
        $mockSyncResult->expects($this->once())
            ->method('isSuccess')
            ->willReturn(true);

        $mockDiscoveryOrchestrator = $this->getMockBuilder(AttributeDiscoveryOrchestratorServiceInterface::class)
            ->getMock();
        $mockDiscoveryOrchestrator->expects($this->once())
            ->method('execute')
            ->with()
            ->willReturn($mockSyncResult);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $expectation = $this->exactly(2);
        $mockLogger->expects($expectation)
            ->method('info')
            ->willReturnCallback(
                callback: function (string $message) use ($expectation): void {
                    $invocationCount = match (true) {
                        method_exists($expectation, 'getInvocationCount') => $expectation->getInvocationCount(),
                        method_exists($expectation, 'numberOfInvocations') => $expectation->numberOfInvocations(),
                        default => throw new \RuntimeException('Cannot determine invocation count from matcher'),
                    };

                    switch ($invocationCount) {
                        case 1:
                            $this->assertSame(
                                expected: 'Starting discovery of attributes.',
                                actual: $message,
                            );
                            break;

                        case 2:
                            $this->assertSame(
                                expected: 'Discovery of attributes completed successfully.',
                                actual: $message,
                            );
                            break;

                        default:
                            $this->fail(message: 'Logger::info called more than twice');
                            break;
                    }
                },
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

        $mockDiscoveryOrchestrator = $this->getMockBuilder(AttributeDiscoveryOrchestratorServiceInterface::class)
            ->getMock();
        $mockDiscoveryOrchestrator->expects($this->once())
            ->method('execute')
            ->with()
            ->willReturn($mockSyncResult);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $expectation = $this->exactly(2);
        $mockLogger->expects($expectation)
            ->method('info')
            ->willReturnCallback(
                callback: function (string $message) use ($expectation): void {
                    $invocationCount = match (true) {
                        method_exists($expectation, 'getInvocationCount') => $expectation->getInvocationCount(),
                        method_exists($expectation, 'numberOfInvocations') => $expectation->numberOfInvocations(),
                        default => throw new \RuntimeException('Cannot determine invocation count from matcher'),
                    };

                    switch ($invocationCount) {
                        case 1:
                            $this->assertSame(
                                expected: 'Starting discovery of attributes.',
                                actual: $message,
                            );
                            break;

                        case 2:
                            $this->assertSame(
                                expected: 'Discovery of attributes completed with failures. See logs for more details.',
                                actual: $message,
                            );
                            break;

                        default:
                            $this->fail(message: 'Logger::info called more than twice');
                            break;
                    }
                },
            );

        $cron = $this->instantiateTestObject([
            'discoveryOrchestratorService' => $mockDiscoveryOrchestrator,
            'logger' => $mockLogger,
        ]);

        $cron->execute();
    }
}
