<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Api\Data\SyncHistoryEntityRecordInterface;
use Klevu\IndexingApi\Api\Data\SyncHistoryEntitySearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class SyncHistoryEntitySearchResults extends SearchResults implements SyncHistoryEntitySearchResultsInterface
{
    /**
     * @return SyncHistoryEntityRecordInterface[]
     */
    public function getItems(): array
    {
        return $this->_get(self::KEY_ITEMS) ?? [];
    }

    /**
     * @param SyncHistoryEntityRecordInterface[] $items
     *
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setItems(array $items): SyncHistoryEntitySearchResultsInterface
    {
        foreach ($items as $key => $item) {
            if (!($item instanceof SyncHistoryEntityRecordInterface)) {
                throw new \InvalidArgumentException(
                    message: sprintf(
                        'Argument "items" must contain instances of "%s", "%s" received for item %s.',
                        SyncHistoryEntityRecordInterface::class,
                        get_debug_type($item),
                        $key,
                    ),
                );
            }
        }

        return $this->setData(self::KEY_ITEMS, $items);
    }
}
