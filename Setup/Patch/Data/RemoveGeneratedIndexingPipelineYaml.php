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
     * @var GeneratedConfigurationOverridesFilepathProviderInterface[]
     */
    private array $generatedConfigurationOverridesFilepathProviders = []; // phpcs:ignore Generic.Files.LineLength.TooLong

    // phpcs:disable Generic.Files.LineLength.TooLong
    /**
     * @param Filesystem $filesystem
     * @param GeneratedConfigurationOverridesFilepathProviderInterface[] $generatedConfigurationOverridesFilepathProviders
     */
    public function __construct(
        Filesystem $filesystem,
        array $generatedConfigurationOverridesFilepathProviders = [],
    ) {
        try {
            $this->directory = $filesystem->getDirectoryWrite(directoryCode: DirectoryList::VAR_DIR);
        } catch (FileSystemException) {
            // invalid directoryCode
        }
        array_walk(
            array: $generatedConfigurationOverridesFilepathProviders,
            callback: [$this, 'addGeneratedConfigurationOverridesFilepathProvider'],
        );
    }
    // phpcs:enable Generic.Files.LineLength.TooLong

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
        if (!$this->directory) {
            return $this;
        }

        foreach ($this->generatedConfigurationOverridesFilepathProviders as $generatedConfigurationOverridesFilepathProvider) { // phpcs:ignore Generic.Files.LineLength.TooLong
            $directoryPath = $generatedConfigurationOverridesFilepathProvider->get();
            if (!$directoryPath || !$this->directory->isExist(path: $directoryPath)) {
                continue;
            }

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

    /**
     * @param GeneratedConfigurationOverridesFilepathProviderInterface $generatedConfigurationOverridesFilepathProvider
     * @param string $identifier
     *
     * @return void
     */
    private function addGeneratedConfigurationOverridesFilepathProvider(
        GeneratedConfigurationOverridesFilepathProviderInterface $generatedConfigurationOverridesFilepathProvider,
        string $identifier,
    ): void {
        $this->generatedConfigurationOverridesFilepathProviders[$identifier]
            = $generatedConfigurationOverridesFilepathProvider;
    }
}
