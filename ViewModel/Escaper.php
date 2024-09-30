<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\ViewModel;

use Magento\Framework\Escaper as FrameworkEscaper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class Escaper implements ArgumentInterface
{
    /**
     * @var FrameworkEscaper
     */
    private readonly FrameworkEscaper $escaper;

    /**
     * @param FrameworkEscaper $escaper
     */
    public function __construct(FrameworkEscaper $escaper)
    {
        $this->escaper = $escaper;
    }

    /**
     * @return FrameworkEscaper
     */
    public function getEscaper(): FrameworkEscaper
    {
        return $this->escaper;
    }
}
