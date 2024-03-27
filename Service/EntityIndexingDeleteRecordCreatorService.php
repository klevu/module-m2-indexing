<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service;

use Klevu\Indexing\Model\EntityIndexingDeleteRecordFactory;
use Klevu\IndexingApi\Model\EntityIndexingDeleteRecordInterface;
use Klevu\IndexingApi\Service\EntityIndexingDeleteRecordCreatorServiceInterface;

class EntityIndexingDeleteRecordCreatorService implements EntityIndexingDeleteRecordCreatorServiceInterface
{
    /**
     * @var EntityIndexingDeleteRecordFactory
     */
    private readonly EntityIndexingDeleteRecordFactory $entityIndexingDeleteRecordFactory;

    /**
     * @param EntityIndexingDeleteRecordFactory $entityIndexingDeleteRecordFactory
     */
    public function __construct(EntityIndexingDeleteRecordFactory $entityIndexingDeleteRecordFactory)
    {
        $this->entityIndexingDeleteRecordFactory = $entityIndexingDeleteRecordFactory;
    }

    /**
     * @param int $recordId
     * @param int $entityId
     * @param int|null $parentId
     *
     * @return EntityIndexingDeleteRecordInterface
     */
    public function execute(
        int $recordId,
        int $entityId,
        ?int $parentId = null,
    ): EntityIndexingDeleteRecordInterface {
        return $this->entityIndexingDeleteRecordFactory->create([
            'recordId' => $recordId,
            'entityId' => $entityId,
            'parentId' => $parentId,
        ]);
    }
}
