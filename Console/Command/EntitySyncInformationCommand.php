<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Console\Command;

use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\Configuration\Service\Provider\CronExecutionDataProviderInterface;
use Klevu\IndexingApi\Api\Data\EntitySyncConditionsValuesInterface;
use Klevu\IndexingApi\Service\Determiner\IsIndexableDeterminerInterface;
use Klevu\IndexingApi\Service\Provider\EntitySyncConditionsValuesProviderInterface;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesHandlerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

// phpcs:disable SlevomatCodingStandard.Classes.ClassStructure.IncorrectGroupOrder

class EntitySyncInformationCommand extends Command
{
    // We're not using entity-sync-information as it makes shorthanding k:i:entity-s for actual
    //  sync ambiguous
    public const COMMAND_NAME = 'klevu:indexing:sync-information';
    public const OPTION_ENTITY_TYPE = 'entity-type';
    public const OPTION_TARGET_ENTITY_ID = 'target-entity-id';

    /**
     * @var SerializerInterface
     */
    private readonly SerializerInterface $serializer;
    /**
     * @var ScopeConfigInterface
     */
    private readonly ScopeConfigInterface $scopeConfig;
    /**
     * @var CronExecutionDataProviderInterface
     */
    private readonly CronExecutionDataProviderInterface $cronExecutionDataProvider;
    /**
     * @var ApiKeysProviderInterface
     */
    private readonly ApiKeysProviderInterface $apiKeysProvider;
    /**
     * @var EntitySyncConditionsValuesProviderInterface
     */
    private readonly EntitySyncConditionsValuesProviderInterface $entitySyncConditionsValuesProvider;
    /**
     * @var array<string, IsIndexableDeterminerInterface>
     */
    private array $isIndexableDeterminers = [];

