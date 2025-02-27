<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\FilterEntitiesToUpdateService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\FilterEntitiesToUpdateServiceInterface;
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
 * @covers \Klevu\Indexing\Service\FilterEntitiesToUpdateService::class
 * @method FilterEntitiesToUpdateServiceInterface instantiateTestObject(?array $arguments = null)
 * @method FilterEntitiesToUpdateServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class FilterEntitiesToUpdateServiceTest extends TestCase
{
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

        $this->implementationFqcn = FilterEntitiesToUpdateService::class;
        $this->interfaceFqcn = FilterEntitiesToUpdateServiceInterface::class;
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
     * @testWith [0]
     *           [-1]
     *           [99999999]
     */
    public function testInvalidBatchSize_ThrowsException(mixed $invalidBatchSize): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Invalid Batch Size: Invalid value provided. Value outside allowed range 1 < 9999999, received %s.',
                $invalidBatchSize,
            ),
        );

        $this->instantiateTestObject([
            'batchSize' => $invalidBatchSize,
        ]);
    }

    public function testExecute_ReturnsArrayOfIndexingEntityIds(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingEntity1 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity2 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity3 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => null,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity4 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable-variant',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 40,
            IndexingEntity::TARGET_PARENT_ID => 4,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity5 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGROIES',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => null,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity6 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => 'another-key',
            IndexingEntity::TARGET_ID => 6,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity7 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable-variant',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 50,
            IndexingEntity::TARGET_PARENT_ID => 7,
            IndexingEntity::IS_INDEXABLE => false,
        ]);

        $service = $this->instantiateTestObject();
        $resultGenerator = $service->execute(
            type: 'KLEVU_PRODUCT',
            entityIds: [1, 3, 4, 5, 6, 7, 999],
            apiKeys: [$apiKey],
        );
        $result = array_merge(
            ...iterator_to_array($resultGenerator),
        );

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertContains(needle: (int)$indexingEntity1->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity2->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity3->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity4->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity5->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity6->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity7->getId(), haystack: $result);
    }

    public function testExecute_ReturnsArrayOfIndexingEntityIds_FilteredBySubtype(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingEntity1 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity2 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::LAST_ACTION => Actions::DELETE,
            IndexingEntity::IS_INDEXABLE => false,
        ]);
        $indexingEntity3 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity4 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable-variant',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 40,
            IndexingEntity::TARGET_PARENT_ID => 4,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity5 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => 'another-key',
            IndexingEntity::TARGET_ID => 6,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity6 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'configurable-variant',
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 50,
            IndexingEntity::TARGET_PARENT_ID => 7,
            IndexingEntity::IS_INDEXABLE => false,
        ]);
        $indexingEntity7 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => null,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 20,
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity8 = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => null,
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ID => 10,
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $service = $this->instantiateTestObject();
        $resultGenerator = $service->execute(
            type: 'KLEVU_PRODUCT',
            entityIds: [],
            apiKeys: [$apiKey],
            entitySubtypes: [
                'simple',
            ],
        );
        $result = array_merge(
            ...iterator_to_array($resultGenerator),
        );

        $this->assertCount(expectedCount: 1, haystack: $result);
        $this->assertContains(needle: (int)$indexingEntity1->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity2->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity3->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity4->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity5->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity6->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity7->getId(), haystack: $result);
        $this->assertNotContains(needle: (int)$indexingEntity8->getId(), haystack: $result);
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
