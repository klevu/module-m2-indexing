<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Cron;

use Klevu\Indexing\Cron\SyncHistoryConsolidationClean;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Cron\Model\Config as CronConfig;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SyncHistoryConsolidationCleanTest extends TestCase
{
    use ObjectInstantiationTrait;

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

        $this->implementationFqcn = SyncHistoryConsolidationClean::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testCrontabIsConfigured(): void
    {
        $cronConfig = $this->objectManager->get(CronConfig::class);
        $cronJobs = $cronConfig->getJobs();

        $this->assertArrayHasKey(key: 'klevu', array: $cronJobs);
        $klevuCronJobs = $cronJobs['klevu'];

        $this->assertArrayHasKey(key: 'klevu_indexing_sync_history_consolidation_clean', array: $klevuCronJobs);
        $syncEntityCron = $klevuCronJobs['klevu_indexing_sync_history_consolidation_clean'];

        $this->assertSame(expected: SyncHistoryConsolidationClean::class, actual: $syncEntityCron['instance']);
        $this->assertSame(expected: 'execute', actual: $syncEntityCron['method']);
        $this->assertSame(expected: 'klevu_indexing_sync_history_consolidation_clean', actual: $syncEntityCron['name']);
        $this->assertSame(expected: '10 0 * * *', actual: $syncEntityCron['schedule']);
    }
}
