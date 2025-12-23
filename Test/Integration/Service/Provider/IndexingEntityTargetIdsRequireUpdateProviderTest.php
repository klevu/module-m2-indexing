<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Provider;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Service\Provider\IndexingEntityTargetIdsRequireUpdateProvider;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Service\Provider\IndexingEntityTargetIdsRequireUpdateProviderInterface;
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
 * @covers \Klevu\Indexing\Service\Provider\IndexingEntityTargetIdsRequireUpdateProvider::class
 * @method IndexingEntityTargetIdsRequireUpdateProviderInterface instantiateTestObject(?array $arguments = null)
 * @method IndexingEntityTargetIdsRequireUpdateProviderInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class IndexingEntityTargetIdsRequireUpdateProviderTest extends TestCase
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

        $this->implementationFqcn = IndexingEntityTargetIdsRequireUpdateProvider::class;
        $this->interfaceFqcn = IndexingEntityTargetIdsRequireUpdateProviderInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function dataProvider_testGet(): array
    {
        return [
            'KLEVU_CATEGORY-withoutApiKey' => [
                'KLEVU_CATEGORY',
                null,
                [1, 2, 5, 6],
            ],
            'KLEVU_CATEGORY-withApiKey' => [
                'KLEVU_CATEGORY',
                ['klevu-1234567890'],
                [1, 2],
            ],
            'KLEVU_CATEGORY-withApiKeys' => [
                'KLEVU_CATEGORY',
                ['klevu-1234567890', 'klevu-9876543210'],
                [1, 2, 5, 6],
            ],
            'KLEVU_CATEGORY-withNonExistentApiKey' => [
                'KLEVU_CATEGORY',
                ['klevu-1111111111'],
                [],
            ],
            'KLEVU_CMS-withoutApiKey' => [
                'KLEVU_CMS',
                null,
                [9, 10, 13, 14],
            ],
            'KLEVU_CMS-withApiKey' => [
                'KLEVU_CMS',
                ['klevu-1234567890'],
                [9, 10],
            ],
            'KLEVU_CMS-withApiKeys' => [
                'KLEVU_CMS',
                ['klevu-1234567890', 'klevu-9876543210'],
                [9, 10, 13, 14],
            ],
            'KLEVU_CMS-withNonExistentApiKey' => [
                'KLEVU_CMS',
                ['klevu-1111111111'],
                [],
            ],
            'KLEVU_PRODUCT-withoutApiKey' => [
                'KLEVU_PRODUCT',
                null,
                [17, 18, 21, 22],
            ],
            'KLEVU_PRODUCT-withApiKey' => [
                'KLEVU_PRODUCT',
                ['klevu-1234567890'],
                [17, 18],
            ],
            'KLEVU_PRODUCT-withApiKeys' => [
                'KLEVU_PRODUCT',
                ['klevu-1234567890', 'klevu-9876543210'],
                [17, 18, 21, 22],
            ],
            'KLEVU_PRODUCT-withNonExistentApiKey' => [
                'KLEVU_PRODUCT',
                ['klevu-1111111111'],
                [],
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testGet
     *
     * @param string $entityType
     * @param string[]|null $apiKeys
     * @param int[] $expectedTargetIds
     *
     * @return void
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    public function testGet(
        string $entityType,
        ?array $apiKeys,
        array $expectedTargetIds,
    ): void {
        $allApiKeys = [
            'klevu-1234567890',
            'klevu-9876543210',
        ];

        array_walk($allApiKeys, [$this, 'cleanIndexingEntities']);
        $this->createFixtures($allApiKeys);

        /** @var IndexingEntityTargetIdsRequireUpdateProvider $provider */
        $provider = $this->instantiateTestObject();
        $results = $provider->get(
            entityType: $entityType,
            apiKeys: $apiKeys,
        );

        $this->assertSame(
            expected: $expectedTargetIds,
            actual: $results,
        );

        array_walk($allApiKeys, [$this, 'cleanIndexingEntities']);
    }

    /**
     * @param string[] $apiKeys
     *
     * @return int[]
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     */
    private function createFixtures(array $apiKeys): array
    {
        $return = [];

        // 1,2 : API Key 1; KLEVU_CATEGORY, Requires Update true
        $return[1] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $return[2] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        // 3,4 : API Key 1; KLEVU_CATEGORY, Requires Update false
        $return[3] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $return[4] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        // 5,6 : API Key 2; KLEVU_CATEGORY, Requires Update true
        $return[5] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 5,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $return[6] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 6,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        // 7,8 : API Key 2; KLEVU_CATEGORY, Requires Update false
        $return[7] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 7,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $return[8] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 8,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        // 9,10 : API Key 1; KLEVU_CMS, Requires Update true
        $return[9] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 9,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $return[10] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 10,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        // 11,12 : API Key 1; KLEVU_CMS, Requires Update false
        $return[11] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 11,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $return[12] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 12,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        // 13,14 : API Key 2; KLEVU_CMS, Requires Update true
        $return[13] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 13,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $return[14] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 14,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        // 15,16 : API Key 2; KLEVU_CMS, Requires Update false
        $return[15] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 15,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $return[16] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 16,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        // 17,18 : API Key 1; KLEVU_PRODUCT, Requires Update true
        $return[17] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 17,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $return[18] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 18,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        // 19,20 : API Key 1; KLEVU_PRODUCT, Requires Update false
        $return[19] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 19,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $return[20] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 20,
            IndexingEntity::API_KEY => $apiKeys[0] ?? 'klevu-1234567890',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        // 21,22 : API Key 2; KLEVU_PRODUCT, Requires Update true
        $return[21] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 21,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        $return[22] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 22,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::REQUIRES_UPDATE => true,
        ])->getId();
        // 23,24 : API Key 2; KLEVU_PRODUCT, Requires Update false
        $return[23] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 23,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();
        $return[24] = $this->createIndexingEntity([
            IndexingEntity::TARGET_ID => 24,
            IndexingEntity::API_KEY => $apiKeys[1] ?? 'klevu-9876543210',
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::REQUIRES_UPDATE => false,
        ])->getId();

        return $return;
    }
}
