<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Console\Command;

use Klevu\IndexingApi\Service\AttributeDiscoveryOrchestratorServiceInterface;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateAttributesCommand extends Command
{
    public const COMMAND_NAME = 'klevu:indexing:attribute-update';
    public const OPTION_ATTRIBUTE_IDS = 'attribute-ids';
    public const OPTION_API_KEYS = 'api-keys';
    public const OPTION_ATTRIBUTE_TYPES = 'attribute-types';
    public const ATTRIBUTE_IDS_ALL = 'all';

    /**
     * @var AttributeDiscoveryOrchestratorServiceInterface
     */
    private AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;

    /**
     * @param AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     * @param string|null $name
     */
    public function __construct(
        AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService,
        ?string $name = null,
    ) {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;

        parent::__construct($name);
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
                'Recalculate attributes next action and set in "klevu_indexing_attribute" table.',
            ),
        );
        $this->addOption(
            name: static::OPTION_ATTRIBUTE_IDS,
            mode: InputOption::VALUE_REQUIRED,
            description: (string)__(
                'Update only these attributes. Comma separated list e.g. --attribute-ids 1,2,3',
            ),
        );
        $this->addOption(
            name: static::OPTION_API_KEYS,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Update Attributes only for these API Keys (optional). Comma separated list '
                . 'e.g. --api-keys api-key-1,api-key-2',
            ),
        );
        $this->addOption(
            name: static::OPTION_ATTRIBUTE_TYPES,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__('Update attributes only for these Attribute Types (optional). '
                . 'Comma separated list e.g. --attribute-types KLEVU_CMS,KLEVU_PRODUCTS',
            ),
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $return = Cli::RETURN_SUCCESS;
        $output->writeln(
            messages: sprintf(
                '<comment>%s</comment>',
                __('Begin Attribute Update.'),
            ),
        );
        $attributeIds = $input->getOption(name: static::OPTION_ATTRIBUTE_IDS);
        if (!$attributeIds) {
            $output->writeln(
                messages: sprintf(
                    '<error>%s</error>',
                    __('Attribute IDs are required.'),
                ),
            );
            $return = Cli::RETURN_FAILURE;
        } else {
            $success = $this->discoveryOrchestratorService->execute(
                attributeTypes: $this->getAttributeTypes(input: $input),
                apiKeys: $this->getApiKeys(input: $input),
                attributeIds: $this->formatAttributeIds(attributeIds: $attributeIds),
            );
            if ($success->isSuccess()) {
                $output->writeln(
                    messages: sprintf(
                        '<comment>%s</comment>',
                        __('Attribute Update Completed Successfully.'),
                    ),
                );
            } else {
                $return = Cli::RETURN_FAILURE;
                $output->writeln(
                    messages: sprintf(
                        '<error>%s</error>',
                        __('Attribute Update Failed. See Logs for more details.'),
                    ),
                );
            }
        }

        return $return;
    }

    /**
     * @param InputInterface $input
     *
     * @return string[]
     */
    private function getApiKeys(InputInterface $input): array
    {
        $apiKeys = $input->getOption(name: static::OPTION_API_KEYS);

        return $apiKeys
            ? array_map(callback: 'trim', array: explode(',', $apiKeys))
            : [];
    }

    /**
     * @param InputInterface $input
     *
     * @return string[]
     */
    private function getAttributeTypes(InputInterface $input): array
    {
        $attributeTypes = $input->getOption(name: static::OPTION_ATTRIBUTE_TYPES);

        return $attributeTypes
            ? array_map(callback: 'trim', array: explode(',', $attributeTypes))
            : [];
    }

    /**
     * @param mixed $attributeIds
     *
     * @return int[]
     */
    private function formatAttributeIds(mixed $attributeIds): array
    {
        if ($attributeIds === static::ATTRIBUTE_IDS_ALL) {
            return [];
        }

        return array_map(
            callback: 'intval',
            array: array_map(
                callback: 'trim',
                array: explode(separator: ',', string: $attributeIds),
            ),
        );
    }
}
