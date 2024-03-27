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
     * @var string[]
     */
    private readonly array $messages;
    /**
     * @var int[]
     */
    private readonly array $processedIds;

    /**
     * @param bool $isSuccess
     * @param string[] $messages
     * @param int[] $processedIds
     */
    public function __construct(
        bool $isSuccess,
        array $messages = [],
        array $processedIds = [],
    ) {
        $this->isSuccess = $isSuccess;
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
