<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class Attributes extends TagScope
{
    /**
     * Cache type code unique among all cache types
     */
    public const TYPE_IDENTIFIER = 'klevu_indexing_attributes';
    /**
     * The tag name that limits the cache cleaning scope within a particular tag
     */
    public const CACHE_TAG = 'KLEVU_INDEXING_ATTRIBUTES';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct(
            $cacheFrontendPool->get(static::TYPE_IDENTIFIER),
            static::CACHE_TAG,
        );
    }
}
