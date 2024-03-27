<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Model\EntityIndexingDeleteRecordInterface;

class EntityIndexingDeleteRecord implements EntityIndexingDeleteRecordInterface
{
    /**
     * @var int
     */
    private readonly int $recordId;
    /**
     * @var int
     */
    private readonly int $entityId;
    /**
     * @var int|null
     */
    private readonly ?int $parentId;

    /**
     * @param int $recordId
     * @param int $entityId
     * @param int|null $parentId
     */
    public function __construct(
        int $recordId,
        int $entityId,
        ?int $parentId = null,
    ) {
        $this->recordId = $recordId;
        $this->entityId = $entityId;
        $this->parentId = $parentId;
    }

    /**
     * @return int
     */
    public function getRecordId(): int
    {
        return $this->recordId;
    }

    /**
     * @return int
     */
    public function getEntityId(): int
    {
        return $this->entityId;
    }

    /**
     * @return int|null
     */
    public function getParentId(): ?int
    {
        return $this->parentId;
    }
}
