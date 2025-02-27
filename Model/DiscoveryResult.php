<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Api\Data\DiscoveryResultInterface;

class DiscoveryResult implements DiscoveryResultInterface
{
    /**
     * @var bool
     */
    private readonly bool $isSuccess;
    /**
     * @var string
     */
    private readonly string $action;
    /**
     * @var string
     */
    private readonly string $entityType;
    /**
     * @var string[]
     */
    private readonly array $messages;
    /**
     * @var int[]
     */
    private readonly array $processedIds;

    /**
     * @param bool $isSuccess
     * @param string $action
     * @param string $entityType
     * @param string[] $messages
     * @param int[] $processedIds
     */
    public function __construct(
        bool $isSuccess,
        string $action = '',
        string $entityType = '',
        array $messages = [],
        array $processedIds = [],
    ) {
        $this->isSuccess = $isSuccess;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->messages = $messages;
        $this->processedIds = $processedIds;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    /**
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * @return bool
     */
    public function hasMessages(): bool
    {
        return (bool)$this->messages;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @return bool
     */
    public function hasProcessedIds(): bool
    {
        return (bool)$this->processedIds;
    }

    /**
     * @return int[]
     */
    public function getProcessedIds(): array
    {
        return $this->processedIds;
    }
}
