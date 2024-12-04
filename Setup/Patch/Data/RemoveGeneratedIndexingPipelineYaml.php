<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Setup\Patch\Data;

use Klevu\PlatformPipelines\Service\Provider\GeneratedConfigurationOverridesFilepathProviderInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class RemoveGeneratedIndexingPipelineYaml implements DataPatchInterface
{
    /**
     * @var WriteInterface|null
     */
    private ?WriteInterface $directory = null;
    /**
     * @var GeneratedConfigurationOverridesFilepathProviderInterface
     */
    private readonly GeneratedConfigurationOverridesFilepathProviderInterface $generatedConfigurationOverridesFilepathProvider; // phpcs:ignore Generic.Files.LineLength.TooLong

    /**
     * @param Filesystem $filesystem
     * @param GeneratedConfigurationOverridesFilepathProviderInterface $generatedConfigurationOverridesFilepathProvider
     */
    public function __construct(
        Filesystem $filesystem,
        GeneratedConfigurationOverridesFilepathProviderInterface $generatedConfigurationOverridesFilepathProvider,
    ) {
        try {
            $this->directory = $filesystem->getDirectoryWrite(directoryCode: DirectoryList::VAR_DIR);
        } catch (FileSystemException) {
            // invalid directoryCode
        }
        $this->generatedConfigurationOverridesFilepathProvider = $generatedConfigurationOverridesFilepathProvider;
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * @return DataPatchInterface
     */
    public function apply(): DataPatchInterface
    {
        $directoryPath = $this->generatedConfigurationOverridesFilepathProvider->get();
        if ($this->directory && $this->directory->isExist(path: $directoryPath)) {
            try {
                $this->directory->delete(path: $directoryPath);
            } catch (FileSystemException) {
                // $directoryPath is not writable
            }
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }
}
