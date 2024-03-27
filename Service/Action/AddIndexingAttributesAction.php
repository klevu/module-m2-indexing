<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\Indexing\Exception\IndexingAttributeSaveException;
use Klevu\IndexingApi\Api\Data\IndexingAttributeInterface;
use Klevu\IndexingApi\Api\IndexingAttributeRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\AddIndexingAttributesActionInterface;
use Psr\Log\LoggerInterface;

class AddIndexingAttributesAction implements AddIndexingAttributesActionInterface
{
    /**
     * @var IndexingAttributeRepositoryInterface
     */
    private readonly IndexingAttributeRepositoryInterface $indexingAttributeRepository;
    /**
     * @var LoggerInterface
     */
    private readonly LoggerInterface $logger;

    /**
     * @param IndexingAttributeRepositoryInterface $indexingAttributeRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        IndexingAttributeRepositoryInterface $indexingAttributeRepository,
        LoggerInterface $logger,
    ) {
        $this->indexingAttributeRepository = $indexingAttributeRepository;
        $this->logger = $logger;
    }

    /**
     * @param string $type
     * @param MagentoAttributeInterface[] $magentoAttributes
     *
     * @return void
     * @throws IndexingAttributeSaveException
     */
    public function execute(string $type, array $magentoAttributes): void
    {
        $failed = [];

        foreach ($magentoAttributes as $magentoAttribute) {
            try {
                $indexingAttribute = $this->createIndexingAttribute(type: $type, magentoAttribute: $magentoAttribute);
                $this->indexingAttributeRepository->save(indexingAttribute: $indexingAttribute);
            } catch (\Exception $exception) {
                $failed[] = $magentoAttribute->getAttributeId();
                $this->logger->error(
                    message: 'Method: {method} - Attribute ID: {attribute_id} - Error: {exception}',
                    context: [
                        'method' => __METHOD__,
                        'attribute_id' => $magentoAttribute->getAttributeId(),
                        'exception' => $exception->getMessage(),
                    ],
                );
            }
        }
        if ($failed) {
            throw new IndexingAttributeSaveException(
                phrase: __(
                    'Failed to save Indexing Attributes for Magento Attribute IDs (%1). See log for details.',
                    implode(', ', $failed),
                ),
            );
        }
    }

    /**
     * @param string $type
     * @param MagentoAttributeInterface $magentoAttribute
     *
     * @return IndexingAttributeInterface
     */
    private function createIndexingAttribute(
        string $type,
        MagentoAttributeInterface $magentoAttribute,
    ): IndexingAttributeInterface {
        $isIndexable = $magentoAttribute->isIndexable();
        $indexingAttribute = $this->indexingAttributeRepository->create();
        $indexingAttribute->setTargetAttributeType(attributeType: $type);
        $indexingAttribute->setTargetId(targetId: $magentoAttribute->getAttributeId());
        $indexingAttribute->setTargetCode(targetCode: $magentoAttribute->getAttributeCode());
        $indexingAttribute->setApiKey(apiKey: $magentoAttribute->getApiKey());
        $indexingAttribute->setIsIndexable(isIndexable: $isIndexable);
        $indexingAttribute->setNextAction(
            nextAction: $isIndexable
                ? Actions::ADD
                : Actions::NO_ACTION,
        );
        $indexingAttribute->setLastAction(lastAction: Actions::NO_ACTION);

        return $indexingAttribute;
    }
}
