<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Api\Data\SyncResultInterface;

class SyncResult implements SyncResultInterface
{
    /**
     * @var bool
     */
    private readonly bool $isSuccess;
    /**
     * @var int
     */
    private readonly int $code;
    /**
     * @var string[]
     */
    private readonly array $messages;

    /**
     * @param bool $isSuccess
     * @param int $code
     * @param string[] $messages
     */
    public function __construct(
        bool $isSuccess,
        int $code,
        array $messages = [],
    ) {
        $this->isSuccess = $isSuccess;
        $this->code = $code;
        $this->messages = $messages;
    }

    /**
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->isSuccess;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return (int)$this->code;
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
}
