<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Traits;

use Klevu\Indexing\Model\IndexingAttribute;
use Klevu\Indexing\Model\ResourceModel\IndexingAttribute\Collection as IndexingAttributeCollection;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

trait IndexingAttributesTrait
{
    /**
     * @param mixed[] $data
     *
     * @return IndexingAttributeInterface
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createIndexingAttribute(array $data = []): IndexingAttributeInterface
    {
        $repository = $this->objectManager->get(IndexingAttributeRepositoryInterface::class);
        $indexingAttribute = $repository->create();
        $indexingAttribute->setTargetId(
            isset($data[IndexingAttribute::TARGET_ID])
                ? (int)$data[IndexingAttribute::TARGET_ID]
                : 1,
        );
        $indexingAttribute->setTargetCode(
            $data[IndexingAttribute::TARGET_CODE] ?? 'klevu_test_attribute',
        );
        $indexingAttribute->setTargetAttributeType(
            $data[IndexingAttribute::TARGET_ATTRIBUTE_TYPE] ?? 'KLEVU_PRODUCT',
        );
        $indexingAttribute->setApiKey($data[IndexingAttribute::API_KEY] ?? 'klevu-js-api-key');
        $indexingAttribute->setNextAction($data[IndexingAttribute::NEXT_ACTION] ?? Actions::NO_ACTION);
        $indexingAttribute->setLastAction($data[IndexingAttribute::LAST_ACTION] ?? Actions::NO_ACTION);
        $indexingAttribute->setLastActionTimestamp($data[IndexingAttribute::LAST_ACTION_TIMESTAMP] ?? null);
        $indexingAttribute->setLockTimestamp($data[IndexingAttribute::LOCK_TIMESTAMP] ?? null);
        $indexingAttribute->setIsIndexable($data[IndexingAttribute::IS_INDEXABLE] ?? true);

        return $repository->save($indexingAttribute);
    }

    /**
     * @param string $apiKey
     *
     * @return void
     */
    private function cleanIndexingAttributes(string $apiKey): void
    {
        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: IndexingAttribute::API_KEY,
            value: $apiKey,
            conditionType: 'like',
        );
        $searchCriteria = $searchCriteriaBuilder->create();
        /** @var IndexingAttributeRepositoryInterface $repository */
        $repository = $this->objectManager->get(IndexingAttributeRepositoryInterface::class);
        $indexingAttributesToDelete = $repository->getList($searchCriteria);
        foreach ($indexingAttributesToDelete->getItems() as $indexingAttribute) {
            try {
                $repository->delete($indexingAttribute);
            } catch (LocalizedException) {
                // this is fine, indexingAttribute already deleted
            }
        }
    }

    /**
     * @param string $apiKey
     * @param AttributeInterface $attribute
     * @param string|null $type
     *
     * @return IndexingAttributeInterface|null
     */
    private function getIndexingAttributeForAttribute(
        string $apiKey,
        AttributeInterface $attribute,
        ?string $type = 'KLEVU_PRODUCT',
    ): ?IndexingAttributeInterface {
        $productIndexingAttributes = $this->getIndexingAttributes($type, $apiKey);
        $productIndexingEntityArray = array_filter(
            array: $productIndexingAttributes,
            callback: static fn (IndexingAttributeInterface $indexingEntity) => (
                (int)$indexingEntity->getTargetId() === (int)$attribute->getAttributeId()
            )
        );

        return array_shift($productIndexingEntityArray);
    }

    /**
     * @param string|null $type
     * @param string|null $apiKey
     *
     * @return IndexingAttributeInterface[]
     */
    private function getIndexingAttributes(?string $type = 'KLEVU_PRODUCT', ?string $apiKey = null): array
    {
        $collection = $this->objectManager->create(IndexingAttributeCollection::class);
        $collection->addFieldToFilter(
            field: IndexingAttribute::TARGET_ATTRIBUTE_TYPE,
            condition: ['eq' => $type],
        );
        if ($apiKey) {
            $collection->addFieldToFilter(
                field: IndexingAttribute::API_KEY,
                condition: ['eq' => $apiKey],
            );
        }

        return $collection->getItems();
    }
}
