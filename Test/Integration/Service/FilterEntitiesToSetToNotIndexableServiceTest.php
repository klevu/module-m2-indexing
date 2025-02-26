<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\FilterEntitiesToSetToNotIndexableService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterfaceFactory;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterEntitiesToSetToNotIndexableServiceInterface;
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

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers \Klevu\Indexing\Service\FilterEntitiesToSetToNotIndexableService::class
 * @method FilterEntitiesToSetToNotIndexableServiceInterface instantiateTestObject(?array $arguments = null)
 * @method FilterEntitiesToSetToNotIndexableServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FilterEntitiesToSetToNotIndexableServiceTest extends TestCase
{
    // phpcs:enable Generic.Files.LineLength.TooLong
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

        $this->implementationFqcn = FilterEntitiesToSetToNotIndexableService::class;
        $this->interfaceFqcn = FilterEntitiesToSetToNotIndexableServiceInterface::class;
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
    public function testExecute_ReturnsEntityIdsToSetToNotIndexable_whichHaveBeenDisabled(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingEntity1 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::LAST_ACTION => Actions::ADD,
        ]);
        $indexingEntity2 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
        ]);
        $indexingEntity3 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::LAST_ACTION => Actions::UPDATE,
        ]);
        $indexingEntity4 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::LAST_ACTION => Actions::DELETE,
        ]);
        $indexingEntity5 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable_variants',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::TARGET_PARENT_ID => 4,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::LAST_ACTION => Actions::ADD,
        ]);

        $magentoEntityInterfaceFactory = $this->objectManager->get(MagentoEntityInterfaceFactory::class);
        $magentoEntities = [];
        $magentoEntities[] = $magentoEntityInterfaceFactory->create([
            'entityId' => 1,
            'apiKey' => $apiKey,
            'isIndexable' => true,
            'entitySubtype' => 'simple',
        ]);
        $magentoEntities[] = $magentoEntityInterfaceFactory->create([
            'entityId' => 2,
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'entitySubtype' => 'simple',
        ]);
        $magentoEntities[] = $magentoEntityInterfaceFactory->create([
            'entityId' => 3,
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'entitySubtype' => 'simple',
        ]);
        $magentoEntities[] = $magentoEntityInterfaceFactory->create([
            'entityId' => 4,
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'entitySubtype' => 'configurable',
        ]);
        $magentoEntities[] = $magentoEntityInterfaceFactory->create([
            'entityId' => 3,
            'entityParentId' => 4,
            'apiKey' => $apiKey,
            'isIndexable' => false,
            'entitySubtype' => 'configurable_variants',
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
            klevuIndexingEntities:[
                $indexingEntity1,
                $indexingEntity2,
                $indexingEntity3,
                $indexingEntity4,
                $indexingEntity5,
            ],
            type: 'KLEVU_PRODUCT',
            apiKeys: [$apiKey],
            entitySubtypes: [
                'simple',
                'configurable',
            ],
        );
        $results = iterator_to_array($resultsGenerator);
        $result = array_pop($results);

        $this->assertCount(expectedCount: 3, haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity1->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingEntity2->getId(), haystack: $result);
        $this->assertContains(needle: (int)$indexingEntity3->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity4->getId(), haystack: $result);
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
        $indexingEntity->setTargetEntitySubtype($data[IndexingEntity::TARGET_ENTITY_SUBTYPE] ?? null);
        $indexingEntity->setApiKey($data[IndexingEntity::API_KEY] ?? 'klevu-js-api-key');
        $indexingEntity->setNextAction($data[IndexingEntity::NEXT_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastAction($data[IndexingEntity::LAST_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastActionTimestamp($data[IndexingEntity::LAST_ACTION_TIMESTAMP] ?? null);
        $indexingEntity->setLockTimestamp($data[IndexingEntity::LOCK_TIMESTAMP] ?? null);
        $indexingEntity->setIsIndexable($data[IndexingEntity::IS_INDEXABLE] ?? true);

        return $repository->save($indexingEntity);
    }
}
