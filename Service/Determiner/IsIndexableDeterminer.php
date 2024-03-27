<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Determiner;

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
     * @var IsIndexableDeterminerInterface[]
     */
    private array $isIndexableDeterminers = [];

    /**
     * @param IsLoggingEnabledServiceInterface $isLoggingEnabledService
     * @param IsIndexableDeterminerInterface[] $isIndexableDeterminers
     */
    public function __construct(
        IsLoggingEnabledServiceInterface $isLoggingEnabledService,
        array $isIndexableDeterminers = [],
    ) {
        $this->isLoggingEnabledService = $isLoggingEnabledService;
        array_walk($isIndexableDeterminers, [$this, 'addIndexableDeterminer']);
    }

    /**
     * @param ExtensibleDataInterface|PageInterface $entity
     * @param StoreInterface $store
     *
     * @return bool
     */
    public function execute(
        ExtensibleDataInterface|PageInterface $entity,
        StoreInterface $store,
    ): bool {
        $isIndexable = true;
        $isDebuggingEnabled = $this->isDebugLoggingEnabled($store);
        foreach ($this->isIndexableDeterminers as $isIndexableDeterminer) {
            $isIndexable = $isIndexable && $isIndexableDeterminer->execute(entity: $entity, store: $store);
            if (!$isIndexable && !$isDebuggingEnabled) {
                break;
            }
        }

        return $isIndexable;
    }

    /**
     * @param IsIndexableDeterminerInterface $isIndexableDeterminer
     *
     * @return void
     */
    private function addIndexableDeterminer(IsIndexableDeterminerInterface $isIndexableDeterminer): void
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
