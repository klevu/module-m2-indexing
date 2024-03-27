<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Determiner;

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
    private array $isIndexableDeterminers = [];

    /**
     * @param IsLoggingEnabledServiceInterface $isLoggingEnabledService
     * @param IsAttributeIndexableDeterminerInterface[] $isIndexableDeterminers
     */
    public function __construct(
        IsLoggingEnabledServiceInterface $isLoggingEnabledService,
        array $isIndexableDeterminers = [],
    ) {
        $this->isLoggingEnabledService = $isLoggingEnabledService;
        array_walk($isIndexableDeterminers, [$this, 'addIndexableDeterminer']);
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
        foreach ($this->isIndexableDeterminers as $isIndexableDeterminer) {
            $isIndexable = $isIndexable && $isIndexableDeterminer->execute(attribute: $attribute, store: $store);
            if (!$isIndexable && !$isDebuggingEnabled) {
                break;
            }
        }

        return $isIndexable;
    }

    /**
     * @param IsAttributeIndexableDeterminerInterface $isIndexableDeterminer
     *
     * @return void
     */
    private function addIndexableDeterminer(IsAttributeIndexableDeterminerInterface $isIndexableDeterminer): void
    {
        $this->isIndexableDeterminers[] = $isIndexableDeterminer;
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
