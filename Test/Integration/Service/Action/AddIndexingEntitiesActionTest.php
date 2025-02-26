<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Exception\IndexingEntitySaveException;
use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Action\AddIndexingEntitiesAction;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Service\Action\AddIndexingEntitiesActionInterface;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\GeneratorTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Service\Action\AddIndexingEntitiesAction::class
 * @method AddIndexingEntitiesActionInterface instantiateTestObject(?array $arguments = null)
 * @method AddIndexingEntitiesActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AddIndexingEntitiesActionTest extends TestCase
{
    use GeneratorTrait;
    use ObjectInstantiationTrait;
    use StoreTrait;
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

        $this->implementationFqcn = AddIndexingEntitiesAction::class;
        $this->interfaceFqcn = AddIndexingEntitiesActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @dataProvider dataProvider_testExecute_SavesNewProductIndexingEntity
     */
    public function testExecute_SavesNewIndexingEntity(string $type): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);
        $magentoEntities = [];
        $magentoEntities[] = $this->objectManager->create(MagentoEntityInterface::class, [
            'entityId' => 1,
            'apiKey' => $apiKey,
            'isIndexable' => true,
        ]);
        $magentoEntities[] = $this->objectManager->create(MagentoEntityInterface::class, [
            'entityId' => 2,
            'entityParentId' => 3,
            'apiKey' => $apiKey,
            'isIndexable' => false,
        ]);
        $action = $this->instantiateTestObject();
        $action->execute(type: $type, magentoEntities: $this->generate($magentoEntities));

        $indexingEntities = $this->getIndexingEntities($apiKey, $type);
        $this->assertCount(expectedCount: 2, haystack: $indexingEntities);
        $targetIds = $this->getTargetIds($indexingEntities);
        $this->assertContains(1, $targetIds);
        $this->assertContains(2, $targetIds);

        $indexingEntityArray1 = $this->filterIndexEntities($indexingEntities, 1);
        $indexingEntity1 = array_shift($indexingEntityArray1);
        $this->assertTrue($indexingEntity1->getIsIndexable());

        $indexingEntityArray2 = $this->filterIndexEntities($indexingEntities, 2);
        $indexingEntity2 = array_shift($indexingEntityArray2);
        $this->assertFalse($indexingEntity2->getIsIndexable());
        $this->assertSame(3, $indexingEntity2->getTargetParentId());
    }

    /**
     * @return string[][]
     */
    public function dataProvider_testExecute_SavesNewProductIndexingEntity(): array
    {
        return [
            ['KLEVU_CATEGORY'],
            ['KLEVU_CMS'],
            ['KLEVU_PRODUCT'],
        ];
    }

    public function testExecute_LogsError_WhenSaveExceptionIsThrown(): void
    {
        $apiKey = 'klevu-api-key-' . random_int(1, 999999);
        /** @var MagentoEntityInterface $magentoEntity */
        $magentoEntity = $this->objectManager->create(MagentoEntityInterface::class, [
            'entityId' => 1,
            'apiKey' => $apiKey,
            'isIndexable' => true,
        ]);
        $magentoEntities = [$magentoEntity];

        $mockIndexingEntityRepository = $this->getMockBuilder(IndexingEntityRepositoryInterface::class)
            ->getMock();
        $mockIndexingEntityRepository->expects($this->once())
            ->method('save')
            ->willThrowException(new \Exception('Exception thrown by repo'));
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error');

        $this->expectException(IndexingEntitySaveException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Failed to save Indexing Entities for Magento Entity IDs (%s). See log for details.',
                $magentoEntity->getEntityId(),
            ),
        );

        $action = $this->instantiateTestObject([
            'indexingEntityRepository' => $mockIndexingEntityRepository,
            'logger' => $mockLogger,
        ]);
        $action->execute(type: 'KLEVU_PRODUCT', magentoEntities: $this->generate($magentoEntities));
    }

    /**
     * @param string $apiKey
     * @param string $type
     *
     * @return IndexingEntityInterface[]
     */
    private function getIndexingEntities(string $apiKey, string $type): array
    {
        $searchCriteriaBuilderFactory = $this->objectManager->get(SearchCriteriaBuilderFactory::class);
        $searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
        $searchCriteriaBuilder->addFilter(
            field: IndexingEntity::TARGET_ENTITY_TYPE,
            value: $type,
        );
        $searchCriteriaBuilder->addFilter(
            field: IndexingEntity::API_KEY,
            value: $apiKey,
        );
        $searchCriteria = $searchCriteriaBuilder->create();
        $repository = $this->objectManager->get(IndexingEntityRepositoryInterface::class);
        $searchResult = $repository->getList($searchCriteria);

        return $searchResult->getItems();
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     *
     * @return int[]
     */
    private function getTargetIds(array $indexingEntities): array
    {
        return array_map(static fn (IndexingEntityInterface $indexingEntity): int => (
            $indexingEntity->getTargetId()
        ), $indexingEntities);
    }

    /**
     * @param IndexingEntityInterface[] $indexingEntities
     * @param int $entityId
     *
     * @return IndexingEntityInterface[]
     */
    private function filterIndexEntities(array $indexingEntities, int $entityId): array
    {
        return array_filter(
            array: $indexingEntities,
            callback: static function (IndexingEntityInterface $indexingEntity) use ($entityId) {
                return $entityId === (int)$indexingEntity->getTargetId();
            },
        );
    }
}
