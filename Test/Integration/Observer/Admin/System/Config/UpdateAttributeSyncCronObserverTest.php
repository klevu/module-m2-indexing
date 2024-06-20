<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Observer\Admin\System\Config;

use Klevu\Indexing\Constants;
use Klevu\Indexing\Observer\Admin\System\Config\UpdateAttributeSyncCronObserver;
use Klevu\IndexingApi\Service\Action\ConsolidateCronConfigSettingsActionInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Event\ConfigInterface as EventConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers UpdateAttributeSyncCronObserver
 * @method UpdateAttributeSyncCronObserver instantiateTestObject(?array $arguments = null)
 * @method UpdateAttributeSyncCronObserver instantiateTestObjectFromInterface(?array $arguments = null)
 * @magentoAppArea adminhtml
 */
class UpdateAttributeSyncCronObserverTest extends TestCase
{
    use ObjectInstantiationTrait;
    use StoreTrait;
    use TestImplementsInterfaceTrait;

    private const OBSERVER_NAME = 'Klevu_Indexing_adminSystemConfig_updateAttributeSyncCron';
    private const EVENT_NAME = 'admin_system_config_changed_section_klevu_data_sync';

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

        $this->implementationFqcn = UpdateAttributeSyncCronObserver::class;
        $this->interfaceFqcn = ObserverInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();
    }

    /**
     * @magentoAppArea global
     */
    public function testObserver_IsNotConfigured_InGlobalScope(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayNotHasKey(key: self::OBSERVER_NAME, array: $observers);
    }

    public function testObserver_IsConfiguredInAdminScope(): void
    {
        $observerConfig = $this->objectManager->create(type: EventConfig::class);
        $observers = $observerConfig->getObservers(eventName: self::EVENT_NAME);

        $this->assertArrayHasKey(key: self::OBSERVER_NAME, array: $observers);
        $this->assertSame(
            expected: ltrim(string: UpdateAttributeSyncCronObserver::class, characters: '\\'),
            actual: $observers[self::OBSERVER_NAME]['instance'],
        );
    }

    public function testExecute_NoPathsChanged(): void
    {
        $mockConsolidateCronConfigSettings = $this->getMockConsolidateCronConfigSettingsAction();
        $mockConsolidateCronConfigSettings->expects($this->never())
            ->method('execute');

        /** @var Observer $observer */
        $observer = $this->objectManager->create(Observer::class, [
            'data' => [
                'changed_paths' => [],
            ],
        ]);

        $updateOrderSyncCron = $this->instantiateTestObject([
            'consolidateCronConfigSettingsAction' => $mockConsolidateCronConfigSettings,
        ]);

        $updateOrderSyncCron->execute($observer);
    }

    public function testExecute_UnrelatedPathsChanged(): void
    {
        $mockConsolidateCronConfigSettings = $this->getMockConsolidateCronConfigSettingsAction();
        $mockConsolidateCronConfigSettings->expects($this->never())
            ->method('execute');

        /** @var Observer $observer */
        $observer = $this->objectManager->create(Observer::class, [
            'data' => [
                'changed_paths' => [
                    'some/other/path',
                ],
            ],
        ]);

        $updateOrderSyncCron = $this->instantiateTestObject([
            'consolidateCronConfigSettingsAction' => $mockConsolidateCronConfigSettings,
        ]);

        $updateOrderSyncCron->execute($observer);
    }

    public function testExecute_FrequencyPathChanged(): void
    {
        $mockConsolidateCronConfigSettings = $this->getMockConsolidateCronConfigSettingsAction();
        $mockConsolidateCronConfigSettings->expects($this->once())
            ->method('execute');

        /** @var Observer $observer */
        $observer = $this->objectManager->create(Observer::class, [
            'data' => [
                'changed_paths' => [
                    Constants::XML_PATH_INDEXING_HISTORY_REMOVAL_AFTER_DAYS,
                    Constants::XML_PATH_ATTRIBUTE_CRON_FREQUENCY,
                ],
            ],
        ]);

        $updateOrderSyncCron = $this->instantiateTestObject([
            'consolidateCronConfigSettingsAction' => $mockConsolidateCronConfigSettings,
        ]);

        $updateOrderSyncCron->execute($observer);
    }

    public function testExecute_ExpressionPathChanged(): void
    {
        $mockConsolidateCronConfigSettings = $this->getMockConsolidateCronConfigSettingsAction();
        $mockConsolidateCronConfigSettings->expects($this->once())
            ->method('execute');

        /** @var Observer $observer */
        $observer = $this->objectManager->create(Observer::class, [
            'data' => [
                'changed_paths' => [
                    Constants::XML_PATH_INDEXING_HISTORY_REMOVAL_AFTER_DAYS,
                    Constants::XML_PATH_ATTRIBUTE_CRON_EXPR,
                ],
            ],
        ]);

        $updateOrderSyncCron = $this->instantiateTestObject([
            'consolidateCronConfigSettingsAction' => $mockConsolidateCronConfigSettings,
        ]);

        $updateOrderSyncCron->execute($observer);
    }

    public function testExecute_BothPathsChanges(): void
    {
        $mockConsolidateCronConfigSettings = $this->getMockConsolidateCronConfigSettingsAction();
        $mockConsolidateCronConfigSettings->expects($this->once())
            ->method('execute');

        /** @var Observer $observer */
        $observer = $this->objectManager->create(Observer::class, [
            'data' => [
                'changed_paths' => [
                    Constants::XML_PATH_ATTRIBUTE_CRON_FREQUENCY,
                    Constants::XML_PATH_ATTRIBUTE_CRON_EXPR,
                ],
            ],
        ]);

        $updateOrderSyncCron = $this->instantiateTestObject([
            'consolidateCronConfigSettingsAction' => $mockConsolidateCronConfigSettings,
        ]);

        $updateOrderSyncCron->execute($observer);
    }

    /**
     * @return MockObject&ConsolidateCronConfigSettingsActionInterface
     */
    private function getMockConsolidateCronConfigSettingsAction(): MockObject&ConsolidateCronConfigSettingsActionInterface // phpcs:ignore Generic.Files.LineLength.TooLong
    {
        return $this->getMockBuilder(ConsolidateCronConfigSettingsActionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }
}
