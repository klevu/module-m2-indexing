<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action\Cron;

use Klevu\IndexingApi\Service\Action\CreateCronScheduleActionInterface;
use Magento\Cron\Model\ResourceModel\Schedule as ScheduleResourceModel;
use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Psr\Log\LoggerInterface;

class CreateCronScheduleAction implements CreateCronScheduleActionInterface
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';
    private const SECONDS_IN_MINUTE = 60;
    private const DEFAULT_CRON_SCHEDULE_MINUTES = 5;

    /**
     * @var ScheduleFactory
     */
    private readonly ScheduleFactory $scheduleFactory;
    /**
     * @var ScheduleResourceModel
     */
    private readonly ScheduleResourceModel $scheduleResourceModel;
    /**
     * @var DateTime
     */
    private readonly DateTime $dateTime;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var string
     */
    private readonly string $jobCode;
    /**
     * @var int
     */
    private readonly int $scheduleInMinutes;
    /**
     * @var string
     */
    private readonly string $status;

    /**
     * @param ScheduleFactory $scheduleFactory
     * @param ScheduleResourceModel $scheduleResourceModel
     * @param DateTime $dateTime
     * @param LoggerInterface $logger
     * @param string $jobCode
     * @param int $scheduleInMinutes
     * @param string $status
     */
    public function __construct(
        ScheduleFactory $scheduleFactory,
        ScheduleResourceModel $scheduleResourceModel,
        DateTime $dateTime,
        LoggerInterface $logger,
        string $jobCode,
        int $scheduleInMinutes = self::DEFAULT_CRON_SCHEDULE_MINUTES,
        string $status = Schedule::STATUS_PENDING,
    ) {
        $this->scheduleFactory = $scheduleFactory;
        $this->scheduleResourceModel = $scheduleResourceModel;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->jobCode = $jobCode;
        $this->scheduleInMinutes = $scheduleInMinutes;
        $this->status = $status;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->scheduleResourceModel->save(
                object: $this->createScheduleEntry(),
            );
        } catch (AlreadyExistsException) {
            // is already scheduled, fine no need to create it again
        } catch (\Exception $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }

    /**
     * @return Schedule
     */
    private function createScheduleEntry(): Schedule
    {
        /** @var Schedule $schedule */
        $schedule = $this->scheduleFactory->create();
        $schedule->setJobCode($this->jobCode);
        $schedule->setStatus($this->status);
        $schedule->setCreatedAt(
           date(
               format: self::DATE_FORMAT,
               timestamp: $this->dateTime->gmtTimestamp(),
           ),
        );
        $schedule->setScheduledAt(
            date(
                format: self::DATE_FORMAT,
                timestamp: $this->getScheduledAtTime(),
            ),
        );

        return $schedule;
    }

    /**
     * @return int
     */
    private function getScheduledAtTime(): int
    {
        return $this->dateTime->gmtTimestamp() + ($this->scheduleInMinutes * self::SECONDS_IN_MINUTE);
    }
}
