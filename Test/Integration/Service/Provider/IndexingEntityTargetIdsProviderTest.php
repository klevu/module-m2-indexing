<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Provider\IndexingEntityTargetIdsProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Service\Provider\IndexingEntityTargetIdsProviderInterface;
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
 * @covers \Klevu\Indexing\Service\Provider\IndexingEntityTargetIdsProvider::class
 * @method IndexingEntityTargetIdsProviderInterface instantiateTestObject(?array $arguments = null)
 * @method IndexingEntityTargetIdsProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingEntityTargetIdsProviderTest extends TestCase
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

        $this->implementationFqcn = IndexingEntityTargetIdsProvider::class;
        $this->interfaceFqcn = IndexingEntityTargetIdsProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @return array<array<int[]>>
     */
    public static function dataProvider_testGetByEntityIds(): array
    {
        return [
            [
                [],
                [],
            ],
            [
                [0, 1, 2],
                [
                    0 => 1,
                    1 => 1,
                    2 => 1,
                ],
            ],
            [
                [3, 4, 5],
                [
                    3 => 2,
                    4 => 3,
                    5 => 3,
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testGetByEntityIds
     *
     * @param int[] $entityIdKeys
     * @param int[] $expectedResult
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function testGetByEntityIds(
        array $entityIdKeys,
        array $expectedResult,
    ): void {
        $this->cleanIndexingEntities('klevu-1234567890');
        $this->cleanIndexingEntities('klevu-9876543210');

        $indexingEntities = $this->createIndexingEntityFixtures();

        $indexingEntityTargetIdsProvider = $this->instantiateTestObject();
        $result = $indexingEntityTargetIdsProvider->getByEntityIds(
            entityIds: array_map(
                callback: static fn (int $entityIdKey): int => $indexingEntities[$entityIdKey]->getId(),
                array: $entityIdKeys,
            ),
        );

        $this->assertEquals(
            expected: array_combine(
                keys: array_map(
                    callback: static fn (int $entityIdKey): int => $indexingEntities[$entityIdKey]->getId(),
                    array: array_keys($expectedResult),
                ),
                values: $expectedResult,
            ),
            actual: $result,
        );

        $this->cleanIndexingEntities('klevu-1234567890');
        $this->cleanIndexingEntities('klevu-9876543210');
    }

    /**
     * @return IndexingEntity[]
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createIndexingEntityFixtures(): array
    {
        return [
            $this->createIndexingEntity(
                data: [
                    IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                    IndexingEntity::TARGET_ID => 1,
                    IndexingEntity::TARGET_PARENT_ID => null,
                    IndexingEntity::IS_INDEXABLE => true,
                    IndexingEntity::API_KEY => 'klevu-1234567890',
                ],
            ),
            $this->createIndexingEntity(
                data: [
                    IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                    IndexingEntity::TARGET_ID => 1,
                    IndexingEntity::TARGET_PARENT_ID => 100,
                    IndexingEntity::IS_INDEXABLE => true,
                    IndexingEntity::API_KEY => 'klevu-1234567890',
                ],
            ),
            $this->createIndexingEntity(
                data: [
                    IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                    IndexingEntity::TARGET_ID => 1,
                    IndexingEntity::TARGET_PARENT_ID => null,
                    IndexingEntity::IS_INDEXABLE => true,
                    IndexingEntity::API_KEY => 'klevu-9876543210',
                ],
            ),
            $this->createIndexingEntity(
                data: [
                    IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
                    IndexingEntity::TARGET_ID => 2,
                    IndexingEntity::TARGET_PARENT_ID => null,
                    IndexingEntity::IS_INDEXABLE => false,
                    IndexingEntity::API_KEY => 'klevu-9876543210',
                ],
            ),
            $this->createIndexingEntity(
                data: [
                    IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
                    IndexingEntity::TARGET_ID => 3,
                    IndexingEntity::TARGET_PARENT_ID => null,
                    IndexingEntity::IS_INDEXABLE => true,
                    IndexingEntity::API_KEY => 'klevu-9876543210',
                ],
            ),
            $this->createIndexingEntity(
                data: [
                    IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
                    IndexingEntity::TARGET_ID => 3,
                    IndexingEntity::TARGET_PARENT_ID => null,
                    IndexingEntity::IS_INDEXABLE => true,
                    IndexingEntity::API_KEY => 'klevu-1234567890',
                ],
            ),
        ];
    }
}
