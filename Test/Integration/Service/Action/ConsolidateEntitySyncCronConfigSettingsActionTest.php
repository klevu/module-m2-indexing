<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Indexing\Constants;
use Klevu\Indexing\Model\Source\Options\CronFrequency;
use Klevu\Indexing\Service\Action\ConsolidateEntitySyncCronConfigSettingsAction;
use Klevu\Indexing\Service\Action\ConsolidateSyncCronConfigSettingsAction;
use Klevu\IndexingApi\Service\Action\ConsolidateCronConfigSettingsActionInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\App\Config\Storage\Writer as ConfigWriter;
use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

/**
 * @covers ConsolidateSyncCronConfigSettingsAction
 * @method ConsolidateCronConfigSettingsActionInterface instantiateTestObject(?array $arguments = null)
 * @method ConsolidateCronConfigSettingsActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class ConsolidateEntitySyncCronConfigSettingsActionTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line Used by traits

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = ObjectManager::getInstance();

        // @phpstan-ignore-next-line Virtual class
        $this->implementationFqcn = ConsolidateEntitySyncCronConfigSettingsAction::class;
        $this->implementationForVirtualType = ConsolidateSyncCronConfigSettingsAction::class;
        $this->interfaceFqcn = ConsolidateCronConfigSettingsActionInterface::class;
    }

    /**
     * @return string[][]
     */
    public static function dataProvider_cronFrequencies(): array
    {
        /** @var OptionSourceInterface $cronFrequencyOptionSource */
        $cronFrequencyOptionSource = ObjectManager::getInstance()->get(CronFrequency::class);

        $allFrequencies = array_column(
            array: $cronFrequencyOptionSource->toOptionArray(),
            column_key: 'value',
        );
        $filteredFrequencies = array_filter(
            $allFrequencies,
            static fn (string $frequency): bool => !in_array(
                $frequency,
                [CronFrequency::OPTION_CUSTOM, CronFrequency::OPTION_DISABLED],
                true,
            ),
        );

        return array_map(
            static fn (string $frequency): array => [$frequency],
            $filteredFrequencies,
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @dataProvider dataProvider_cronFrequencies
     */
    public function testExecute_FrequencyNotCustom_ExpressionIsEmpty(
        string $frequency,
    ): void {
        ConfigFixture::setGlobal(
            path: Constants::XML_PATH_ENTITY_CRON_FREQUENCY,
            value: $frequency,
        );
        ConfigFixture::setGlobal(
            path: Constants::XML_PATH_ENTITY_CRON_EXPR,
            value: '',
        );

        $consolidateConfigAction = $this->instantiateTestObject([
            'configWriter' => $this->getMockConfigWriter([
                Constants::XML_PATH_ENTITY_CRON_EXPR => $frequency,
            ]),
        ]);

        $consolidateConfigAction->execute();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_FrequencyIsCustom_ExpressionMatchesFrequency(): void
    {
        ConfigFixture::setGlobal(
            path: Constants::XML_PATH_ENTITY_CRON_FREQUENCY,
            value: CronFrequency::OPTION_CUSTOM,
        );
        ConfigFixture::setGlobal(
            path: Constants::XML_PATH_ENTITY_CRON_EXPR,
            value: CronFrequency::OPTION_CUSTOM,
        );

        $consolidateConfigAction = $this->instantiateTestObject([
            'configWriter' => $this->getMockConfigWriter([
                Constants::XML_PATH_ENTITY_CRON_FREQUENCY => CronFrequency::OPTION_DISABLED,
                Constants::XML_PATH_ENTITY_CRON_EXPR => CronFrequency::OPTION_DISABLED,
            ]),
        ]);

        $consolidateConfigAction->execute();
    }

    /**
     * @magentoAppIsolation enabled
     * @dataProvider dataProvider_cronFrequencies
     */
    public function testExecute_FrequencyIsCustom_ExpressionNotCustom(
        string $expression,
    ): void {
        ConfigFixture::setGlobal(
            path: Constants::XML_PATH_ENTITY_CRON_FREQUENCY,
            value: CronFrequency::OPTION_CUSTOM,
        );
        ConfigFixture::setGlobal(
            path: Constants::XML_PATH_ENTITY_CRON_EXPR,
            value: $expression,
        );

        $consolidateConfigAction = $this->instantiateTestObject([
            'configWriter' => $this->getMockConfigWriter([
                Constants::XML_PATH_ENTITY_CRON_FREQUENCY => $expression,
            ]),
        ]);

        $consolidateConfigAction->execute();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_FrequencyIsCustom_ExpressionIsCustom(): void
    {
        ConfigFixture::setGlobal(
            path: Constants::XML_PATH_ENTITY_CRON_FREQUENCY,
            value: CronFrequency::OPTION_CUSTOM,
        );
        ConfigFixture::setGlobal(
            path: Constants::XML_PATH_ENTITY_CRON_EXPR,
            value: '*/7 * * * *',
        );

        $consolidateConfigAction = $this->instantiateTestObject([
            'configWriter' => $this->getMockConfigWriter([]),
        ]);

        $consolidateConfigAction->execute();
    }

    /**
     * @param string[] $expectedPathValues
     * @return MockObject&ConfigWriter
     */
    private function getMockConfigWriter(
        array $expectedPathValues,
    ): MockObject&ConfigWriter {
        $mockConfigWriter = $this->getMockBuilder(ConfigWriter::class)
            ->disableOriginalConstructor()
            ->getMock();

        if ($expectedPathValues) {
            $matcher = $this->exactly(
                count($expectedPathValues),
            );
            $mockConfigWriter->expects($matcher)
                ->method('save')
                ->willReturnCallback(
                    function (string $path, mixed $value) use ($matcher, $expectedPathValues): void {
                        $paths = array_keys($expectedPathValues);
                        $this->assertSame(
                            expected: $paths[$matcher->getInvocationCount() - 1],
                            actual: $path,
                        );

                        $values = array_values($expectedPathValues);
                        $this->assertSame(
                            expected: $values[$matcher->getInvocationCount() - 1],
                            actual: $value,
                        );
                    },
                );
        } else {
            $mockConfigWriter->expects($this->never())
                ->method('save');
        }

        return $mockConfigWriter;
    }
}
