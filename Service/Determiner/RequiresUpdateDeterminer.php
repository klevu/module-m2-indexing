<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Determiner;

use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Service\Determiner\RequiresUpdateCriteriaInterface;
use Klevu\IndexingApi\Service\Determiner\RequiresUpdateDeterminerInterface;
use Psr\Log\LoggerInterface;

class RequiresUpdateDeterminer implements RequiresUpdateDeterminerInterface
{
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var array<string, RequiresUpdateCriteriaInterface>
     */
    private array $criteriaServices = [];

    /**
     * @param LoggerInterface $logger
     * @param array<string, RequiresUpdateCriteriaInterface> $criteriaServices
     */
    public function __construct(
        LoggerInterface $logger,
        array $criteriaServices = [],
    ) {
        $this->logger = $logger;
        array_walk($criteriaServices, [$this, 'addCriteriaService']);
    }

    /**
     * @param IndexingEntityInterface $indexingEntity
     *
     * @return bool
     */
    public function execute(IndexingEntityInterface $indexingEntity): bool
    {
        $requiresUpdate = false;

        $entityType = $indexingEntity->getTargetEntityType();
        foreach (array_keys($indexingEntity->getRequiresUpdateOrigValues()) as $criteriaIdentifier) {
            $criteriaServices = $this->getCriteriaServicesForEntityTypeAndIdentifier(
                entityType: $entityType,
                criteriaIdentifier: $criteriaIdentifier,
            );
            if (empty($criteriaServices)) {
                $this->logger->debug(
                    message: 'No RequiresUpdateCriteriaInterface services found '
                        . 'for criteria identifier: {criteriaIdentifier}',
                    context: [
                        'method' => __METHOD__,
                        'criteriaIdentifier' => $criteriaIdentifier,
                        'indexingEntity' => $indexingEntity->toArray(),
                    ],
                );
                continue;
            }

            foreach ($criteriaServices as $criteriaService) {
                $requiresUpdate = $criteriaService->execute(
                    indexingEntity: $indexingEntity,
                );

                if ($requiresUpdate) {
                    break 2;
                }
            }
        }

        return $requiresUpdate;
    }

    /**
     * @param RequiresUpdateCriteriaInterface|null $service
     * @param string $key
     *
     * @return void
     */
    private function addCriteriaService(
        ?RequiresUpdateCriteriaInterface $service,
        string $key,
    ): void {
        if ($service) {
            $this->criteriaServices[$key] = $service;
        }
    }

    /**
     * @param string $entityType
     * @param string $criteriaIdentifier
     *
     * @return RequiresUpdateCriteriaInterface[]
     */
    private function getCriteriaServicesForEntityTypeAndIdentifier(
        string $entityType,
        string $criteriaIdentifier,
    ): array {
        return array_filter(
            array: $this->criteriaServices,
            callback: static fn (RequiresUpdateCriteriaInterface $criteriaService) => (
                $criteriaService->getEntityType() === $entityType
                && $criteriaService->getCriteriaIdentifier() === $criteriaIdentifier
            ),
        );
    }
}
