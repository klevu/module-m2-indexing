<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Observer;

use Klevu\Indexing\Observer\DiscoverEntitiesAfterIntegrationObserver;
use Klevu\IndexingApi\Service\EntityDiscoveryOrchestratorServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Event;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Klevu\Indexing\Observer\DiscoverEntitiesAfterIntegrationObserver::class
 * @method DiscoverEntitiesAfterIntegrationObserver instantiateTestObject(?array $arguments = null)
 * @method DiscoverEntitiesAfterIntegrationObserver instantiateTestObjectFromInterface(?array $arguments = null)
 */
class DiscoverEntitiesAfterIntegrationObserverTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_Indexing_discoverEntitiesAfterIntegration';
    private const EVENT_NAME = 'klevu_integrate_api_keys_after';

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = DiscoverEntitiesAfterIntegrationObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testInvalidateCustomerDataObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: DiscoverEntitiesAfterIntegrationObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    public function testExecute_WithoutApiKey_DoesNotCallOrchestrator(): void
    {
        $mockEvent = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEvent->expects($this->once())
            ->method('getData')
            ->with('apiKey')
            ->willReturn(null);

        $mockObserver = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockObserver->expects($this->once())
            ->method('getEvent')
            ->willReturn($mockEvent);

        $mockOrchestratorService = $this->getMockBuilder(EntityDiscoveryOrchestratorServiceInterface::class)
            ->getMock();
        $mockOrchestratorService->expects($this->never())
            ->method('execute');

        $observer = $this->instantiateTestObject([
            'discoveryOrchestratorService' => $mockOrchestratorService,
        ]);
        $observer->execute($mockObserver);
    }

    public function testExecute_WithApiKey_DoesNotCallOrchestrator(): void
    {
        $apiKey = 'klevu-test-api-key';

        $mockEvent = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockEvent->expects($this->once())
            ->method('getData')
            ->with('apiKey')
            ->willReturn($apiKey);

        $mockObserver = $this->getMockBuilder(Observer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockObserver->expects($this->once())
            ->method('getEvent')
            ->willReturn($mockEvent);

        $mockOrchestratorService = $this->getMockBuilder(EntityDiscoveryOrchestratorServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockOrchestratorService->expects($this->once())
            ->method('execute')
            ->with(null, [$apiKey], []);

        $observer = $this->instantiateTestObject([
            'discoveryOrchestratorService' => $mockOrchestratorService,
        ]);
        $observer->execute($mockObserver);
    }
}
