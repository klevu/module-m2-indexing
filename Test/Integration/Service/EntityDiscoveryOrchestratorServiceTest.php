<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Model\IndexingEntity;
use Klevu\Indexing\Model\ResourceModel\IndexingEntity\Collection as IndexingEntityCollection;
use Klevu\Indexing\Service\EntityDiscoveryOrchestratorService;
use Klevu\Indexing\Test\Integration\Traits\IndexingEntitiesTrait;
use Klevu\IndexingApi\Api\Data\DiscoveryResultInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntityInterface;
use Klevu\IndexingApi\Api\Data\IndexingEntitySearchResultsInterface;
use Klevu\IndexingApi\Api\IndexingEntityRepositoryInterface;
use Klevu\IndexingApi\Model\MagentoEntityInterface;
use Klevu\IndexingApi\Model\Source\Actions;
use Klevu\IndexingApi\Service\Action\AddIndexingEntitiesActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToBeIndexableActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToDeleteActionInterface;
use Klevu\IndexingApi\Service\Action\SetIndexingEntitiesToUpdateActionInterface;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToAddServiceInterface;
use Klevu\IndexingApi\Service\FilterEntitiesToDeleteServiceInterface;
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
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Service\EntityDiscoveryOrchestratorService::class
 * @method EntityDiscoveryOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityDiscoveryOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityDiscoveryOrchestratorServiceTest extends TestCase
{
    use GeneratorTrait;
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;
    use IndexingEntitiesTrait;

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

        $this->implementationFqcn = EntityDiscoveryOrchestratorService::class;
        $this->interfaceFqcn = EntityDiscoveryOrchestratorServiceInterface::class;
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
    public function testExecute_NoProviders_ReturnsSuccessFalse(): void
    {
        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('warning')
            ->with(
                'Method: {method} - Warning: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\EntityDiscoveryOrchestratorService::validateDiscoveryProviders',
                    'message' => 'No providers available for entity discovery.',
                ],
            );

        $service = $this->instantiateTestObject([
            'logger' => $mockLogger,
            'discoveryProviders' => [],
        ]);
        $resultGenerators = $service->execute();
        $resultsArray = [];
        foreach ($resultGenerators as $resultGenerator) {
            $resultsArray[] = iterator_to_array($resultGenerator);
        }
        $results = array_filter(
            array_merge(...$resultsArray),
        );
        $result = array_shift($results);

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 1, haystack: $messages, message: 'Message Count');
        $this->assertContains(
            needle: 'No providers available for entity discovery.',
            haystack: $messages,
            message: 'Expected Message Exists',
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_WithTypeArgument_SkipsOtherEntityTypes(): void
    {
        $collection = $this->objectManager->create(IndexingEntityCollection::class);
        $count = count($collection->getItems());

        $mockProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->expects($this->once())
            ->method('getEntityType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProvider->expects($this->never())
            ->method('getData');

        $service = $this->instantiateTestObject([
            'discoveryProviders' => [
                'products' => $mockProvider,
            ],
        ]);
        $resultGenerators = $service->execute(entityTypes: ['KLEVU_CMS']);
        $resultsArray = [];
        foreach ($resultGenerators as $resultGenerator) {
            $resultsArray[] = iterator_to_array($resultGenerator);
        }
        $results = array_filter(
            array_merge(...$resultsArray),
        );
        $result = array_shift($results);

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $this->assertContains(
            needle: 'Supplied entity types did not match any providers.',
            haystack: $result->getMessages(),
        );
        $collection = $this->objectManager->create(IndexingEntityCollection::class);
        $this->assertCount(
            expectedCount: $count,
            haystack: $collection->getItems(),
            message: 'Final Items Count',
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_WithTypeArgument(): void
    {
        $collection = $this->objectManager->create(IndexingEntityCollection::class);
        $count = count($collection->getItems());

        $mockProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->method('getEntityType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProvider->method('getData')
            ->willReturn($this->generate([]));

        $service = $this->instantiateTestObject([
            'discoveryProviders' => [
                'products' => $mockProvider,
            ],
        ]);
        $resultGenerators = $service->execute(entityTypes: ['KLEVU_PRODUCT']);
        $resultsArray = [];
        foreach ($resultGenerators as $resultGenerator) {
            $resultsArray[] = iterator_to_array($resultGenerator);
        }
        $results = array_filter(
            array_merge(...$resultsArray),
        );
        $result = array_shift($results);

        $this->assertNull(actual: $result);
        $collection = $this->objectManager->create(IndexingEntityCollection::class);
        $this->assertCount(
            expectedCount: $count,
            haystack: $collection->getItems(),
            message: 'Final Items Count',
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_Save_ReturnSuccessFalse_AnyEntitiesFailToSave(): void
    {
        $mockProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->method('getEntityType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProvider->method('getData')
            ->willReturn(
                $this->generate(
                    [
                        'klevu-api-key' => [
                            [
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 1,
                                    'apiKey' => 'klevu-api-key',
                                    'isIndexable' => true,
                                ]),
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 2,
                                    'apiKey' => 'klevu-api-key',
                                    'isIndexable' => false,
                                ]),
                            ],
                        ],
                    ],
                ),
            );

        $mockIndexingEntityRepository = $this->getMockBuilder(IndexingEntityRepositoryInterface::class)
            ->getMock();
        $mockIndexingEntityRepository->expects($this->exactly(2))
            ->method('create')
            ->willReturn(
                $this->objectManager->create(IndexingEntityInterface::class),
            );
        $matcher = $this->exactly(2);
        $mockIndexingEntityRepository->expects($matcher)
            ->method('save')
            ->willReturnCallback(callback: function () use ($matcher): IndexingEntityInterface {
                if ($matcher->getInvocationCount() === 1) {
                    throw new \Exception('Could not Save Entity');
                }
                return $this->objectManager->create(IndexingEntityInterface::class);
            });

        $addIndexingEntitiesAction = $this->objectManager->create(AddIndexingEntitiesActionInterface::class, [
            'indexingEntityRepository' => $mockIndexingEntityRepository,
        ]);

        $service = $this->instantiateTestObject([
            'addIndexingEntitiesAction' => $addIndexingEntitiesAction,
            'discoveryProviders' => [
                'products' => $mockProvider,
            ],
        ]);
        $resultGenerators = $service->execute(entityTypes: ['KLEVU_PRODUCT']);
        $resultsArray = [];
        foreach ($resultGenerators as $resultGenerator) {
            $resultsArray[] = iterator_to_array($resultGenerator);
        }
        $results = array_filter(
            array_merge(...$resultsArray),
        );
        $result = array_shift($results);

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 1, haystack: $messages, message: 'Message Count');
        $this->assertContains(
            needle: 'Failed to save Indexing Entities for Magento Entity IDs (1). See log for details.',
            haystack: $messages,
            message: 'Expected Message Exists',
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     */
    public function testExecute_Deletion_ReturnSuccessFalse_AnyAttributesFailToSave(): void
    {
        $apiKey = 'klevu-api-key';
        $indexingEntity1 = $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);
        $indexingEntity2 = $this->createIndexingEntity([
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_PRODUCT',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'simple',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
            IndexingEntity::IS_INDEXABLE => true,
        ]);

        $mockProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->method('getEntityType')
            ->willReturn('KLEVU_PRODUCT');
        $mockProvider->method('getData')
            ->willReturn(
                $this->generate(
                    [
                        'klevu-api-key' => [
                            [
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 1,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => true,
                                ]),
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 2,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => false,
                                ]),
                            ],
                        ],
                    ],
                ),
            );
        $mockProvider->method('getEntityProviderTypes')
            ->willReturn(['simple']);

        $mockFilterEntitiesToAddService = $this->getMockBuilder(FilterEntitiesToAddServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToAddService->expects($this->once())
            ->method('execute')
            ->willReturn($this->generate([]));

        $mockFilterEntitiesToDeleteService = $this->getMockBuilder(FilterEntitiesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToDeleteService->expects($this->once())
            ->method('execute')
            ->willReturn($this->generate([
                (int)$indexingEntity1->getId(),
                (int)$indexingEntity2->getId(),
            ]));

        $entity1 = $this->objectManager->create(IndexingEntityInterface::class);
        $entity1->setId(1);
        $entity1->setLastAction(Actions::ADD);
        $entity2 = $this->objectManager->create(IndexingEntityInterface::class);
        $entity2->setId(2);
        $entity2->setLastAction(Actions::ADD);
        $mockSearchResult = $this->getMockBuilder(IndexingEntitySearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->atLeastOnce())
            ->method('getItems')
            ->willReturn([$entity1, $entity2]);
        $mockIndexingEntityRepository = $this->getMockBuilder(IndexingEntityRepositoryInterface::class)
            ->getMock();
        $mockIndexingEntityRepository->expects($this->atLeastOnce())
            ->method('getList')
            ->willReturn($mockSearchResult);

        $matcher = $this->exactly(2);
        $mockIndexingEntityRepository->expects($matcher)
            ->method('save')
            ->willReturnCallback(callback: function () use ($matcher): IndexingEntityInterface {
                if ($matcher->getInvocationCount() === 1) {
                    throw new \Exception('Could not Save Entity');
                }
                return $this->objectManager->create(IndexingEntityInterface::class);
            });

        $setIndexingEntitiesToDeleteAction = $this->objectManager->create(
            type: SetIndexingEntitiesToDeleteActionInterface::class,
            arguments: [
                'indexingEntityRepository' => $mockIndexingEntityRepository,
            ],
        );

        $service = $this->instantiateTestObject([
            'setIndexingEntitiesToDeleteAction' => $setIndexingEntitiesToDeleteAction,
            'filterEntitiesToAddService' => $mockFilterEntitiesToAddService,
            'filterEntitiesToDeleteService' => $mockFilterEntitiesToDeleteService,
            'discoveryProviders' => [
                'KLEVU_PRODUCT' => $mockProvider,
            ],
        ]);
        $resultGenerators = $service->execute(entityTypes: ['KLEVU_PRODUCT']);
        $resultsArray = [];
        foreach ($resultGenerators as $resultGenerator) {
            $resultsArray[] = iterator_to_array($resultGenerator);
        }
        $results = array_filter(
            array_merge(...$resultsArray),
            static fn (DiscoveryResultInterface $result): bool => (
                $result->getAction() === Actions::DELETE->value
            ),
        );
        $result = array_shift($results);

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertContains(
            needle: 'Indexing entities (1) failed to save. See log for details.',
            haystack: $messages,
            message: 'Expected Message Exists',
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_SetExistingEntitiesToUpdate_WhenEntityIdsProvided(): void
    {
        $apiKey = 'klevu-api-key';
        $mockProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->method('getEntityType')
            ->willReturn('KLEVU_CMS');
        $mockProvider->method('getData')
            ->with(
                [$apiKey],
                [1, 2, 3, 4],
            )
            ->willReturn(
                $this->generate(
                    [
                        'klevu-api-key' => [
                            [
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 1,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => true,
                                ]),
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 2,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => false,
                                ]),
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 3,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => false,
                                ]),
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 4,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => true,
                                ]),
                            ],
                        ],
                    ],
                ),
            );
        $mockProvider->method('getEntityProviderTypes')
            ->willReturn(['page']);

        $mockFilterEntitiesToAddService = $this->getMockBuilder(FilterEntitiesToAddServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToAddService->expects($this->once())
            ->method('execute')
            ->willReturn($this->generate([]));

        $mockFilterEntitiesToDeleteService = $this->getMockBuilder(FilterEntitiesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToDeleteService->expects($this->once())
            ->method('execute')
            ->willReturn($this->generate([]));

        $entity1 = $this->objectManager->create(IndexingEntityInterface::class);
        $entity1->setId(1);
        $entity2 = $this->objectManager->create(IndexingEntityInterface::class);
        $entity2->setId(2);
        $entity3 = $this->objectManager->create(IndexingEntityInterface::class);
        $entity3->setId(3);
        $entity4 = $this->objectManager->create(IndexingEntityInterface::class);
        $entity4->setId(4);
        $mockSearchResult = $this->getMockBuilder(IndexingEntitySearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->atLeastOnce())
            ->method('getItems')
            ->willReturn([$entity1, $entity2, $entity3, $entity4]);
        $mockIndexingEntityRepository = $this->getMockBuilder(IndexingEntityRepositoryInterface::class)
            ->getMock();
        $mockIndexingEntityRepository->expects($this->atLeastOnce())
            ->method('getList')
            ->willReturn($mockSearchResult);

        $mockIndexingEntityRepository->expects($this->never())
            ->method('save');

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'page',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'page',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'page',
            IndexingEntity::TARGET_ID => 20,
            IndexingEntity::TARGET_PARENT_ID => 1,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'page',
            IndexingEntity::TARGET_ID => 3,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION_TIMESTAMP => null,
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'page',
            IndexingEntity::TARGET_ID => 4,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $setIndexingEntitiesToUpdateAction = $this->objectManager->create(
            type: SetIndexingEntitiesToUpdateActionInterface::class,
            arguments: [
                'indexingEntityRepository' => $mockIndexingEntityRepository,
            ],
        );

        $service = $this->instantiateTestObject([
            'setIndexingEntitiesToUpdateAction' => $setIndexingEntitiesToUpdateAction,
            'filterEntitiesToAddService' => $mockFilterEntitiesToAddService,
            'filterEntitiesToDeleteService' => $mockFilterEntitiesToDeleteService,
            'discoveryProviders' => [
                'cms' => $mockProvider,
            ],
        ]);
        $resultGenerators = $service->execute(
            entityTypes: ['KLEVU_CMS'],
            apiKeys: [$apiKey],
            entityIds: [1, 2, 3, 4],
        );
        $resultsArray = [];
        foreach ($resultGenerators as $resultGenerator) {
            $resultsArray[] = iterator_to_array($resultGenerator);
        }
        $results = array_filter(
            array_merge(...$resultsArray),
            static fn (DiscoveryResultInterface $result): bool => (
                $result->getAction() === Actions::UPDATE->value
            ),
        );
        $result = array_shift($results);

        $this->assertTrue(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertFalse(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 0, haystack: $messages, message: 'Message Count');
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_SetExistingEntitiesToUpdate_WhenEntityIdsEmptyArray(): void
    {
        $apiKey = 'klevu-api-key';
        $mockProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->method('getEntityType')
            ->willReturn('KLEVU_CMS');
        $mockProvider->method('getData')
            ->with(
                [$apiKey],
                [],
            )
            ->willReturn(
                $this->generate(
                    [
                        'klevu-api-key' => [
                            [
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 1,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => true,
                                ]),
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 2,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => false,
                                ]),
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 20,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => true,
                                ]),
                            ],
                        ],
                    ],
                ),
            );
        $mockProvider->method('getEntityProviderTypes')
            ->willReturn(['page']);

        $mockFilterEntitiesToAddService = $this->getMockBuilder(FilterEntitiesToAddServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToAddService->expects($this->once())
            ->method('execute')
            ->willReturn($this->generate([]));

        $mockFilterEntitiesToDeleteService = $this->getMockBuilder(FilterEntitiesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToDeleteService->expects($this->once())
            ->method('execute')
            ->willReturn($this->generate([]));

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'page',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'page',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'page',
            IndexingEntity::TARGET_ID => 20,
            IndexingEntity::TARGET_PARENT_ID => 1,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject([
            'filterEntitiesToAddService' => $mockFilterEntitiesToAddService,
            'filterEntitiesToDeleteService' => $mockFilterEntitiesToDeleteService,
            'discoveryProviders' => [
                'cms' => $mockProvider,
            ],
        ]);
        $resultGenerators = $service->execute(
            entityTypes: ['KLEVU_CMS'],
            apiKeys: [$apiKey],
            entityIds: [],
        );
        $resultsArray = [];
        foreach ($resultGenerators as $resultGenerator) {
            $resultsArray[] = iterator_to_array($resultGenerator);
        }
        $results = array_filter(
            array_merge(...$resultsArray),
            static fn (DiscoveryResultInterface $result): bool => (
                $result->getAction() === Actions::UPDATE->value
            ),
        );
        $result = array_shift($results);

        $this->assertTrue(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertFalse(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 0, haystack: $messages, message: 'Message Count');
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_LogsError_WhenIndexingEntitySaveExceptionThrown_forUpdate(): void
    {
        $apiKey = 'klevu-api-key';
        $mockProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->method('getEntityType')
            ->willReturn('KLEVU_CMS');
        $mockProvider->method('getData')
            ->with(
                [$apiKey],
                [1, 2],
            )
            ->willReturn(
                $this->generate(
                    [
                        'klevu-api-key' => [
                            [
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 1,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => true,
                                ]),
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 2,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => false,
                                ]),
                            ],
                        ],
                    ],
                ),
            );
        $mockProvider->method('getEntityProviderTypes')
            ->willReturn(['page']);

        $mockFilterEntitiesToAddService = $this->getMockBuilder(FilterEntitiesToAddServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToAddService->expects($this->once())
            ->method('execute')
            ->willReturn($this->generate([]));

        $mockFilterEntitiesToDeleteService = $this->getMockBuilder(FilterEntitiesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToDeleteService->expects($this->once())
            ->method('execute')
            ->willReturn($this->generate([]));

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'page',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CMS',
            IndexingEntity::TARGET_ENTITY_SUBTYPE => 'page',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => true,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::ADD,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $entity1 = $this->objectManager->create(IndexingEntityInterface::class);
        $entity1->setId(1);
        $entity1->setIsIndexable(true);
        $entity1->setNextAction(Actions::NO_ACTION);
        $entity2 = $this->objectManager->create(IndexingEntityInterface::class);
        $entity2->setId(2);
        $entity2->setIsIndexable(true);
        $entity2->setNextAction(Actions::DELETE);
        $mockSearchResult = $this->getMockBuilder(IndexingEntitySearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->atLeastOnce())
            ->method('getItems')
            ->willReturn([$entity1, $entity2]);
        $mockIndexingEntityRepository = $this->getMockBuilder(IndexingEntityRepositoryInterface::class)
            ->getMock();
        $mockIndexingEntityRepository->expects($this->atLeastOnce())
            ->method('getList')
            ->willReturn($mockSearchResult);

        $mockIndexingEntityRepository->expects($this->exactly(2))
            ->method('save')
            ->willThrowException(new \Exception('Some Exception'));

        $setIndexingEntitiesToUpdateAction = $this->objectManager->create(
            type: SetIndexingEntitiesToUpdateActionInterface::class,
            arguments: [
                'indexingEntityRepository' => $mockIndexingEntityRepository,
            ],
        );

        $service = $this->instantiateTestObject([
            'setIndexingEntitiesToUpdateAction' => $setIndexingEntitiesToUpdateAction,
            'filterEntitiesToAddService' => $mockFilterEntitiesToAddService,
            'filterEntitiesToDeleteService' => $mockFilterEntitiesToDeleteService,
            'discoveryProviders' => [
                'cms' => $mockProvider,
            ],
        ]);
        $resultGenerators = $service->execute(
            entityTypes: ['KLEVU_CMS'],
            apiKeys: [$apiKey],
            entityIds: [1, 2],
        );
        $resultsArray = [];
        foreach ($resultGenerators as $resultGenerator) {
            $resultsArray[] = iterator_to_array($resultGenerator);
        }
        $results = array_filter(
            array_merge(...$resultsArray),
            static fn (DiscoveryResultInterface $result): bool => (
                $result->getAction() === Actions::UPDATE->value
            ),
        );
        $result = array_shift($results);

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 1, haystack: $messages, message: 'Message Count');
        $this->assertContains(
            needle: 'Indexing entities (1, 2) failed to save. See log for details.',
            haystack: $messages,
            message: 'Expected Message Exists',
        );
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_SetExistingEntitiesToBeIndexable(): void
    {
        $apiKey = 'klevu-api-key';
        $mockProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->method('getEntityType')
            ->willReturn('KLEVU_CATEGORY');
        $mockProvider->method('getData')
            ->with([$apiKey])
            ->willReturn(
                $this->generate(
                    [
                        'klevu-api-key' => [
                            [
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 1,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => true,
                                ]),
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 2,
                                    'entityParentId' => 3,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => true,
                                ]),
                            ],
                        ],
                    ],
                ),
            );

        $mockFilterEntitiesToAddService = $this->getMockBuilder(FilterEntitiesToAddServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToAddService->expects($this->once())
            ->method('execute')
            ->willReturn($this->generate([]));

        $mockFilterEntitiesToDeleteService = $this->getMockBuilder(FilterEntitiesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToDeleteService->expects($this->never())
            ->method('execute');

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::TARGET_PARENT_ID => 3,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 20,
            IndexingEntity::TARGET_PARENT_ID => 1,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $service = $this->instantiateTestObject([
            'filterEntitiesToAddService' => $mockFilterEntitiesToAddService,
            'filterEntitiesToDeleteService' => $mockFilterEntitiesToDeleteService,
            'discoveryProviders' => [
                'categories' => $mockProvider,
            ],
        ]);
        $resultGenerators = $service->execute(
            entityTypes: ['KLEVU_CATEGORY'],
            apiKeys: [$apiKey],
            entityIds: [1, 2],
        );
        $resultsArray = [];
        foreach ($resultGenerators as $resultGenerator) {
            $resultsArray[] = iterator_to_array($resultGenerator);
        }
        $results = array_filter(
            array_merge(...$resultsArray),
            static fn (DiscoveryResultInterface $result): bool => (
                $result->getAction() === Actions::ADD->value
            ),
        );
        $result = array_shift($results);

        $this->assertTrue(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertFalse(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 0, haystack: $messages, message: 'Message Count');
    }

    public function WhenIndexingEntitySaveExceptionThrown_forChangeOfIndexableStatus(): void
    {
        $apiKey = 'klevu-api-key';
        $mockProvider = $this->getMockBuilder(EntityDiscoveryProviderInterface::class)
            ->getMock();
        $mockProvider->expects($this->exactly(3))
            ->method('getEntityType')
            ->willReturn('KLEVU_CATEGORY');
        $mockProvider->expects($this->once())
            ->method('getData')
            ->with([$apiKey])
            ->willReturn(
                $this->generate(
                    [
                        'klevu-api-key' => [
                            [
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 1,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => true,
                                ]),
                                $this->objectManager->create(MagentoEntityInterface::class, [
                                    'entityId' => 2,
                                    'entityParentId' => 3,
                                    'apiKey' => $apiKey,
                                    'isIndexable' => true,
                                ]),
                            ],
                        ],
                    ],
                ),
            );

        $mockFilterEntitiesToAddService = $this->getMockBuilder(FilterEntitiesToAddServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToAddService->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $mockFilterEntitiesToDeleteService = $this->getMockBuilder(FilterEntitiesToDeleteServiceInterface::class)
            ->getMock();
        $mockFilterEntitiesToDeleteService->expects($this->once())
            ->method('execute')
            ->willReturn([]);

        $entity1 = $this->objectManager->create(IndexingEntityInterface::class);
        $entity1->setId(1);
        $entity2 = $this->objectManager->create(IndexingEntityInterface::class);
        $entity2->setId(2);
        $mockSearchResult = $this->getMockBuilder(IndexingEntitySearchResultsInterface::class)
            ->getMock();
        $mockSearchResult->expects($this->once())
            ->method('getItems')
            ->willReturn([$entity1, $entity2]);
        $mockIndexingEntityRepository = $this->getMockBuilder(IndexingEntityRepositoryInterface::class)
            ->getMock();
        $mockIndexingEntityRepository->expects($this->once())
            ->method('getList')
            ->willReturn($mockSearchResult);

        $mockIndexingEntityRepository->expects($this->exactly(2))
            ->method('save')
            ->willThrowException(new \Exception('Some Exception'));

        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 1,
            IndexingEntity::TARGET_PARENT_ID => null,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 2,
            IndexingEntity::TARGET_PARENT_ID => 3,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);
        $this->createIndexingEntity(data: [
            IndexingEntity::API_KEY => $apiKey,
            IndexingEntity::TARGET_ENTITY_TYPE => 'KLEVU_CATEGORY',
            IndexingEntity::TARGET_ID => 20,
            IndexingEntity::TARGET_PARENT_ID => 1,
            IndexingEntity::IS_INDEXABLE => false,
            IndexingEntity::NEXT_ACTION => Actions::NO_ACTION,
            IndexingEntity::LAST_ACTION => Actions::DELETE,
            IndexingEntity::LAST_ACTION_TIMESTAMP => date('Y-m-d H:i:s'),
        ]);

        $setIndexingEntitiesToBeIndexableAction = $this->objectManager->create(
            type: SetIndexingEntitiesToBeIndexableActionInterface::class,
            arguments: [
                'indexingEntityRepository' => $mockIndexingEntityRepository,
            ],
        );

        $service = $this->instantiateTestObject([
            'setIndexingEntitiesToBeIndexableAction' => $setIndexingEntitiesToBeIndexableAction,
            'filterEntitiesToAddService' => $mockFilterEntitiesToAddService,
            'filterEntitiesToDeleteService' => $mockFilterEntitiesToDeleteService,
            'discoveryProviders' => [
                'categories' => $mockProvider,
            ],
        ]);
        $resultGenerators = $service->execute(
            entityTypes: ['KLEVU_CATEGORY'],
            apiKeys: [$apiKey],
            entityIds: [1, 2],
        );
        $result = null;
        foreach ($resultGenerators as $resultGenerator) {
            $results = iterator_to_array($resultGenerator);
            $result = array_shift($results);
        }

        $this->assertFalse(condition: $result->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $result->hasMessages(), message: 'Has Messages');
        $messages = $result->getMessages();
        $this->assertCount(expectedCount: 1, haystack: $messages, message: 'Message Count');
        $this->assertContains(
            needle: 'Indexing entities (1, 2) failed to save. See log for details.',
            haystack: $messages,
            message: 'Expected Message Exists',
        );
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
        $indexingEntity->setTargetParentId((int)$data[IndexingEntity::TARGET_PARENT_ID]);
        $indexingEntity->setTargetEntityType($data[IndexingEntity::TARGET_ENTITY_TYPE] ?? 'KLEVU_CMS');
        $indexingEntity->setTargetEntitySubtype($data[IndexingEntity::TARGET_ENTITY_SUBTYPE] ?? 'page');
        $indexingEntity->setApiKey($data[IndexingEntity::API_KEY] ?? 'klevu-js-api-key');
        $indexingEntity->setNextAction($data[IndexingEntity::NEXT_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastAction($data[IndexingEntity::LAST_ACTION] ?? Actions::NO_ACTION);
        $indexingEntity->setLastActionTimestamp($data[IndexingEntity::LAST_ACTION_TIMESTAMP] ?? null);
        $indexingEntity->setLockTimestamp($data[IndexingEntity::LOCK_TIMESTAMP] ?? null);
        $indexingEntity->setIsIndexable($data[IndexingEntity::IS_INDEXABLE] ?? true);

        return $repository->save($indexingEntity);
    }
}
