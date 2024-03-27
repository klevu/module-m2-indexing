<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Api\Data\IndexerResultInterface;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;

class IndexerResult implements IndexerResultInterface
{
    /**
     * @var IndexerResultStatuses|null
     */
    private ?IndexerResultStatuses $status = null;
    /**
     * @var string[]
     */
    private array $messages = [];
    /**
     * @var mixed
     */
    private mixed $pipelineResult = null;

    /**
     * @return IndexerResultStatuses|null
     */
    public function getStatus(): ?IndexerResultStatuses
    {
        return $this->status;
    }

    /**
     * @param IndexerResultStatuses $status
     * @return void
     */
    public function setStatus(IndexerResultStatuses $status): void
    {
        $this->status = $status;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param string[] $messages
     * @return void
     */
    public function setMessages(array $messages): void
    {
        $this->messages = array_map('strval', $messages);
    }

    /**
     * @return mixed
     */
    public function getPipelineResult(): mixed
    {
        return $this->pipelineResult;
    }

    /**
     * @param mixed $pipelineResult
     * @return void
     */
    public function setPipelineResult(mixed $pipelineResult): void
    {
        $this->pipelineResult = $pipelineResult;
    }
}
