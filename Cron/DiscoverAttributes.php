<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Cron;

use Klevu\IndexingApi\Service\AttributeDiscoveryOrchestratorServiceInterface;
use Psr\Log\LoggerInterface;

class DiscoverAttributes
{
    /**
     * @var AttributeDiscoveryOrchestratorServiceInterface
     */
    private AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService
     * @param LoggerInterface $logger
     */
    public function __construct(
        AttributeDiscoveryOrchestratorServiceInterface $discoveryOrchestratorService,
        LoggerInterface $logger,
    ) {
        $this->discoveryOrchestratorService = $discoveryOrchestratorService;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function execute(): void
    {
        $this->logger->info(
            message: 'Starting discovery of attributes.',
        );

        $success = $this->discoveryOrchestratorService->execute();

        $this->logger->info(
            message: sprintf(
                'Discovery of attributes completed %s.',
                $success->isSuccess() ? 'successfully' : 'with failures. See logs for more details',
            ),
        );
    }
}
