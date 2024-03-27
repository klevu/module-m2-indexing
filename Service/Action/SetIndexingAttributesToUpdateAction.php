<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\Indexing\Exception\IndexingAttributeSaveException;
use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\SetIndexingAttributesToUpdateActionInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Psr\Log\LoggerInterface;

class SetIndexingAttributesToUpdateAction implements SetIndexingAttributesToUpdateActionInterface
{
    /**
     * @var IndexingAttributeRepositoryInterface
     */
    private readonly IndexingAttributeRepositoryInterface $indexingAttributeRepository;
    /**
     * @var SearchCriteriaBuilderFactory
     */
    private readonly SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param IndexingAttributeRepositoryInterface $indexingAttributeRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        IndexingAttributeRepositoryInterface $indexingAttributeRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        LoggerInterface $logger,
    ) {
        $this->indexingAttributeRepository = $indexingAttributeRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->logger = $logger;
    }

    /**
     * @param int[] $attributeIds
     *
     * @return void
     * @throws IndexingAttributeSaveException
     */
    public function execute(array $attributeIds): void
    {
        $failed = [];
        $indexingAttributes = $this->getIndexingAttributes($attributeIds);
        foreach ($indexingAttributes as $indexingAttribute) {
            if (!$indexingAttribute->getIsIndexable() || $indexingAttribute->getNextAction() === Actions::ADD) {
                continue;
            }
            try {
                $indexingAttribute->setNextAction(nextAction: Actions::UPDATE);
                $this->indexingAttributeRepository->save($indexingAttribute);
            } catch (\Exception $exception) {
                $failed[] = $indexingAttribute->getId();
                $this->logger->error(
                    message: 'Method: {method} - Attribute ID: {attribute_id} - Error: {exception}',
                    context: [
                        'method' => __METHOD__,
                        'attribute_id' => $indexingAttribute->getId(),
                        'exception' => $exception->getMessage(),
                    ],
                );
            }
        }

        if ($failed) {
            throw new IndexingAttributeSaveException(
                phrase: __(
                    'Indexing attributes (%1) failed to save. See log for details.',
                    implode(', ', $failed),
                ),
            );
        }
    }

    /**
     * @param int[] $attributeIds
     *
     * @return IndexingAttributeInterface[]
     */
    private function getIndexingAttributes(array $attributeIds): array
    {
        $indexingAttributes = [];
        if ($attributeIds) {
            $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
            $searchCriteriaBuilder->addFilter(
                field: IndexingAttribute::ENTITY_ID,
                value: $attributeIds,
                conditionType: 'in',
            );
            $searchCriteria = $searchCriteriaBuilder->create();
            $searchResult = $this->indexingAttributeRepository->getList(searchCriteria: $searchCriteria);
            $indexingAttributes = $searchResult->getItems();
        }

        return $indexingAttributes;
    }
}