    /**
     * @param SerializerInterface $serializer
     * @param ScopeConfigInterface $scopeConfig
     * @param CronExecutionDataProviderInterface $cronExecutionDataProvider
     * @param EntitySyncConditionsValuesProviderInterface $entitySyncConditionsValuesProvider
     * @param ApiKeysProviderInterface $apiKeysProvider
     * @param array<string, IsIndexableDeterminerInterface> $isIndexableDeterminers
     * @param string|null $name
     */
    public function __construct(
        SerializerInterface $serializer,
        ScopeConfigInterface $scopeConfig,
        CronExecutionDataProviderInterface $cronExecutionDataProvider,
        ApiKeysProviderInterface $apiKeysProvider,
        EntitySyncConditionsValuesProviderInterface $entitySyncConditionsValuesProvider,
        array $isIndexableDeterminers = [],
        ?string $name = null,
    ) {
        parent::__construct($name);

        $this->serializer = $serializer;
        $this->scopeConfig = $scopeConfig;
        $this->cronExecutionDataProvider = $cronExecutionDataProvider;
        $this->apiKeysProvider = $apiKeysProvider;
        $this->entitySyncConditionsValuesProvider = $entitySyncConditionsValuesProvider;
        array_walk($isIndexableDeterminers, [$this, 'addIsIndexableDeterminer']);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName(name: static::COMMAND_NAME);
        $this->setDescription(
            description: (string)__(
                'Get information about entity sync conditions for a specific target entity.',
            ),
        );
        $this->addOption(
            name: static::OPTION_ENTITY_TYPE,
            mode: InputOption::VALUE_REQUIRED,
            description: (string)__(
                'Entity Type. e.g. --entity-type KLEVU_PRODUCT',
            ),
        );
        $this->addOption(
            name: static::OPTION_TARGET_ENTITY_ID,
            mode: InputOption::VALUE_REQUIRED,
            description: (string)__(
                'Magento database ID of the target entity. e.g. --target-entity-id 42',
            ),
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws NoSuchEntityException
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $targetEntityType = (string)$input->getOption(static::OPTION_ENTITY_TYPE);
        if (!$targetEntityType) {
            $output->writeln('<error>Please provide a valid entity type using the --entity-type option.</error>');

            return self::FAILURE;
        }
        $targetEntityId = (int)$input->getOption(static::OPTION_TARGET_ENTITY_ID);
        if ($targetEntityId <= 0) {
            $output->writeln(
                messages: [
                    '<error>Please provide a valid target entity ID using the --target-entity-id option.</error>',
                ],
            );

            return self::FAILURE;
        }

        $output->writeln(
            messages: [
                '==========================',
                __(' GLOBAL CONFIGURATION')->render(),
                '==========================',
                '',
            ],
        );
        $output->writeln(
            messages: $this->formatRows(
                data: $this->getGlobalConfigurationData($targetEntityType),
                indent: 0,
            ),
        );

        $output->writeln(
            messages: [
                '==========================',
                __(' SYNC INFORMATION')->render(),
                '==========================',
                '',
            ],
        );
        $apiKeys = $this->apiKeysProvider->get(storeIds: []);
        if (empty($apiKeys)) {
            $output->writeln(
                messages: [
                    __('[!] No API Keys integrated')->render(),
                    '',
                ],
            );

            return self::SUCCESS;
        }

        $entitySyncConditionsValues = $this->entitySyncConditionsValuesProvider->get(
            targetEntityType: (string)$input->getOption(static::OPTION_ENTITY_TYPE),
            targetEntityId: (int)$input->getOption(static::OPTION_TARGET_ENTITY_ID),
        );
        foreach ($entitySyncConditionsValues as $index => $conditionsValuesData) {
            $output->writeln(
                messages: [
                    __('[%1] ', $index + 1)->render(),
                ],
            );

            $output->writeln(
                messages: $this->formatRows(
                    data: $this->getSyncInformationSummaryData(
                        targetEntityType: $targetEntityType,
                        conditionsValuesData: $conditionsValuesData,
                    ),
                    indent: 4,
                ),
            );

            $output->writeln(
                messages: [
                    __('    Indexing Entity')->render(),
                ],
            );

            $output->writeln(
                messages: $this->formatRows(
                    data: $this->getIndexingEntitySummaryData(
                        targetEntityType: $targetEntityType,
                        conditionsValuesData: $conditionsValuesData,
                    ),
                    indent: 8,
                ),
            );

            $output->writeln(
                messages: [
                    __('    Real Time Sync Information')->render(),
                ],
            );

            $output->writeln(
                messages: $this->formatRows(
                    data: $this->getRealTimeSyncInformationData(
                        targetEntityType: $targetEntityType,
                        conditionsValuesData: $conditionsValuesData,
                    ),
                    indent: 8,
                ),
            );

            $output->writeln(
                messages: [
                    '--------------------------------------------------',
                    '',
                ],
            );
        }

        return self::SUCCESS;
    }

    /**
     * @param string $targetEntityType
     *
     * @return mixed[][]
     */
    public function getGlobalConfigurationData(
        string $targetEntityType, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
    ): array {
        $cronExecutionData = $this->cronExecutionDataProvider->get();

        // phpcs:disable Generic.Files.LineLength.TooLong
        $discoveryNextScheduled = match (true) {
            !empty($cronExecutionData['klevu_indexing_discover_entities']['next_scheduled_execution']['started_at']) => __(
                    'Running since: %1',
                    $cronExecutionData['klevu_indexing_discover_entities']['next_scheduled_execution']['started_at'],
                )->render(),
            !empty($cronExecutionData['klevu_indexing_discover_entities']['next_scheduled_execution']['scheduled_at']) =>
                $cronExecutionData['klevu_indexing_discover_entities']['next_scheduled_execution']['scheduled_at'],
            default => __('<n/a>')->render(),
        };
        $syncNextScheduled = match (true) {
            !empty($cronExecutionData['klevu_indexing_sync_entities']['next_scheduled_execution']['started_at']) => __(
                    'Running since: %1',
                    $cronExecutionData['klevu_indexing_sync_entities']['next_scheduled_execution']['started_at'],
                )->render(),
            !empty($cronExecutionData['klevu_indexing_sync_entities']['next_scheduled_execution']['scheduled_at']) =>
                $cronExecutionData['klevu_indexing_sync_entities']['next_scheduled_execution']['scheduled_at'],
            default => __('<n/a>')->render(),
        };

        return [
            [
                'Discovery Cron Schedule' => $cronExecutionData['klevu_indexing_discover_entities']['schedule']
                    ?? __('Not Set')->render(),
                'Discovery Last Run' => $cronExecutionData['klevu_indexing_discover_entities']['last_successful_execution']['finished_at']
                    ?? __('<n/a>')->render(),
                'Discovery Next Scheduled' => $discoveryNextScheduled,
            ],
            [
                'Sync Cron Schedule' => $cronExecutionData['klevu_indexing_sync_entities']['schedule']
                    ?? __('Not Set')->render(),
                'Sync Last Run' => $cronExecutionData['klevu_indexing_sync_entities']['last_successful_execution']['finished_at']
                    ?? __('<n/a>')->render(),
                'Sync Next Scheduled' => $syncNextScheduled,
            ],
            [
                'Enable Pipelines Autogeneration' => $this->scopeConfig->isSetFlag(
                    path: ConfigurationOverridesHandlerInterface::XML_PATH_CONFIGURATION_OVERRIDES_GENERATION_ENABLED,
                ),
            ],
        ];
        //phpcs:enable Generic.Files.LineLength.TooLong
    }

    /**
     * @param string $targetEntityType
     * @param EntitySyncConditionsValuesInterface $conditionsValuesData
     *
     * @return mixed[][]
     * @throws NoSuchEntityException
     */
    public function getSyncInformationSummaryData(
        string $targetEntityType,
        EntitySyncConditionsValuesInterface $conditionsValuesData,
    ): array {
        $store = $conditionsValuesData->getStore();
        $website = method_exists($store, 'getWebsite')
            ? $store->getWebsite()
            : null;

        $targetEntity = $conditionsValuesData->getTargetEntity();
        $targetParentEntity = $conditionsValuesData->getTargetParentEntity();

        return [
            [
                'API Key' => $conditionsValuesData->getApiKey(),
                'Website' => $website?->getCode() ?? $store?->getWebsiteId(),
                'Store' => $store?->getCode() ?? __('<n/a>')->render(),
            ],
            [
                'Entity Type' => $targetEntityType,
                'Entity ID' => $targetEntity?->getId() ?? __('<n/a>')->render(),
                'Parent Entity ID' => $targetParentEntity?->getId() ?? __('<n/a>')->render(),
            ],
        ];
    }

    /**
     * @param string $targetEntityType
     * @param EntitySyncConditionsValuesInterface $conditionsValuesData
     *
     * @return mixed[][]
     */
    public function getIndexingEntitySummaryData(
        string $targetEntityType, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        EntitySyncConditionsValuesInterface $conditionsValuesData,
    ): array {
        $indexingEntity = $conditionsValuesData->getIndexingEntity();
        if (!$indexingEntity) {
            return [
                [
                    'ID' => __('<n/a>')->render(),
                ],
            ];
        }

        $nextActionValue = $indexingEntity->getNextAction()?->value;
        if ($nextActionValue && $indexingEntity->getLockTimestamp()) {
            $nextActionValue .= __(
                ' (locked at %1)',
                $indexingEntity->getLockTimestamp(),
            )->render();
        }

        $lastActionValue = $indexingEntity->getLastAction()?->value;
        if ($lastActionValue && $indexingEntity->getLastActionTimestamp()) {
            $lastActionValue .= __(
                ' (executed at %1)',
                $indexingEntity->getLastActionTimestamp(),
            )->render();
        }

        return [
            [
                'ID' => $indexingEntity->getId(),
                'Created At' => $indexingEntity->getCreatedAt(),
                'Updated At' => $indexingEntity->getUpdatedAt(),
            ],
            [
                'Entity Subtype' => $indexingEntity->getTargetEntitySubtype(),
                'Is Indexable' => __($indexingEntity->getIsIndexable() ? 'Yes' : 'No')->render(),
                'Requires Update' => __($indexingEntity->getRequiresUpdate() ? 'Yes' : 'No')->render(),
                'Orig Values' => $this->serializer->serialize(
                    data: $indexingEntity->getRequiresUpdateOrigValues(),
                ),
            ],
            [
                'Next Action' => $nextActionValue,
                'Last Action' => $lastActionValue,
            ],
        ];
    }

    /**
     * @param string $targetEntityType
     * @param EntitySyncConditionsValuesInterface $conditionsValuesData
     *
     * @return mixed[]
     */
    public function getRealTimeSyncInformationData(
        string $targetEntityType,
        EntitySyncConditionsValuesInterface $conditionsValuesData,
    ): array {
        $indexingEntity = $conditionsValuesData->getIndexingEntity();
        $isIndexableDeterminer = $this->isIndexableDeterminers[$targetEntityType] ?? null;

        $targetEntity = $conditionsValuesData->getTargetEntity();
        $isIndexable = ($isIndexableDeterminer && $targetEntity)
            ? $isIndexableDeterminer->execute(
                entity: $targetEntity,
                store: $conditionsValuesData->getStore(),
                entitySubtype: (string)$indexingEntity?->getTargetEntitySubtype(),
            )
            : null;

        return [
            [
                'Is Indexable' => match ($isIndexable) {
                    true => __('Yes')->render(),
                    false => __('No')->render(),
                    default => __('<n/a>')->render(),
                },
            ],
        ];
    }

    /**
     * @param IsIndexableDeterminerInterface $isIndexableDeterminer
     * @param string $entityType
     *
     * @return void
     */
    private function addIsIndexableDeterminer(
        IsIndexableDeterminerInterface $isIndexableDeterminer,
        string $entityType,
    ): void {
        $this->isIndexableDeterminers[$entityType] = $isIndexableDeterminer;
    }

    /**
     * @param array<array<string, mixed>> $data
     *
     * @return string[]
     */
    private function formatRows(array $data, int $indent): array
    {
        $maxLabelLength = max(
            value: array_map(
                callback: static fn (string $label) => strlen($label),
                array: array_keys(
                    array: array_merge([], ...$data),
                ),
            ),
        );

        $formattedRowGroups = [];
        foreach ($data as $dataRows) {
            $formattedRowGroups[] = array_map(
                static fn (string $label, mixed $value): string => sprintf(
                    '%s%-' . $maxLabelLength . 's : %s',
                    str_repeat(' ', $indent),
                    __($label)->render(),
                    match (true) {
                        null === $value => __('<null>')->render(),
                        is_bool($value) => __($value ? 'Yes' : 'No')->render(),
                        is_array($value) || is_object($value) => $this->serializer->serialize($value),
                        default => (string)$value,
                    },
                ),
                array_keys($dataRows),
                array_values($dataRows),
            );

            $formattedRowGroups[] = [''];
        }

        return array_merge([], ...$formattedRowGroups);
    }
}
