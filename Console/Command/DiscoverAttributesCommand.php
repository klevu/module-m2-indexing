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

class DiscoverAttributesCommand extends Command
{
    public const COMMAND_NAME = 'klevu:indexing:attribute-discovery';
    public const OPTION_API_KEYS = 'api-keys';
    public const OPTION_ATTRIBUTE_TYPES = 'attribute-types';

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
                'Find Attributes and add them to "klevu_indexing_attribute" table so they can be indexed.',
            ),
        );
        $this->addOption(
            name: static::OPTION_API_KEYS,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Discover Attributes only for these API Keys (optional). Comma separated list '
                . 'e.g. --api-keys api-key-1,api-key-2',
            ),
        );
        $this->addOption(
            name: static::OPTION_ATTRIBUTE_TYPES,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__(
                'Discover Attributes only for these Attribute Types (optional). Comma separated list '
                . 'e.g. --attribute-types KLEVU_CMS,KLEVU_PRODUCTS',
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
                __('Begin Attribute Discovery.'),
            ),
        );
        $success = $this->discoveryOrchestratorService->execute(
            attributeTypes: $this->getAttributeTypes(input: $input),
            apiKeys: $this->getApiKeys(input: $input),
        );

        if ($success->isSuccess()) {
            $output->writeln(
                messages: sprintf(
                    '<comment>%s</comment>',
                    __('Attribute Discovery Completed Successfully.'),
                ),
            );
        } else {
            $return = Cli::RETURN_FAILURE;
            $output->writeln(
                messages: sprintf(
                    '<error>%s</error>',
                    __('Attribute Discovery Failed. See Logs for more details.'),
                ),
            );
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
}
