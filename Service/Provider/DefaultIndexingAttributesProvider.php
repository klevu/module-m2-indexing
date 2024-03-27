<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\IndexingApi\Model\Source\IndexType;
use Klevu\IndexingApi\Model\Source\StandardAttribute;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingApi\Service\Provider\DefaultIndexingAttributesProviderInterface;
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
     * @var string
     */
    private readonly string $entityType;

    /**
     * @param MagentoToKlevuAttributeMapperInterface $attributeToNameMapper
     * @param LoggerInterface $logger
     * @param string $entityType
     */
    public function __construct(
        MagentoToKlevuAttributeMapperInterface $attributeToNameMapper,
        LoggerInterface $logger,
        string $entityType,
    ) {
        $this->attributeToNameMapper = $attributeToNameMapper;
        $this->logger = $logger;
        $this->entityType = $entityType;
    }

    /**
     * @return array<string, IndexType>
     */
    public function get(): array
    {
        $return = [];
        foreach (StandardAttribute::indexTypesArray() as $attributeName => $indexType) {
            try {
                $return[$this->attributeToNameMapper->reverseForCode($attributeName)] = $indexType;
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
            }
        }

        return $return;
    }
}
