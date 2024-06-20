<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Observer;

use Klevu\Indexing\Observer\DiscoverEntitiesAfterIntegrationRemovalObserver;
use Klevu\IndexingApi\Service\Action\CreateCronScheduleActionInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Event;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class DiscoverEntitiesAfterIntegrationRemovalObserverTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_Indexing_discoverEntitiesAfterIntegrationRemoval';
    private const EVENT_NAME = 'klevu_remove_api_keys_after';

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

        $this->implementationFqcn = DiscoverEntitiesAfterIntegrationRemovalObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testInvalidateCustomerDataObserver_IsConfigured(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: DiscoverEntitiesAfterIntegrationRemovalObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    public function testExecute_WithApiKey_DoesNotTriggersAction(): void
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

        $mockCreateCronScheduleAction = $this->getMockBuilder(CreateCronScheduleActionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockCreateCronScheduleAction->expects($this->never())
            ->method('execute');

        $observer = $this->instantiateTestObject([
            'createCronScheduleAction' => $mockCreateCronScheduleAction,
        ]);
        $observer->execute($mockObserver);
    }

    public function testExecute_WithApiKey_TriggersAction(): void
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

        $mockCreateCronScheduleAction = $this->getMockBuilder(CreateCronScheduleActionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockCreateCronScheduleAction->expects($this->once())
            ->method('execute');

        $observer = $this->instantiateTestObject([
            'createCronScheduleAction' => $mockCreateCronScheduleAction,
        ]);
        $observer->execute($mockObserver);
    }
}
