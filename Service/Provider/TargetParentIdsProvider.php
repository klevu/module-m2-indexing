<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\IndexingApi\Service\Provider\TargetParentIdsProviderInterface;
use Psr\Log\LoggerInterface;

class TargetParentIdsProvider implements TargetParentIdsProviderInterface
{
    /**
     * @var LoggerInterface 
     */
    private readonly LoggerInterface $logger;
    /**
     * @var array<string, TargetParentIdsProviderInterface>
     */
    private array $targetParentIdsProviders = [];

    /**
     * @param LoggerInterface $logger
     * @param array<string, TargetParentIdsProviderInterface> $targetParentIdsProviders
     */
    public function __construct(
        LoggerInterface $logger,
        array $targetParentIdsProviders = [],
    ) {
        $this->logger = $logger;
        array_walk($targetParentIdsProviders, [$this, 'addTargetParentIdsProvider']);
    }

    /**
     * @param string $entityType
     * @param int $targetId
     *
     * @return int[]
     */
    public function get(
        string $entityType,
        int $targetId,
    ): array {
        return array_key_exists($entityType, $this->targetParentIdsProviders)
            ? $this->targetParentIdsProviders[$entityType]->get($entityType, $targetId)
            : [];
    }

    /**
     * @param TargetParentIdsProviderInterface|null $targetParentIdsProvider
     * @param string $entityType
     *
     * @return void
     */
    private function addTargetParentIdsProvider(
        ?TargetParentIdsProviderInterface $targetParentIdsProvider,
        string $entityType,
    ): void {
        if (!$targetParentIdsProvider) {
            return;
        }
        
        $this->targetParentIdsProviders[$entityType] = $targetParentIdsProvider;
    }
}
