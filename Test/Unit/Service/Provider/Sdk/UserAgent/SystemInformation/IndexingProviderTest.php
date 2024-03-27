<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Unit\Service\Provider\Sdk\UserAgent\SystemInformation;

// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Composer\InstalledVersions;
use Klevu\Indexing\Service\Provider\Sdk\UserAgent\SystemInformation\IndexingProvider;
use Klevu\PhpSDK\Provider\UserAgentProviderInterface;
use PHPUnit\Framework\TestCase;

class IndexingProviderTest extends TestCase
{
    public function testIsInstanceOfInterface(): void
    {
        $indexingProvider = new IndexingProvider();

        $this->assertInstanceOf(
            expected: UserAgentProviderInterface::class,
            actual: $indexingProvider,
        );
    }

    public function testExecute_ComposerInstall(): void
    {
        if (!InstalledVersions::isInstalled('klevu/module-m2-indexing')) {
            $this->markTestSkipped('Module not installed by composer');
        }

        $indexingProvider = new IndexingProvider();

        $result = $indexingProvider->execute();

        $this->assertStringContainsString(
            needle: 'klevu-m2-indexing/' . $this->getLibraryVersion(),
            haystack: $result,
        );
    }

    public function testExecute_AppInstall(): void
    {
        if (InstalledVersions::isInstalled('klevu/module-m2-indexing')) {
            $this->markTestSkipped('Module installed by composer');
        }

        $indexingProvider = new IndexingProvider();

        $result = $indexingProvider->execute();

        $this->assertSame(
            expected: 'klevu-m2-indexing',
            actual: $result,
        );
    }

    /**
     * @return string
     */
    private function getLibraryVersion(): string
    {
        $composerFilename = __DIR__ . '/../../../../../../../composer.json';
        $composerContent = json_decode(
            json: file_get_contents($composerFilename) ?: '{}',
            associative: true,
        );
        if (!is_array($composerContent)) {
            $composerContent = [];
        }

        $version = $composerContent['version'] ?? '-';
        $versionParts = explode('.', $version) + array_fill(0, 4, '0');

        return implode('.', $versionParts);
    }
}