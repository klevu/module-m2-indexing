<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Framework;

use Magento\Framework\Url as CoreUrl;

class Url extends CoreUrl
{
    /**
     * @param mixed[] $params
     *
     * @return string
     */
    public function getBaseUrl($params = []) // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint,SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint, Generic.Files.LineLength.TooLong
    {
        $routeParamsResolver = $this->getRouteParamsResolver();

        $routeParamsScope = $routeParamsResolver->getData('scope');
        if (
            !array_key_exists('_scope', $params)
            && null !== $routeParamsScope
        ) {
            $params['_scope'] = $routeParamsScope;
        }

        $routeParamsSecure = $routeParamsResolver->getData('secure');
        if (
            !array_key_exists('_secure', $params)
            && null !== $routeParamsSecure
        ) {
            $params['_secure'] = $routeParamsSecure;
        }

        return parent::getBaseUrl($params);
    }
}
