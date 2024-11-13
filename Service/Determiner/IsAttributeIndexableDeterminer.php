<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Determiner;

use Klevu\IndexingApi\Service\Determiner\IsAttributeIndexableConditionInterface;
use Klevu\IndexingApi\Service\Determiner\IsAttributeIndexableDeterminerInterface;
use Klevu\LoggerApi\Service\IsLoggingEnabledServiceInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Store\Api\Data\StoreInterface;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Monolog\Logger;

class IsAttributeIndexableDeterminer implements IsAttributeIndexableDeterminerInterface
{
    /**
     * @var IsLoggingEnabledServiceInterface
     */
    private readonly IsLoggingEnabledServiceInterface $isLoggingEnabledService;
    /**
     * @var IsAttributeIndexableDeterminerInterface[]
     */
    private array $isIndexableConditions = [];

    /**
     * @param IsLoggingEnabledServiceInterface $isLoggingEnabledService
     * @param IsAttributeIndexableDeterminerInterface[] $isIndexableConditions
     */
    public function __construct(
        IsLoggingEnabledServiceInterface $isLoggingEnabledService,
        array $isIndexableConditions = [],
    ) {
        $this->isLoggingEnabledService = $isLoggingEnabledService;
        array_walk($isIndexableConditions, [$this, 'addIndexableCondition']);
    }

    /**
     * @param AttributeInterface $attribute
     * @param StoreInterface $store
     *
     * @return bool
     */
    public function execute(
        AttributeInterface $attribute,
        StoreInterface $store,
    ): bool {
        $isIndexable = true;
        $isDebuggingEnabled = $this->isDebugLoggingEnabled($store);
        foreach ($this->isIndexableConditions as $isIndexableCondition) {
            $isIndexable = $isIndexable && $isIndexableCondition->execute(attribute: $attribute, store: $store);
            if (!$isIndexable && !$isDebuggingEnabled) {
                break;
            }
        }

        return $isIndexable;
    }

    /**
     * @param IsAttributeIndexableConditionInterface $isIndexableCondition
     *
     * @return void
     */
    private function addIndexableCondition(IsAttributeIndexableConditionInterface $isIndexableCondition): void
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
