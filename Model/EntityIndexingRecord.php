<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Model\EntityIndexingRecordInterface;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Framework\Api\ExtensibleDataInterface;

class EntityIndexingRecord implements EntityIndexingRecordInterface
{
    /**
     * @var int
     */
    private readonly int $recordId;
    /**
     * @var ExtensibleDataInterface|PageInterface
     */
    private readonly ExtensibleDataInterface|PageInterface $entity;
    /**
     * @var ExtensibleDataInterface|PageInterface|null
     */
    private readonly ExtensibleDataInterface|PageInterface|null $parent;

    /**
     * @param int $recordId
     * @param ExtensibleDataInterface|PageInterface $entity
     * @param ExtensibleDataInterface|PageInterface|null $parent
     */
    public function __construct(
        int $recordId,
        ExtensibleDataInterface|PageInterface $entity,
        ExtensibleDataInterface|PageInterface|null $parent = null,
    ) {
        $this->recordId = $recordId;
        $this->entity = $entity;
        $this->parent = $parent;
    }

    /**
     * @return int
     */
    public function getRecordId(): int
    {
        return $this->recordId;
    }

    /**
     * @return ExtensibleDataInterface|PageInterface
     */
    public function getEntity(): ExtensibleDataInterface|PageInterface
    {
        return $this->entity;
    }

    /**
     * @return ExtensibleDataInterface|PageInterface|null
     */
    public function getParent(): ExtensibleDataInterface|PageInterface|null
    {
        return $this->parent;
    }
}
