<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider\Cache;

use Klevu\Indexing\Cache\Attributes;
use Klevu\IndexingApi\Service\Provider\Cache\AttributesCacheKeyProviderInterface;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Encryption\EncryptorFactory;

class AttributesCacheKeyProvider implements AttributesCacheKeyProviderInterface
{
    public const CACHE_CONCATENATION_STRING = '_apikey_';

    /**
     * Used factory over interface as Magento\Framework\Encryption\EncryptorInterface
     * does not contain the second parameter for the hash method
     *
     * @var EncryptorFactory
     */
    private readonly EncryptorFactory $encryptorFactory;

    /**
     * @param EncryptorFactory $encryptorFactory
     */
    public function __construct(EncryptorFactory $encryptorFactory)
    {
        $this->encryptorFactory = $encryptorFactory;
    }

    /**
     * @param string $apiKey
     *
     * @return string
     */
    public function get(string $apiKey): string
    {
        /** @var Encryptor $encryptor */
        $encryptor = $this->encryptorFactory->create();

        return Attributes::TYPE_IDENTIFIER
            . static::CACHE_CONCATENATION_STRING
            . $encryptor->hash(data: $apiKey, version: Encryptor::HASH_VERSION_SHA256);
    }
}
