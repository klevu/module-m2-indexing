<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Determiner;

use Klevu\Indexing\Service\Determiner\RequiresUpdateDeterminer;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterfaceFactory;
use Klevu\IndexingApi\Service\Determiner\RequiresUpdateCriteriaInterface;
use Klevu\IndexingApi\Service\Determiner\RequiresUpdateDeterminerInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RequiresUpdateDeterminerTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var IndexingEntityInterfaceFactory|null
     */
    private ?IndexingEntityInterfaceFactory $indexingEntityFactory = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = RequiresUpdateDeterminer::class;
        $this->interfaceFqcn = RequiresUpdateDeterminerInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->indexingEntityFactory = $this->objectManager->get(IndexingEntityInterfaceFactory::class);
    }

    public function testExecute_NoCriteriaServices(): void
    {
        $indexingEntity = $this->indexingEntityFactory->create();
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetId(1);
        $indexingEntity->setApiKey('klevu-1234567890');
        $indexingEntity->setIsIndexable(true);
        $indexingEntity->setRequiresUpdate(true);
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: 'foo',
            value: true,
        );

        $loggerMock = $this->getMockLogger(
            expectedLogLevels: [
                'debug',
            ],
        );
        $loggerMock->expects($this->once())
            ->method('debug')
            ->with(
                'No RequiresUpdateCriteriaInterface services found for criteria identifier: {criteriaIdentifier}',
                $this->callback(
                    callback: function (array $context): bool {
                        $this->assertArrayHasKey('method', $context);
                        $this->assertNotEmpty($context['method']);

                        $this->assertArrayHasKey('criteriaIdentifier', $context);
                        $this->assertSame('foo', $context['criteriaIdentifier']);

                        $this->assertArrayHasKey('indexingEntity', $context);
                        $this->assertIsArray($context['indexingEntity']);
                        $this->assertArrayHasKey('target_entity_type', $context['indexingEntity']);
                        $this->assertSame('KLEVU_PRODUCT', $context['indexingEntity']['target_entity_type']);
                        $this->assertArrayHasKey('target_id', $context['indexingEntity']);
                        $this->assertSame(1, $context['indexingEntity']['target_id']);
                        $this->assertArrayHasKey('api_key', $context['indexingEntity']);
                        $this->assertSame('klevu-1234567890', $context['indexingEntity']['api_key']);
                        $this->assertArrayHasKey('requires_update', $context['indexingEntity']);
                        $this->assertTrue($context['indexingEntity']['requires_update']);
                        $this->assertArrayHasKey('requires_update_orig_values', $context['indexingEntity']);
                        $this->assertSame(
                            expected: [
                                'foo' => true,
                            ],
                            actual: $context['indexingEntity']['requires_update_orig_values'],
                        );

                        return true;
                    },
                ),
            );

        $requiresUpdateDeterminer = $this->instantiateTestObject([
            'logger' => $loggerMock,
            'criteriaServices' => [],
        ]);

        $result = $requiresUpdateDeterminer->execute($indexingEntity);
        $this->assertFalse($result);
    }

    public function testExecute_CriteriaServicesMatchEntityType(): void
    {
        $indexingEntity = $this->indexingEntityFactory->create();
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetId(1);
        $indexingEntity->setApiKey('klevu-1234567890');
        $indexingEntity->setIsIndexable(true);
        $indexingEntity->setRequiresUpdate(true);
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: 'foo',
            value: true,
        );

        $requiresUpdateCriteriaProduct = $this->getMockRequiresUpdateCriteria(
            entityType: 'KLEVU_PRODUCT',
            criteriaIdentifier: 'foo',
            result: null,
        );
        $requiresUpdateCriteriaProduct->expects($this->once())
            ->method('execute')
            ->with($indexingEntity)
            ->willReturn(false);

        $requiresUpdateCriteriaCms = $this->getMockRequiresUpdateCriteria(
            entityType: 'KLEVU_CMS',
            criteriaIdentifier: 'foo',
            result: null,
        );
        $requiresUpdateCriteriaCms->expects($this->never())
            ->method('execute')
            ->with($indexingEntity)
            ->willReturn(true);

        $requiresUpdateDeterminer = $this->instantiateTestObject([
            'criteriaServices' => [
                'foo_product' => $requiresUpdateCriteriaProduct,
                'foo_cms' => $requiresUpdateCriteriaCms,
            ],
        ]);

        $result = $requiresUpdateDeterminer->execute($indexingEntity);
        $this->assertFalse($result);
    }

    public function testExecute_OverrideCriteriaServicesViaDi(): void
    {
        $indexingEntity = $this->indexingEntityFactory->create();
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetId(1);
        $indexingEntity->setApiKey('klevu-1234567890');
        $indexingEntity->setIsIndexable(true);
        $indexingEntity->setRequiresUpdate(true);
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: 'stock_status',
            value: true,
        );

        $requiresUpdateCriteriaMockStockStatus = $this->getMockRequiresUpdateCriteria(
            entityType: 'KLEVU_PRODUCT',
            criteriaIdentifier: 'stock_status',
            result: null,
        );
        $requiresUpdateCriteriaMockStockStatus->expects($this->once())
            ->method('execute')
            ->with($indexingEntity)
            ->willReturn(true);

        $requiresUpdateDeterminer = $this->instantiateTestObject([
            'criteriaServices' => [
                'stock_status' => $requiresUpdateCriteriaMockStockStatus,
            ],
        ]);

        $result = $requiresUpdateDeterminer->execute($indexingEntity);
        $this->assertTrue($result);
    }

    /**
     * @return array<bool[]>
     */
    public static function dataProvider_testExecute_MultipleCriteria(): array
    {
        return [
            [
                true,
                true,
                true,
            ],
            [
                true,
                false,
                true,
            ],
            [
                false,
                true,
                true,
            ],
            [
                false,
                false,
                false,
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testExecute_MultipleCriteria
     *
     * @param bool $fooResult
     * @param bool $barResult
     * @param bool $expectedResult
     *
     * @return void
     */
    public function testExecute_MultipleCriteria(
        bool $fooResult,
        bool $barResult,
        bool $expectedResult,
    ): void {
        $indexingEntity = $this->indexingEntityFactory->create();
        $indexingEntity->setTargetEntityType('KLEVU_PRODUCT');
        $indexingEntity->setTargetId(1);
        $indexingEntity->setApiKey('klevu-1234567890');
        $indexingEntity->setIsIndexable(true);
        $indexingEntity->setRequiresUpdate(true);
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: 'foo',
            value: true,
        );
        $indexingEntity->addRequiresUpdateOrigValue(
            criteria: 'bar',
            value: true,
        );

        $requiresUpdateCriteriaMockFoo = $this->getMockRequiresUpdateCriteria(
            entityType: 'KLEVU_PRODUCT',
            criteriaIdentifier: 'foo',
            result: $fooResult,
        );
        $requiresUpdateCriteriaMockBar = $this->getMockRequiresUpdateCriteria(
            entityType: 'KLEVU_PRODUCT',
            criteriaIdentifier: 'bar',
            result: $barResult,
        );

        $requiresUpdateDeterminer = $this->instantiateTestObject([
            'criteriaServices' => [
                'foo' => $requiresUpdateCriteriaMockFoo,
                'bar' => $requiresUpdateCriteriaMockBar,
            ],
        ]);

        $result = $requiresUpdateDeterminer->execute($indexingEntity);
        $this->assertSame(
            expected: $expectedResult,
            actual: $result,
        );
    }

    /**
     * @param string[] $expectedLogLevels
     *
     * @return MockObject&LoggerInterface
     */
    private function getMockLogger(array $expectedLogLevels = []): MockObject
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $notExpectedLogLevels = array_diff(
            [
                'emergency',
                'alert',
                'critical',
                'error',
                'warning',
                'notice',
                'info',
                'debug',
            ],
            $expectedLogLevels,
        );
        foreach ($notExpectedLogLevels as $notExpectedLogLevel) {
            $mockLogger->expects($this->never())
                ->method($notExpectedLogLevel);
        }

        return $mockLogger;
    }

    /**
     * @return MockObject&RequiresUpdateCriteriaInterface
     */
    private function getMockRequiresUpdateCriteria(
        string $entityType,
        string $criteriaIdentifier,
        ?bool $result,
    ): MockObject {
        $requiresUpdateCriteria = $this->getMockBuilder(RequiresUpdateCriteriaInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $requiresUpdateCriteria->method('getEntityType')
            ->willReturn($entityType);
        $requiresUpdateCriteria->method('getCriteriaIdentifier')
            ->willReturn($criteriaIdentifier);
        if (null !== $result) {
            $requiresUpdateCriteria->method('execute')
                ->willReturn($result);
        }

        return $requiresUpdateCriteria;
    }
}
