<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Model\ResourceModel\IndexingEntity;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity as IndexingEntityResourceModel;
use Klevu\Indexing\Traits\CastIndexingEntityPropertiesToCorrectType;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class Collection extends AbstractCollection
{
    use CastIndexingEntityPropertiesToCorrectType;

    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param SerializerInterface $serializer
     * @param AdapterInterface|null $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        SerializerInterface $serializer,
        ?AdapterInterface $connection = null,
        ?AbstractDb $resource = null,
    ) {
        $this->serializer = $serializer;

        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $connection,
            $resource,
        );
    }

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            model: IndexingEntity::class,
            resourceModel: IndexingEntityResourceModel::class,
        );
    }

    /**
     * Ensure returned IndexingEntity data is the correct type, by default all fields returned as strings
     *
     * @return $this|Collection
     */
    protected function _afterLoad() // phpcs:ignore SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint, PSR2.Methods.MethodDeclaration.Underscore, Generic.Files.LineLength.TooLong
    {
        parent::_afterLoad();

        /** @var IndexingEntity $item */
        foreach ($this->getItems() as $item) {
            $this->castPropertiesToCorrectType($item);
        }
        $this->_eventManager->dispatch(
            'klevu_indexing_entity_collection_load_after',
            ['collection' => $this],
        );

        return $this;
    }
}
