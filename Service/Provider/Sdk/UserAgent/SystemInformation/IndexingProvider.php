<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider\Sdk\UserAgent\SystemInformation;

// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Composer\InstalledVersions;
use Klevu\PhpSDK\Provider\UserAgentProviderInterface;

class IndexingProvider implements UserAgentProviderInterface
{
    public const PRODUCT_NAME = 'klevu-m2-indexing';

    /**
     * @return string
     */
    public function execute(): string
    {
        try {
            $version = InstalledVersions::getVersion('klevu/module-m2-indexing');
        } catch (\OutOfBoundsException) {
            $version = null;
        }

        return $version
            ? sprintf('%s/%s', static::PRODUCT_NAME, $version)
            : static::PRODUCT_NAME;
    }
}
