<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Determiner;

use Klevu\IndexingApi\Service\Determiner\IsIndexableConditionInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Klevu\LoggerApi\Service\IsLoggingEnabledServiceInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Store\Api\Data\StoreInterface;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Monolog\Logger;

class IsIndexableDeterminer implements IsIndexableDeterminerInterface
{
    /**
     * @var IsLoggingEnabledServiceInterface
     */
    private readonly IsLoggingEnabledServiceInterface $isLoggingEnabledService;
    /**
     * @var IsIndexableConditionInterface[]
     */
    private array $isIndexableConditions = [];

    /**
     * @param IsLoggingEnabledServiceInterface $isLoggingEnabledService
     * @param IsIndexableConditionInterface[] $isIndexableConditions
     */
    public function __construct(
        IsLoggingEnabledServiceInterface $isLoggingEnabledService,
        array $isIndexableConditions = [],
    ) {
        $this->isLoggingEnabledService = $isLoggingEnabledService;
        array_walk($isIndexableConditions, [$this, 'addIndexableCondition']);
    }

    /**
     * @param ExtensibleDataInterface|PageInterface $entity
     * @param StoreInterface $store
     * @param string $entitySubtype
     *
     * @return bool
     */
    public function execute(
        ExtensibleDataInterface|PageInterface $entity,
        StoreInterface $store,
        string $entitySubtype = '',
    ): bool {
        $isIndexable = true;
        $isDebuggingEnabled = $this->isDebugLoggingEnabled($store);
        foreach ($this->isIndexableConditions as $isIndexableCondition) {
            $isIndexable = $isIndexable && $isIndexableCondition->execute(
                    entity: $entity,
                    store: $store,
                    entitySubtype: $entitySubtype,
                );
            if (!$isIndexable && !$isDebuggingEnabled) {
                break;
            }
        }

        return $isIndexable;
    }

    /**
     * @param IsIndexableConditionInterface $isIndexableCondition
     *
     * @return void
     */
    private function addIndexableCondition(IsIndexableConditionInterface $isIndexableCondition): void
    {
        $this->isIndexableConditions[] = $isIndexableCondition;
    }

    /**
     * @param StoreInterface $store
     *
     * @return bool
     */
    private function isDebugLoggingEnabled(StoreInterface $store): bool
    {
        return $this->isLoggingEnabledService->execute(
            logLevel: Logger::DEBUG,
            store: $store,
        );
    }
}
