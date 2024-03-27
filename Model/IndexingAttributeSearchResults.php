<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model;

use Klevu\IndexingApi\Api\Data\IndexingAttributeSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class IndexingAttributeSearchResults extends SearchResults implements IndexingAttributeSearchResultsInterface
{
}
