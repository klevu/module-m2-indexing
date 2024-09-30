<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Provider;

use Klevu\Indexing\Service\Mapper\MagentoToKlevuAttributeMapperFactory;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\IndexingApi\Service\Provider\MagentoToKlevuAttributeMapperProviderInterface;
use Psr\Log\LoggerInterface;

class MagentoToKlevuAttributeMapperProvider implements MagentoToKlevuAttributeMapperProviderInterface
{
    /**
     * @var MagentoToKlevuAttributeMapperFactory
     */
    private readonly MagentoToKlevuAttributeMapperFactory $attributeMapperFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;
    /**
     * @var array<string, MagentoToKlevuAttributeMapperInterface>
     */
    private array $magentoToKlevuAttributeMappers = [];

    /**
     * @param MagentoToKlevuAttributeMapperFactory $attributeMapperFactory
     * @param LoggerInterface $logger
     * @param mixed[] $magentoToKlevuAttributeMappers
     */
    public function __construct(
        MagentoToKlevuAttributeMapperFactory $attributeMapperFactory,
        LoggerInterface $logger,
        array $magentoToKlevuAttributeMappers,
    ) {
        $this->attributeMapperFactory = $attributeMapperFactory;
        array_walk($magentoToKlevuAttributeMappers, [$this, 'addMagentoToKlevuAttributeMapper']);
        $this->logger = $logger;
    }

    /**
     * @return array<string, MagentoToKlevuAttributeMapperInterface>
     */
    public function get(): array
    {
        return $this->magentoToKlevuAttributeMappers;
    }

    /**
     * @param string $entityType
     *
     * @return MagentoToKlevuAttributeMapperInterface
     */
    public function getByType(string $entityType): MagentoToKlevuAttributeMapperInterface
    {
        if (!($this->magentoToKlevuAttributeMappers[$entityType] ?? null)) {
            $this->logger->info(
                message: 'Method: {method}, Info: {message}',
                context: [
                    'method' => __METHOD__,
                    'message' => sprintf(
                        'The requested mapper does not exist. Requested %s',
                        $entityType,
                    ),
                ],
            );

            return $this->generateAttributeMapperForEntityType(entityType: $entityType);
        }

        return $this->magentoToKlevuAttributeMappers[$entityType];
    }

    /**
     * @param MagentoToKlevuAttributeMapperInterface $magentoToKlevuAttributeMapper
     * @param string $entityType
     *
     * @return void
     */
    private function addMagentoToKlevuAttributeMapper(
        MagentoToKlevuAttributeMapperInterface $magentoToKlevuAttributeMapper,
        string $entityType,
    ): void {
        $this->magentoToKlevuAttributeMappers[$entityType] = $magentoToKlevuAttributeMapper;
    }

    /**
     * Returns a generic attribute mapping class for the requested entity type
     * Note this class uses the default attribute mapping, i.e. $attributeMapping = []
     *
     * @param string $entityType
     *
     * @return MagentoToKlevuAttributeMapperInterface
     */
    private function generateAttributeMapperForEntityType(string $entityType): MagentoToKlevuAttributeMapperInterface
    {
        return $this->attributeMapperFactory->create([
            'entityType' => $entityType,
        ]);
    }
}
