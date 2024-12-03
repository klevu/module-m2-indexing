<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Setup\Patch\Data;

use Klevu\Indexing\Setup\Patch\Data\RemoveGeneratedIndexingPipelineYaml;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
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
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = RemoveGeneratedIndexingPipelineYaml::class;
        $this->interfaceFqcn = DataPatchInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
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

    public function testApply_RemovesIndexingPipelineDirectory(): void
    {
        $filePath = DirectoryList::VAR_DIR . DIRECTORY_SEPARATOR . 'klevu' . DIRECTORY_SEPARATOR
            . 'indexing' . DIRECTORY_SEPARATOR . 'pipeline';

        $filesystem = $this->objectManager->get(Filesystem::class);
        $directory = $filesystem->getDirectoryWrite(directoryCode: DirectoryList::VAR_DIR);
        $directory->create($filePath);

        $this->assertTrue(condition: $directory->isExist($filePath));

        $setup = $this->instantiateTestObject();
        $setup->apply();

        $this->assertFalse(condition: $directory->isExist($filePath));
    }
}
