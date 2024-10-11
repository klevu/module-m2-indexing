<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\IndexingApi\Service\EntityIndexerServiceInterface;
use Klevu\IndexingApi\Service\Provider\PipelineConfigurationProviderInterface;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;

class PipelineConfigurationProvider implements PipelineConfigurationProviderInterface
{
    /**
     * @var EntityIndexerServiceInterface[]
     */
    private array $entityIndexerServices = [];
    /**
     * @var array<string, ConfigurationOverridesHandlerInterface[]>
     */
    private array $configurationOverridesHandlers = [];

    /**
     * @param EntityIndexerServiceInterface[] $entityIndexerServices
     * @param array<string, ConfigurationOverridesHandlerInterface[]> $configurationOverridesHandlers
     */
    public function __construct(
        array $entityIndexerServices,
        array $configurationOverridesHandlers = [],
    ) {
        array_walk($entityIndexerServices, [$this, 'addEntityIndexerService']);
        foreach ($configurationOverridesHandlers as $entityType => $configurationOverridesHandlersForEntityType) {
            array_walk(
                $configurationOverridesHandlersForEntityType,
                function (ConfigurationOverridesHandlerInterface $configurationOverridesHandler) use ($entityType): void { // phpcs:ignore Generic.Files.LineLength.TooLong
                    $this->addConfigurationOverridesHandler(
                        configurationOverridesHandler: $configurationOverridesHandler,
                        entityType: $entityType,
                    );
                },
            );
        }
    }

    /**
     *  If you have a custom implementation of the EntityIndexerService which does not contain a buildPipeline method,
     *   you can plug into this method to return configuration from your class
     *  Note: we use reflection within this class, which is inherently unreliable and not recommended
     *   It is used as the core Klevu classes are known, and the purpose of this tool is debugging
     *
     * @param string $pipelineIdentifier
     *
     * @return mixed[]|null
     */
    public function get(string $pipelineIdentifier): ?array
    {
        if (!trim($pipelineIdentifier)) {
            return null;
        }
        $entityIndexerService = $this->entityIndexerServices[$pipelineIdentifier] ?? null;
        if (!$entityIndexerService) {
            return null;
        }
        [$entityType] = explode('::', $pipelineIdentifier);
        foreach ($this->configurationOverridesHandlers[$entityType] ?? [] as $configurationOverridesHandler) {
            $configurationOverridesHandler->execute();
        }

        $entityIndexerServiceReflection = new \ReflectionObject($entityIndexerService);
        try {
            $pipelineConfigurationFilepathProperty = $entityIndexerServiceReflection->getProperty(
                name: 'pipelineConfigurationFilepath',
            );
            $pipelineConfigurationFilepath = $pipelineConfigurationFilepathProperty->getValue(
                object: $entityIndexerService,
            );
            $pipelineConfigurationOverridesFilepathsProperty = $entityIndexerServiceReflection->getProperty(
                name: 'pipelineConfigurationOverridesFilepaths',
            );
            $pipelineConfigurationOverridesFilepaths = $pipelineConfigurationOverridesFilepathsProperty->getValue(
                object: $entityIndexerService,
            );

            $pipelineBuilderProperty = $entityIndexerServiceReflection->getProperty(name: 'pipelineBuilder');
            $pipelineBuilder = $pipelineBuilderProperty->getValue(object: $entityIndexerService);

            $platformPipelinesPipelineBuilderReflection = new \ReflectionObject($pipelineBuilder);
            $sdkPipelinesPipelineBuilderReflection = $platformPipelinesPipelineBuilderReflection->getParentClass();
            $basePipelineBuilderReflection = $sdkPipelinesPipelineBuilderReflection->getParentClass();

            $configurationBuilderProperty = $basePipelineBuilderReflection->getProperty(
                name: 'configurationBuilder',
            );
            $configurationBuilder = $configurationBuilderProperty->getValue(object: $pipelineBuilder);

            $pipelineConfiguration = $configurationBuilder->buildFromFiles(
                pipelineDefinitionFile: $pipelineConfigurationFilepath,
                pipelineOverridesFiles: $pipelineConfigurationOverridesFilepaths,
            );
        } catch (\ReflectionException) {
            $pipelineConfiguration = null;
        }

        return $pipelineConfiguration;
    }

    /**
     * @param EntityIndexerServiceInterface $entityIndexerService
     * @param string $identifier
     *
     * @return void
     */
    private function addEntityIndexerService(
        EntityIndexerServiceInterface $entityIndexerService,
        string $identifier,
    ): void {
        $this->entityIndexerServices[$identifier] = $entityIndexerService;
    }

    /**
     * @param ConfigurationOverridesHandlerInterface $configurationOverridesHandler
     * @param string $entityType
     *
     * @return void
     */
    private function addConfigurationOverridesHandler(
        ConfigurationOverridesHandlerInterface $configurationOverridesHandler,
        string $entityType,
    ): void {
        $this->configurationOverridesHandlers[$entityType] ??= [];
        $this->configurationOverridesHandlers[$entityType][] = $configurationOverridesHandler;
    }
}
