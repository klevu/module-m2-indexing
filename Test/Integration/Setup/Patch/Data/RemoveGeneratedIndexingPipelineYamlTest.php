<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Setup\Patch\Data;

use Klevu\Indexing\Setup\Patch\Data\RemoveGeneratedIndexingPipelineYaml;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\Filesystem\DirectoryList as AppDirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers RemoveGeneratedIndexingPipelineYaml::class
 * @method DataPatchInterface instantiateTestObject(?array $arguments = null)
 * @method DataPatchInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class RemoveGeneratedIndexingPipelineYamlTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var DirectoryList|null
     */
    private ?DirectoryList $directoryList = null; // @phpstan-ignore-line
    /**
     * @var FileIo|null
     */
    private ?FileIo $fileIo = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = RemoveGeneratedIndexingPipelineYaml::class;
        $this->interfaceFqcn = DataPatchInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->directoryList = $this->objectManager->get(DirectoryList::class);
        $this->fileIo = $this->objectManager->get(FileIo::class);
    }

    public function testGetAliases_ReturnsEmptyArray(): void
    {
        $patch = $this->instantiateTestObject();
        $aliases = $patch->getAliases();

        $this->assertCount(expectedCount: 0, haystack: $aliases);
    }

    public function testGetDependencies_ReturnsEmptyArray(): void
    {
        $patch = $this->instantiateTestObject();
        $dependencies = $patch->getDependencies();

        $this->assertCount(expectedCount: 0, haystack: $dependencies);
    }

    /**
     * @return void
     * @throws FileSystemException
     */
    public function testApply_WhenExist(): void
    {
        $indexingFilepaths = [
            $this->getIndexingFilepath('category', 'add_update.overrides.yml'),
            $this->getIndexingFilepath('category', 'delete.overrides.yml'),
            $this->getIndexingFilepath('cms', 'add_update.overrides.yml'),
            $this->getIndexingFilepath('cms', 'delete.overrides.yml'),
            $this->getIndexingFilepath('product', 'add_update.overrides.yml'),
            $this->getIndexingFilepath('product', 'delete.overrides.yml'),
        ];
        $otherFilepaths = [
            $this->getAnalyticsFilepath('order', 'overrides.yml'),
        ];

        array_walk(
            array: $indexingFilepaths,
            callback: [$this, 'createFile'],
        );
        array_walk(
            array: $otherFilepaths,
            callback: [$this, 'createFile'],
        );

        $setup = $this->instantiateTestObject();
        $setup->apply();

        foreach ($indexingFilepaths as $indexingFilepath) {
            $this->assertFalse(
                condition: $this->fileIo->fileExists($indexingFilepath),
                message: 'Not exists after patch applied: ' . $indexingFilepath,
            );
        }
        foreach ($otherFilepaths as $otherFilepath) {
            $this->assertTrue(
                condition: $this->fileIo->fileExists($otherFilepath),
                message: 'Exists after patch applied: ' . $otherFilepath,
            );
        }
    }

    /**
     * @return void
     * @throws FileSystemException
     */
    public function testApply_WhenNotExist(): void
    {
        $indexingFilepaths = [
            $this->getIndexingFilepath('category', 'add_update.overrides.yml'),
            $this->getIndexingFilepath('category', 'delete.overrides.yml'),
            $this->getIndexingFilepath('cms', 'add_update.overrides.yml'),
            $this->getIndexingFilepath('cms', 'delete.overrides.yml'),
            $this->getIndexingFilepath('product', 'add_update.overrides.yml'),
            $this->getIndexingFilepath('product', 'delete.overrides.yml'),
        ];
        $otherFilepaths = [
            $this->getAnalyticsFilepath('order', 'overrides.yml'),
        ];

        array_walk(
            array: $otherFilepaths,
            callback: [$this, 'createFile'],
        );

        $setup = $this->instantiateTestObject();
        $setup->apply();

        foreach ($indexingFilepaths as $indexingFilepath) {
            $this->assertFalse(
                condition: $this->fileIo->fileExists($indexingFilepath),
                message: 'Not exists after patch applied: ' . $indexingFilepath,
            );
        }
        foreach ($otherFilepaths as $otherFilepath) {
            $this->assertTrue(
                condition: $this->fileIo->fileExists($otherFilepath),
                message: 'Exists after patch applied: ' . $otherFilepath,
            );
        }
    }

    /**
     * @param string $submodule
     * @param string $filename
     *
     * @return string
     * @throws FileSystemException
     */
    private function getIndexingFilepath(
        string $submodule,
        string $filename,
    ): string {
        return $this->getFilepath(
            module: 'indexing',
            submodule: $submodule,
            filename: $filename,
        );
    }

    /**
     * @param string $submodule
     * @param string $filename
     *
     * @return string
     * @throws FileSystemException
     */
    private function getAnalyticsFilepath(
        string $submodule,
        string $filename,
    ): string {
        return $this->getFilepath(
            module: 'analytics',
            submodule: $submodule,
            filename: $filename,
        );
    }

    /**
     * @param string $module
     * @param string $submodule
     * @param string $filename
     *
     * @return string
     * @throws FileSystemException
     */
    private function getFilepath(
        string $module,
        string $submodule,
        string $filename,
    ): string {
        return implode(
            separator: DIRECTORY_SEPARATOR,
            array: [
                $this->directoryList->getPath(AppDirectoryList::VAR_DIR),
                'klevu',
                $module,
                'pipeline',
                $submodule,
                $filename,
            ],
        );
    }

    /**
     * @param string $filepath
     *
     * @return void
     * @throws LocalizedException
     */
    private function createFile(string $filepath): void
    {
        $this->fileIo->checkAndCreateFolder(
            folder: dirname($filepath),
            mode: 0755,
        );
        $this->fileIo->write(
            filename: $filepath,
            src: '# PHPUnit Test',
            mode: 0644,
        );

        $this->assertTrue(
            condition: $this->fileIo->fileExists($filepath),
            message: 'Exists before patch applied: ' . $filepath,
        );
    }
}
