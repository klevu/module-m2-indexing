<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Logger;

use Klevu\Indexing\Logger\Logger as IndexingLoggerVirtualType;
use Klevu\Logger\Test\Integration\Traits\FileSystemTrait;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\Filesystem\DirectoryList as AppDirectoryList;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Monolog as MagentoLogger;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers IndexingLoggerVirtualType::class
 * @method LoggerInterface instantiateTestObject(?array $arguments = null)
 */
class LoggerTest extends TestCase
{
    use FileSystemTrait;
    use ObjectInstantiationTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const XML_PATH_LOG_LEVEL = 'klevu_indexing/developer/log_level_indexing';
    private const LOG_FILENAME_PATTERN = 'klevu-%s-indexing.log';

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;
    /**
     * @var DirectoryList|null
     */
    private ?DirectoryList $directoryList = null;
    /**
     * @var DriverInterface|null
     */
    private ?DriverInterface $fileDriver = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setup();

        $this->implementationFqcn = IndexingLoggerVirtualType::class; // @phpstan-ignore-line virtualType
        $this->interfaceFqcn = LoggerInterface::class;
        $this->implementationForVirtualType = MagentoLogger::class;

        $this->objectManager = ObjectManager::getInstance();
        $this->directoryList = $this->objectManager->get(DirectoryList::class);
        $this->fileDriver = $this->objectManager->get(FileDriver::class);
        $this->storeFixturesPool = $this->objectManager->create(StoreFixturesPool::class);
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testErrorsOnly(): void
    {
        $logger = $this->instantiateTestObject();

        $storeCode = 'default';
        ConfigFixture::setGlobal(
            path: self::XML_PATH_LOG_LEVEL,
            value: MagentoLogger::ERROR,
        );

        $this->deleteAllLogs(includeSystem: true);
        $this->writeTestLogs($logger);

        $logDirectory = $this->directoryList->getPath(AppDirectoryList::LOG)
            . DIRECTORY_SEPARATOR;
        $klevuLogFilepath = $this->getStoreLogsDirectoryPath(null, $storeCode)
            . DIRECTORY_SEPARATOR
            . sprintf(self::LOG_FILENAME_PATTERN, $storeCode);

        $this->assertFileDoesNotExist($logDirectory . 'system.log');
        $this->assertFileDoesNotExist($logDirectory . 'debug.log');
        $this->assertFileDoesNotExist($logDirectory . 'exception.log');
        $this->assertFileExists($klevuLogFilepath);

        $klevuLogFileContents = $this->fileDriver->fileGetContents($klevuLogFilepath);
        $this->assertStringContainsString(
            needle: '.EMERGENCY: Test Emergency {"level":"emergency"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.ALERT: Test Alert {"level":"alert"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.CRITICAL: Test Critical {"level":"critical"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.ERROR: Test Error {"level":"error"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringNotContainsString(
            needle: '.WARNING: Test Warning {"level":"warning"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringNotContainsString(
            needle: '.NOTICE: Test Notice {"level":"notice"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringNotContainsString(
            needle: '.INFO: Test Info {"level":"info"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringNotContainsString(
            needle: '.DEBUG: Test Debug {"level":"debug"} []',
            haystack: $klevuLogFileContents,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testStandard(): void
    {
        $logger = $this->instantiateTestObject();

        $storeCode = 'default';
        ConfigFixture::setGlobal(
            path: self::XML_PATH_LOG_LEVEL,
            value: MagentoLogger::INFO,
        );

        $this->deleteAllLogs(includeSystem: true);
        $this->writeTestLogs($logger);

        $logDirectory = $this->directoryList->getPath(AppDirectoryList::LOG)
            . DIRECTORY_SEPARATOR;
        $klevuLogFilepath = $this->getStoreLogsDirectoryPath(null, $storeCode)
            . DIRECTORY_SEPARATOR
            . sprintf(self::LOG_FILENAME_PATTERN, $storeCode);

        $this->assertFileDoesNotExist($logDirectory . 'system.log');
        $this->assertFileDoesNotExist($logDirectory . 'debug.log');
        $this->assertFileDoesNotExist($logDirectory . 'exception.log');
        $this->assertFileExists($klevuLogFilepath);

        $klevuLogFileContents = $this->fileDriver->fileGetContents($klevuLogFilepath);
        $this->assertStringContainsString(
            needle: '.EMERGENCY: Test Emergency {"level":"emergency"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.ALERT: Test Alert {"level":"alert"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.CRITICAL: Test Critical {"level":"critical"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.ERROR: Test Error {"level":"error"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.WARNING: Test Warning {"level":"warning"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.NOTICE: Test Notice {"level":"notice"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.INFO: Test Info {"level":"info"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringNotContainsString(
            needle: '.DEBUG: Test Debug {"level":"debug"} []',
            haystack: $klevuLogFileContents,
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testVerbose(): void
    {
        $logger = $this->instantiateTestObject();

        $storeCode = 'default';
        ConfigFixture::setGlobal(
            path: self::XML_PATH_LOG_LEVEL,
            value: MagentoLogger::DEBUG,
        );

        $this->deleteAllLogs(includeSystem: true);
        $this->writeTestLogs($logger);

        $logDirectory = $this->directoryList->getPath(AppDirectoryList::LOG)
            . DIRECTORY_SEPARATOR;
        $klevuLogFilepath = $this->getStoreLogsDirectoryPath(null, $storeCode)
            . DIRECTORY_SEPARATOR
            . sprintf(self::LOG_FILENAME_PATTERN, $storeCode);

        $this->assertFileDoesNotExist($logDirectory . 'system.log');
        $this->assertFileDoesNotExist($logDirectory . 'debug.log');
        $this->assertFileDoesNotExist($logDirectory . 'exception.log');
        $this->assertFileExists($klevuLogFilepath);

        $klevuLogFileContents = $this->fileDriver->fileGetContents($klevuLogFilepath);
        $this->assertStringContainsString(
            needle: '.EMERGENCY: Test Emergency {"level":"emergency"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.ALERT: Test Alert {"level":"alert"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.CRITICAL: Test Critical {"level":"critical"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.ERROR: Test Error {"level":"error"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.WARNING: Test Warning {"level":"warning"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.NOTICE: Test Notice {"level":"notice"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.INFO: Test Info {"level":"info"} []',
            haystack: $klevuLogFileContents,
        );
        $this->assertStringContainsString(
            needle: '.DEBUG: Test Debug {"level":"debug"} []',
            haystack: $klevuLogFileContents,
        );
    }

    /**
     * @param LoggerInterface $logger
     *
     * @return void
     */
    private function writeTestLogs(LoggerInterface $logger): void
    {
        $logger->emergency('Test Emergency', ['level' => 'emergency']);
        $logger->alert('Test Alert', ['level' => 'alert']);
        $logger->critical('Test Critical', ['level' => 'critical']);
        $logger->error('Test Error', ['level' => 'error']);
        $logger->warning('Test Warning', ['level' => 'warning']);
        $logger->notice('Test Notice', ['level' => 'notice']);
        $logger->info('Test Info', ['level' => 'info']);
        $logger->debug('Test Debug', ['level' => 'debug']);
    }
}
