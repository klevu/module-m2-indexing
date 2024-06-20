<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Traits;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

trait OptionSourceToHashTrait
{
    /**
     * @param OptionSourceInterface $optionSource
     * @return Phrase[]
     */
    private function getHashForOptionSource(OptionSourceInterface $optionSource): array
    {
        $options = $optionSource->toOptionArray();

        return array_combine(
            array_column($options, 'value'),
            array_column($options, 'label'),
        );
    }
}
