<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\IndexingApi\Service\Provider\PipelineConfigurationOverridesHandlerProviderInterface;
use Klevu\IndexingApi\Service\RegenerateConfigurationOverridesInterface;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;

class RegenerateConfigurationOverrides implements RegenerateConfigurationOverridesInterface
{
    /**
     * @var PipelineConfigurationOverridesHandlerProviderInterface
     */
    private readonly PipelineConfigurationOverridesHandlerProviderInterface $configurationOverridesHandlerProvider;
    /**
     * @var EventManagerInterface
     */
    private readonly EventManagerInterface $eventManager;
    /**
     * @var string|null
     */
    private readonly ?string $entityType;

    /**
     * @param PipelineConfigurationOverridesHandlerProviderInterface $configurationOverridesHandlerProvider
     * @param EventManagerInterface $eventManager
     * @param string|null $entityType
     */
    public function __construct(
        PipelineConfigurationOverridesHandlerProviderInterface $configurationOverridesHandlerProvider,
        EventManagerInterface $eventManager,
        ?string $entityType = null,
    ) {
        $this->configurationOverridesHandlerProvider = $configurationOverridesHandlerProvider;
        $this->eventManager = $eventManager;
        $this->entityType = $entityType;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        foreach ($this->getProductOverrideHandlers() as $entityType => $configurationOverridesHandlers) {
            foreach ($configurationOverridesHandlers as $name => $configurationOverridesHandler) {
                $this->eventManager->dispatch(
                    'klevu_indexing_regenerate_configuration_overrides_before',
                    [
                        'entity_type' => $entityType,
                        'configuration_override_handler_name' => $name,
                        'configuration_override_handler' => $configurationOverridesHandler,
                    ],
                );
                $configurationOverridesHandler->execute();
                $this->eventManager->dispatch(
                    'klevu_indexing_regenerate_configuration_overrides_after',
                    [
                        'entity_type' => $entityType,
                        'configuration_override_handler_name' => $name,
                        'configuration_override_handler' => $configurationOverridesHandler,
                    ],
                );
            }
        }
    }

    /**
     * @return array<string, array<string, ConfigurationOverridesHandlerInterface>>
     */
    private function getProductOverrideHandlers(): array
    {
        $overrideHandlers = $this->configurationOverridesHandlerProvider->get();
        if (null === $this->entityType) {
            return $overrideHandlers;
        }

        return array_filter(
            array: $overrideHandlers,
            callback: fn (string $key) => $key === $this->entityType,
            mode: ARRAY_FILTER_USE_KEY,
        );
    }
}
