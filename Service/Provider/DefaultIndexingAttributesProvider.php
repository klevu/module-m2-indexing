<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Configuration\Exception\ApiKeyNotFoundException;
use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
use Klevu\IndexingApi\Service\Provider\MagentoToKlevuAttributeMapperProviderInterface;
use Klevu\IndexingApi\Service\Provider\StandardAttributesProviderInterface;
use Klevu\PhpSDK\Exception\ApiExceptionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class DefaultIndexingAttributesProvider implements DefaultIndexingAttributesProviderInterface
{
    /**
     * @var MagentoToKlevuAttributeMapperInterface
     */
    private readonly MagentoToKlevuAttributeMapperInterface $attributeToNameMapper;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var StandardAttributesProviderInterface
     */
    private readonly StandardAttributesProviderInterface $standardAttributesProvider;
    /**
     * @var string
     */
    private readonly string $entityType;

    /**
     * @param MagentoToKlevuAttributeMapperProviderInterface $attributeToNameMapperProvider
     * @param LoggerInterface $logger
     * @param StandardAttributesProviderInterface $standardAttributesProvider
     * @param string $entityType
     */
    public function __construct(
        MagentoToKlevuAttributeMapperProviderInterface $attributeToNameMapperProvider,
        LoggerInterface $logger,
        StandardAttributesProviderInterface $standardAttributesProvider,
        string $entityType,
    ) {
        $this->attributeToNameMapper = $attributeToNameMapperProvider->getByType(entityType: $entityType);
        $this->logger = $logger;
        $this->standardAttributesProvider = $standardAttributesProvider;
        $this->entityType = $entityType;
    }

    /**
     * @param string|null $apiKey
     *
     * @return array<string, IndexType>
     * @throws ApiKeyNotFoundException
     */
    public function get(?string $apiKey = null): array
    {
        $return = [];
        foreach ($this->getStandardAttributes(apiKey: $apiKey) as $attributeName) {
            try {
                $key = $this->attributeToNameMapper->reverseForCode(
                    attributeName: $attributeName,
                    apiKey: $apiKey,
                );
                $return[$key] = IndexType::INDEX;
            } catch (NoSuchEntityException) {
                $this->logger->debug(
                    message: 'Method: {method}, Debug: {message}',
                    context: [
                        'method' => __METHOD__,
                        'message' => sprintf(
                            'Klevu Standard Attribute %s is not mapped to any Magento attribute for %s',
                            $attributeName,
                            $this->entityType,
                        ),
                    ],
                );
            } catch (ApiExceptionInterface $exception) {
                $this->logger->error(
                    message: 'Method: {method}, Error: {message}',
                    context: [
                        'method' => __METHOD__,
                        'message' => $exception->getMessage(),
                    ],
                );
            }
        }

        return $return;
    }

    /**
     * @param string|null $apiKey
     *
     * @return string[]
     */
    private function getStandardAttributes(?string $apiKey = null): array
    {
        $return = [];
        try {
            $return = $apiKey
                ? $this->standardAttributesProvider->getAttributeCodes(apiKey: $apiKey, includeAliases: false)
                : $this->standardAttributesProvider->getAttributeCodesForAllApiKeys(includeAliases: false);
        } catch (ApiKeyNotFoundException | ApiExceptionInterface $exception) {
            $this->logger->error(
                message: 'Method: {method}, Error: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => $exception->getMessage(),
                ],
            );
        }

        return $return;
    }
}
