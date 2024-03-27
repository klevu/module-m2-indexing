<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\IndexingApi\Service\Action\ParseFilepathActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File as FileIo;
use Magento\Framework\Module\Dir as ModuleDir;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\FileSystem;

class ParseFilepath implements ParseFilepathActionInterface
{
    /**
     * @var ModuleDir
     */
    private readonly ModuleDir $moduleDir;
    /**
     * @var FileIo
     */
    private readonly FileIo $fileIo;
    /**
     * @var DirectoryList
     */
    private readonly DirectoryList $directoryList;

    /**
     * @param ModuleDir $moduleDir
     * @param FileIo $fileIo
     * @param DirectoryList $directoryList
     */
    public function __construct(
        ModuleDir $moduleDir,
        FileIo $fileIo,
        DirectoryList $directoryList,
    ) {
        $this->moduleDir = $moduleDir;
        $this->fileIo = $fileIo;
        $this->directoryList = $directoryList;
    }

    /**
     * @param string $filepath
     *
     * @return string
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function execute(string $filepath): string
    {
        $return = match (true) {
            $this->isModuleFilepath($filepath) => $this->getModuleFilepath($filepath),
            $this->isRelativeFilepath($filepath) => $this->getRelativeFilepath($filepath),
            default => $filepath,
        };

        if (!$this->fileIo->fileExists($return)) {
            throw new NotFoundException(
                phrase: __('File %1 does not exist', $filepath),
            );
        }

        return $return;
    }

    /**
     * @param string $filepath
     *
     * @return bool
     */
    private function isModuleFilepath(string $filepath): bool
    {
        return !!preg_match('/^[A-Za-z0-9]+_[A-Za-z0-9]+::.*$/', $filepath);
    }

    /**
     * @param string $filepath
     *
     * @return string
     * @throws LocalizedException
     */
    private function getModuleFilepath(string $filepath): string
    {
        [$module, $filepath] = AssetRepository::extractModule(
            FileSystem::normalizePath($filepath),
        );

        return $this->moduleDir->getDir($module, '')
            . DIRECTORY_SEPARATOR
            . $filepath;
    }

    /**
     * @param string $filepath
     *
     * @return bool
     */
    private function isRelativeFilepath(string $filepath): bool
    {
        return !str_starts_with(
            haystack: $filepath,
            needle: DIRECTORY_SEPARATOR,
        );
    }

    /**
     * @param string $filepath
     *
     * @return string
     */
    private function getRelativeFilepath(string $filepath): string
    {
        return $this->directoryList->getRoot()
            . DIRECTORY_SEPARATOR
            . $filepath;
    }
}
