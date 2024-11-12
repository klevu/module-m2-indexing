<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\FilterEntitiesToAddService;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterfaceFactory;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterEntitiesToAddServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\FilterEntitiesToAddService::class
 * @method FilterEntitiesToAddServiceInterface instantiateTestObject(?array $arguments = null)
 * @method FilterEntitiesToAddServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FilterEntitiesToAddServiceTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = FilterEntitiesToAddService::class;
        $this->interfaceFqcn = FilterEntitiesToAddServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_RemovesMagentoEntitiesAlreadyInKlevuEntities(): void
    {
        $apiKey = 'klevu-api-key';
        $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCTS',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 2,
        ]);

        $magentoEntityInterfaceFactory = $this->objectManager->get(MagentoEntityInterfaceFactory::class);
        $magentoEntities = [];
        $magentoEntities[] = $magentoEntityInterfaceFactory->create([
            'entityId' => 1,
            'apiKey' => $apiKey,
            'isIndexable' => true,
        ]);
        $magentoEntities[] = $magentoEntityInterfaceFactory->create([
            'entityId' => 2,
            'apiKey' => $apiKey,
            'isIndexable' => true,
        ]);
        $magentoEntities[] = $magentoEntityInterfaceFactory->create([
            'entityId' => 3,
            'apiKey' => $apiKey,
            'isIndexable' => false,
        ]);
        $magentoEntities[] = $magentoEntityInterfaceFactory->create([
            'entityId' => 4,
            'entityParentId' => 99,
            'apiKey' => $apiKey,
            'isIndexable' => true,
        ]);

        $service = $this->instantiateTestObject();
        $generator = $service->execute(magentoEntities: $magentoEntities, type: 'KLEVU_PRODUCTS', apiKey: $apiKey);
        $result = iterator_to_array($generator);

        $this->assertCount(expectedCount: 3, haystack: $result);
        $magentoEntityIds = array_map(
            callback: static function (MagentoEntityInterface $magentoEntity): string {
                return $magentoEntity->getEntityId() . '-' . ($magentoEntity->getEntityParentId() ?: '0');
            },
            array: $result,
        );
        $this->assertContains(needle: '1-0', haystack: $magentoEntityIds);
        $this->assertNotContains(needle: '2-0', haystack: $magentoEntityIds);
        $this->assertContains(needle: '3-0', haystack: $magentoEntityIds);
        $this->assertContains(needle: '4-99', haystack: $magentoEntityIds);
    }

    /**
     * @param mixed[] $data
     *
     * @return IndexingEntityInterface
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createIndexingEntity(array $data): IndexingEntityInterface
    {
        $repository = $this->objectManager->get(IndexingEntityRepositoryInterface::class);
        $indexingEntity = $repository->create();
        $indexingEntity->setTargetId((int)$data[IndexingEntity::TARGET_ID]);
        $indexingEntity->setTargetParentId($data[IndexingEntity::TARGET_PARENT_ID] ?? null);
        $indexingEntity->setTargetEntityType($data[IndexingEntity::TARGET_ENTITY_TYPE] ?? 'KLEVU_PRODUCT');
        $indexingEntity->setApiKey($data[IndexingEntity::API_KEY] ?? 'klevu-js-api-key');
        $indexingEntity->setNextAction($data[IndexingEntity::NEXT_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastAction($data[IndexingEntity::LAST_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastActionTimestamp($data[IndexingEntity::LAST_ACTION_TIMESTAMP] ?? null);
        $indexingEntity->setLockTimestamp($data[IndexingEntity::LOCK_TIMESTAMP] ?? null);
        $indexingEntity->setIsIndexable($data[IndexingEntity::IS_INDEXABLE] ?? true);

        return $repository->save($indexingEntity);
    }
}
