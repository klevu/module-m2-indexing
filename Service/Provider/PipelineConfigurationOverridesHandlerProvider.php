<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\IndexingApi\Service\Provider\PipelineConfigurationOverridesHandlerProviderInterface;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;

class PipelineConfigurationOverridesHandlerProvider implements PipelineConfigurationOverridesHandlerProviderInterface
{
    /**
     * @var array<string, ConfigurationOverridesHandlerInterface[]>
     */
    private array $configurationOverridesHandlers = [];

    /**
     * @param array<string, ConfigurationOverridesHandlerInterface[]> $configurationOverridesHandlers
     */
    public function __construct(array $configurationOverridesHandlers)
    {
        foreach ($configurationOverridesHandlers as $entityType => $configurationOverridesHandlersForEntityType) {
            array_walk(
                $configurationOverridesHandlersForEntityType,
                function (ConfigurationOverridesHandlerInterface $configurationOverridesHandler, string $key) use ($entityType): void { // phpcs:ignore Generic.Files.LineLength.TooLong
                    $this->addConfigurationOverridesHandler(
                        configurationOverridesHandler: $configurationOverridesHandler,
                        entityType: $entityType,
                        key: $key,
                    );
                },
            );
        }
    }

    /**
     * @return array<string, ConfigurationOverridesHandlerInterface[]>
     */
    public function get(): array
    {
        return $this->configurationOverridesHandlers;
    }

    /**
     * @param ConfigurationOverridesHandlerInterface $configurationOverridesHandler
     * @param string $entityType
     * @param string $key
     *
     * @return void
     */
    private function addConfigurationOverridesHandler(
        ConfigurationOverridesHandlerInterface $configurationOverridesHandler,
        string $entityType,
        string $key,
    ): void {
        $this->configurationOverridesHandlers[$entityType] ??= [];
        $this->configurationOverridesHandlers[$entityType][$key] = $configurationOverridesHandler;
    }
}
