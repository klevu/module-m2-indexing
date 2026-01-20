<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Service\KlevuToMagentoEntityTypeMap;
use Klevu\IndexingApi\Api\KlevuToMagentoEntityTypeMapInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Service\KlevuToMagentoEntityTypeMap::class
 * @method KlevuToMagentoEntityTypeMapInterface instantiateTestObject(?array $arguments = null)
 * @method KlevuToMagentoEntityTypeMapInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class KlevuToMagentoEntityTypeMapTest extends TestCase
{
    use ObjectInstantiationTrait;

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

        $this->implementationFqcn = KlevuToMagentoEntityTypeMap::class;
        $this->interfaceFqcn = KlevuToMagentoEntityTypeMapInterface::class;

        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testConstructThrowsInvalidArgumentException(): array
    {
        return [
            [
                [
                    [
                        'klevu_entity_type' => 'foo',
                    ],
                ],
            ],
            [
                [
                    [
                        'magento_entity_type_id' => 1,
                    ],
                ],
            ],
            [
                [
                    [
                        'klevu_entity_type' => 1,
                        'magento_entity_type_id' => 1,
                    ],
                ],
            ],
            [
                [
                    [
                        'klevu_entity_type' => '',
                        'magento_entity_type_id' => 1,
                    ],
                ],
            ],
            [
                [
                    [
                        'klevu_entity_type' => 'TEST',
                        'magento_entity_type_id' => '1',
                    ],
                ],
            ],
            [
                [
                    [
                        'klevu_entity_type' => 'TEST',
                        'magento_entity_type_id' => 1.0,
                    ],
                ],
            ],
            [
                [
                    [
                        'klevu_entity_type' => 'TEST',
                        'magento_entity_type_id' => 0,
                    ],
                ],
            ],
            [
                [
                    [
                        'klevu_entity_type' => 'TEST',
                        'magento_entity_type_id' => -1,
                    ],
                ],
            ],
            [
                [
                    [
                        'klevu_entity_type' => 'TEST1',
                        'magento_entity_type_id' => 1,
                    ],
                    [
                        'klevu_entity_type' => 'TEST2',
                        'magento_entity_type_id' => -1,
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testConstructThrowsInvalidArgumentException
     *
     * @param mixed[] $klevuToMagentoEntityTypeMapItems
     *
     * @return void
     */
    public function testConstructThrowsInvalidArgumentException(
        array $klevuToMagentoEntityTypeMapItems,
    ): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->instantiateTestObject([
            'klevuToMagentoEntityTypeMapItems' => $klevuToMagentoEntityTypeMapItems,
        ]);
    }

    /**
     * @return void
     */
    public function testGetKlevuEntityTypeForMagentoEntityTypeId(): void
    {
        $klevuToMagentoEntityTypeMap = $this->instantiateTestObject([
            'klevuToMagentoEntityTypeMapItems' => [
                'phpunitTest1' => [
                    'klevu_entity_type' => 'KLEVU_TEST',
                    'magento_entity_type_id' => 999,
                ],
                'phpunitTest2' => [
                    'klevu_entity_type' => 'KLEVU_TEST',
                    'magento_entity_type_id' => 1000,
                ],
                'phpunitTest3' => [
                    'klevu_entity_type' => 'KLEVU_TEST2',
                    'magento_entity_type_id' => 1001,
                ],
            ],
        ]);

        $this->assertSame(
            expected: 'KLEVU_TEST',
            actual: $klevuToMagentoEntityTypeMap->getKlevuEntityTypeForMagentoEntityTypeId(
                magentoEntityTypeId: 999,
            ),
        );
        $this->assertNull(
            actual: $klevuToMagentoEntityTypeMap->getKlevuEntityTypeForMagentoEntityTypeId(
                magentoEntityTypeId: 998,
            ),
        );
    }

    /**
     * @return void
     */
    public function getMagentoEntityTypeIdsForKlevuEntityType(): void
    {
        $klevuToMagentoEntityTypeMap = $this->instantiateTestObject([
            'klevuToMagentoEntityTypeMapItems' => [
                'phpunitTest1' => [
                    'klevu_entity_type' => 'KLEVU_TEST',
                    'magento_entity_type_id' => 999,
                ],
                'phpunitTest2' => [
                    'klevu_entity_type' => 'KLEVU_TEST',
                    'magento_entity_type_id' => 1000,
                ],
                'phpunitTest3' => [
                    'klevu_entity_type' => 'KLEVU_TEST2',
                    'magento_entity_type_id' => 1001,
                ],
            ],
        ]);

        $this->assertSame(
            expected: [
                999,
                1000,
            ],
            actual: $klevuToMagentoEntityTypeMap->getMagentoEntityTypeIdsForKlevuEntityType(
                klevuEntityType: 'KLEVU_TEST',
            ),
        );
        $this->assertSame(
            expected: [],
            actual: $klevuToMagentoEntityTypeMap->getMagentoEntityTypeIdsForKlevuEntityType(
                klevuEntityType: 'FOO',
            ),
        );
    }
}