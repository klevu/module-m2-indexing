<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\FilterEntitiesRequireUpdateService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Service\Determiner\RequiresUpdateDeterminerInterface;
use Klevu\IndexingApi\Service\FilterEntitiesRequireUpdateServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FilterEntitiesRequireUpdateServiceTest extends TestCase
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

        $this->implementationFqcn = FilterEntitiesRequireUpdateService::class;
        $this->interfaceFqcn = FilterEntitiesRequireUpdateServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->cleanIndexingEntities('klevu-1234567890');
        $this->cleanIndexingEntities('klevu-9876543210');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cleanIndexingEntities('klevu-1234567890');
        $this->cleanIndexingEntities('klevu-9876543210');
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

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testExecute(): array
    {
        return [
            [
                'KLEVU_PRODUCT',
                [], // null or empty array causes IndexingEntityProvider to not set filter. This is intended
                [], // null or empty array causes IndexingEntityProvider to not set filter. This is intended
                null, // null or empty array causes IndexingEntityProvider to not set filter. This is intended
                [2, 3, 4, 6, 8, 9],
            ],
            [
                'KLEVU_PRODUCT',
                [1, 2, 3, 4, 5, 6, 8, 9, 10, 20, 21, 22, 23, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110],
                [],
                null,
                [2, 3, 4, 8, 9],
            ],
            [
                'KLEVU_PRODUCT',
                [],
                ['klevu-1234567890'],
                null,
                [2, 3, 4, 6, 8],
            ],
            [
                'KLEVU_PRODUCT',
                [],
                [],
                ['simple', 'virtual', 'cms_page'],
                [4, 6, 8, 9],
            ],
            [
                'KLEVU_CMS',
                [],
                [],
                null,
                [10, 11],
            ],
            [
                'KLEVU_CATEGORY',
                [],
                [],
                null,
                [],
            ],
            [
                'KLEVU_CMS',
                [],
                [],
                ['simple', 'virtual', 'cms_page'],
                [11],
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testExecute
     *
     * @param string $type
     * @param int[] $entityIds
     * @param string[] $apiKeys
     * @param string[]|null $entitySubtypes
     * @param int[] $expectedIndexingEntityIdKeys
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function testExecute(
        string $type,
        array $entityIds,
        array $apiKeys,
        ?array $entitySubtypes,
        array $expectedIndexingEntityIdKeys,
    ): void {
        $indexingEntityIds = $this->createIndexingEntityFixtures();
        $requiresUpdateDeterminerMock = $this->getMockRequiresUpdateDeterminer();
        $requiresUpdateDeterminerMock->method('execute')
            ->willReturnCallback(
                callback: static fn (IndexingEntityInterface $indexingEntity): bool => in_array(
                    needle: $indexingEntity->getId(),
                    haystack: [
                        $indexingEntityIds[0],
                        $indexingEntityIds[1],
                        $indexingEntityIds[2],
                        $indexingEntityIds[3],
                        $indexingEntityIds[4],
                        $indexingEntityIds[5],
                        $indexingEntityIds[6],
                        $indexingEntityIds[7],
                        $indexingEntityIds[8],
                        $indexingEntityIds[9],
                        $indexingEntityIds[10],
                        $indexingEntityIds[11],
                    ],
                    strict: true,
                ),
            );

        /** @var FilterEntitiesRequireUpdateServiceInterface $filterEntitiesRequireUpdateService */
        $filterEntitiesRequireUpdateService = $this->instantiateTestObject([
            'requiresUpdateDeterminer' => $requiresUpdateDeterminerMock,
        ]);
        $resultGenerator = $filterEntitiesRequireUpdateService->execute(
            type: $type,
            entityIds: $entityIds,
            apiKeys: $apiKeys,
            entitySubtypes: $entitySubtypes,
        );
        $result = array_merge(
            ...iterator_to_array($resultGenerator),
        );

        $expectedResult = array_filter(
            array: array_map(
                callback: static fn (int $expectedIndexingEntityIdKey): int => (
                    $indexingEntityIds[$expectedIndexingEntityIdKey] ?? null
                ),
                array: $expectedIndexingEntityIdKeys,
            ),
        );

        $this->assertEquals(
            expected: $expectedResult,
            actual: $result,
        );
    }

    /**
     * @return int[]
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createIndexingEntityFixtures(): array
    {
        $entityIds = [];
        $entityIds[0] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $entityIds[1] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $entityIds[2] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[3] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[4] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 5,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[5] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 6,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $entityIds[6] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 7,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[7] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 8,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $entityIds[8] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 9,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[9] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::API_KEY => 'klevu-9876543210',
            IndexingEntity::TARGET_ID => 10,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[10] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[11] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'cms_page',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 20,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[12] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 101,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[13] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 102,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $entityIds[14] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 103,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[15] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 104,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $entityIds[16] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 105,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[17] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 106,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $entityIds[18] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 107,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[19] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 108,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $entityIds[20] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'virtual',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 109,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[21] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-9876543210',
            IndexingEntity::TARGET_ID => 110,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[22] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => '',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 101,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $entityIds[23] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'cms_page',
            IndexingEntity::API_KEY => 'klevu-1234567890',
            IndexingEntity::TARGET_ID => 120,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();

        return $entityIds;
    }

    /**
     * @return MockObject&RequiresUpdateDeterminerInterface
     */
    private function getMockRequiresUpdateDeterminer(): MockObject
    {
        return $this->getMockBuilder(RequiresUpdateDeterminerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
