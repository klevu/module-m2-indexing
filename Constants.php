<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing;

class Constants
{
    public const CRON_JOB_CODE_INDEXING_ENTITY_DISCOVERY = 'klevu_indexing_discover_entities';
    public const XML_PATH_INDEXING_HISTORY_REMOVAL_AFTER_DAYS = 'klevu/indexing/remove_indexing_history_after_days';
    public const XML_PATH_ATTRIBUTE_CRON_EXPR = 'klevu/indexing/attribute_cron_expr';
    public const XML_PATH_ATTRIBUTE_CRON_FREQUENCY = 'klevu/indexing/attribute_cron_frequency';
    public const XML_PATH_ENTITY_CRON_EXPR = 'klevu/indexing/entity_cron_expr';
    public const XML_PATH_ENTITY_CRON_FREQUENCY = 'klevu/indexing/entity_cron_frequency';
}
