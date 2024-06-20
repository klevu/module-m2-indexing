<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action\Cron;

use Klevu\Indexing\Constants;
use Klevu\Indexing\Service\Action\Cron\CreateCronScheduleAction;
use Klevu\Indexing\Service\Action\Cron\CreateCronScheduleForEntityDiscoveryAction;
use Klevu\IndexingApi\Service\Action\CreateCronScheduleActionInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Cron\Model\ResourceModel\Schedule as ScheduleResourceModel;
use Magento\Cron\Model\ResourceModel\Schedule\Collection as CronScheduleCollection;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CreateCronScheduleForEntityDiscoveryActionTest extends TestCase
{
    use ObjectInstantiationTrait;
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

        $this->implementationFqcn = CreateCronScheduleForEntityDiscoveryAction::class; // @phpstan-ignore-line
        $this->implementationForVirtualType = CreateCronScheduleAction::class;
        $this->interfaceFqcn = CreateCronScheduleActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_CreateCronSchedule(): void
    {
        $jobs = $this->getCronSchedule(jobCode: Constants::CRON_JOB_CODE_INDEXING_ENTITY_DISCOVERY);
        $this->assertCount(expectedCount: 0, haystack: $jobs);

        $action = $this->instantiateTestObject();
        $action->execute();

        $jobs = $this->getCronSchedule(jobCode: Constants::CRON_JOB_CODE_INDEXING_ENTITY_DISCOVERY);
        $this->assertCount(expectedCount: 1, haystack: $jobs);
        $job = array_shift($jobs);

        $this->assertSame(expected: Constants::CRON_JOB_CODE_INDEXING_ENTITY_DISCOVERY, actual: $job->getJobCode());
        $this->assertSame(expected: Schedule::STATUS_PENDING, actual: $job->getStatus());
        $this->assertNull(actual: $job->getMessages());

        $createdAt = strtotime($job->getCreatedAt());
        $scheduledAt = strtotime($job->getScheduledAt());
        $diff = $scheduledAt - $createdAt;
        $this->assertSame(
            expected: 5 * 60,
            actual: $diff,
        );
        $this->assertNull(actual: $job->getExecutedAt());
        $this->assertNull(actual: $job->getFinishedAt());
    }

    public function testExecute_DoesNothing_WhenJobAlreadyExists(): void
    {
        $exception = new AlreadyExistsException(
            phrase: __('That already exists'),
        );

        $mockScheduleResourceModel = $this->getMockBuilder(ScheduleResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockScheduleResourceModel->expects($this->once())
            ->method('save')
            ->willThrowException($exception);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->never())
            ->method('error');

        $jobs = $this->getCronSchedule(jobCode: Constants::CRON_JOB_CODE_INDEXING_ENTITY_DISCOVERY);
        $this->assertCount(expectedCount: 0, haystack: $jobs);

        $action = $this->instantiateTestObject([
            'scheduleResourceModel' => $mockScheduleResourceModel,
            'logger' => $mockLogger,
        ]);
        $action->execute();

        $jobs = $this->getCronSchedule(jobCode: Constants::CRON_JOB_CODE_INDEXING_ENTITY_DISCOVERY);
        $this->assertCount(expectedCount: 0, haystack: $jobs);
    }

    public function testExecute_LogsError_WhenExceptinThrown(): void
    {
        $message = 'Save Failed';
        $exception = new \Exception(message: $message);

        $mockScheduleResourceModel = $this->getMockBuilder(ScheduleResourceModel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockScheduleResourceModel->expects($this->once())
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
                    'method' => 'Klevu\Indexing\Service\Action\Cron\CreateCronScheduleAction::execute',
                    'message' => $message,
                ],
            );

        $jobs = $this->getCronSchedule(jobCode: Constants::CRON_JOB_CODE_INDEXING_ENTITY_DISCOVERY);
        $this->assertCount(expectedCount: 0, haystack: $jobs);

        $action = $this->instantiateTestObject([
            'scheduleResourceModel' => $mockScheduleResourceModel,
            'logger' => $mockLogger,
        ]);
        $action->execute();

        $jobs = $this->getCronSchedule(jobCode: Constants::CRON_JOB_CODE_INDEXING_ENTITY_DISCOVERY);
        $this->assertCount(expectedCount: 0, haystack: $jobs);
    }

    /**
     * @return Schedule[]
     */
    private function getCronSchedule(?string $jobCode = null): array
    {
        $collection = $this->objectManager->create(CronScheduleCollection::class);
        if ($jobCode) {
            $collection->addFieldToFilter(
                field:'job_code',
                condition: ['eq' => $jobCode],
            );
        }

        return $collection->getItems();
    }
}
