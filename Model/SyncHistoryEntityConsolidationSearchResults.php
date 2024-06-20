<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationRecordInterface;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntityConsolidationSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class SyncHistoryEntityConsolidationSearchResults extends SearchResults
    implements SyncHistoryEntityConsolidationSearchResultsInterface
{
    /**
     * @return SyncHistoryEntityConsolidationRecordInterface[]
     */
    public function getItems(): array
    {
        return $this->_get(self::KEY_ITEMS) ?? [];
    }

    /**
     * @param SyncHistoryEntityConsolidationRecordInterface[] $items
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setItems(array $items): SyncHistoryEntityConsolidationSearchResultsInterface
    {
        foreach ($items as $key => $item) {
            if (!($item instanceof SyncHistoryEntityConsolidationRecordInterface)) {
                throw new \InvalidArgumentException(
                    message: sprintf(
                        'Argument "items" must contain instances of "%s", "%s" received for item %s.',
                        SyncHistoryEntityConsolidationRecordInterface::class,
                        get_debug_type($item),
                        $key,
                    ),
                );
            }
        }

        return $this->setData(self::KEY_ITEMS, $items);
    }
}
