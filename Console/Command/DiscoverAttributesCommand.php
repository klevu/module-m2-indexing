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
    public const OPTION_API_KEY = 'api-key';
    public const OPTION_ATTRIBUTE_TYPE = 'attribute-type';

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

        $this->setName(static::COMMAND_NAME);
        $this->setDescription(
            (string)__('Find Attributes and add them to "klevu_indexing_attribute" table so they can be indexed.'),
        );
        $this->addOption(
            name: static::OPTION_API_KEY,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__('Discover Attributes only for this API Key (optional).'),
        );
        $this->addOption(
            name: static::OPTION_ATTRIBUTE_TYPE,
            mode: InputOption::VALUE_OPTIONAL,
            description: (string)__('Discover Attributes only for this Attribute Type (optional).'),
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
            sprintf(
                '<comment>%s</comment>',
                __('Begin Attribute Discovery.'),
            ),
        );
        $apiKey = $input->getOption(static::OPTION_API_KEY);

        $success = $this->discoveryOrchestratorService->execute(
            attributeType: $input->getOption(static::OPTION_ATTRIBUTE_TYPE),
            apiKeys: $apiKey ? [$apiKey] : null,
        );

        if ($success->isSuccess()) {
            $output->writeln(
                sprintf(
                    '<comment>%s</comment>',
                    __('Attribute Discovery Completed Successfully.'),
                ),
            );
        } else {
            $return = Cli::RETURN_FAILURE;
            $output->writeln(
                sprintf(
                    '<error>%s</error>',
                    __('Attribute Discovery Failed. See Logs for more details.'),
                ),
            );
        }

        return $return;
    }
}
