<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\FilterEntitiesToSetToIndexableService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterfaceFactory;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterEntitiesToSetToIndexableServiceInterface;
use Klevu\IndexingApi\Service\Provider\EntityDiscoveryProviderInterface;
use Klevu\TestFixtures\Traits\GeneratorTrait;
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
 * @covers \Klevu\Indexing\Service\FilterEntitiesToSetToIndexableService::class
 * @method FilterEntitiesToSetToIndexableServiceInterface instantiateTestObject(?array $arguments = null)
 * @method FilterEntitiesToSetToIndexableServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FilterEntitiesToSetToIndexableServiceTest extends TestCase
{
    use GeneratorTrait;
    use IndexingEntitiesTrait;
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

        $this->implementationFqcn = FilterEntitiesToSetToIndexableService::class;
        $this->interfaceFqcn = FilterEntitiesToSetToIndexableServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->cleanIndexingEntities('klevu-api-key%');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanIndexingEntities('klevu-api-key%');
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_RemovesMagentoEntitiesAlreadyIndexable(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingEntity1 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
        ]);
        $indexingEntity2 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
        ]);
        $indexingEntity3 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
        ]);
        $indexingEntity4 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::TARGET_PARENT_ID => 99,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
        ]);
        $indexingEntity5 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 5,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
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
        $magentoEntities[] = $magentoEntityInterfaceFactory->create([
            'entityId' => 5,
            'apiKey' => $apiKey,
            'isIndexable' => true,
        ]);

        $mockDiscoveryProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockDiscoveryProvider->expects($this->once())
            ->method('getData')
            ->willReturn($this->generate([
                $apiKey => [$magentoEntities],
            ]));
        $mockDiscoveryProvider->expects($this->once())
            ->method('getEntityType')
            ->willReturn('KLEVU_PRODUCT');

        $service = $this->instantiateTestObject([
            'discoveryProviders' => [
                'KLEVU_PRODUCT' => $mockDiscoveryProvider,
            ],
        ]);
        $resultsGenerator = $service->execute(
            klevuIndexingEntities: [
                $indexingEntity1,
                $indexingEntity2,
                $indexingEntity3,
                $indexingEntity4,
                $indexingEntity5,
            ],
            type: 'KLEVU_PRODUCT',
            apiKeys: [$apiKey],
        );
        $results = iterator_to_array($resultsGenerator);
        $result = array_pop($results);

        $this->assertCount(expectedCount: 4, haystack: $result);
        $this->assertContains(needle: (int)$indexingEntity1->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingEntity2->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity3->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingEntity4->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingEntity5->getId(), haystack: $result);
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
