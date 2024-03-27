<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Pipeline\Indexing\Stage;

use Klevu\Pipelines\Pipeline\PipelineInterface;
use Klevu\Pipelines\Pipeline\Stage\Log as BaseLog;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;

/**
 * Cannot use virtualType as no "physical" class exists for pipelineFqcnProvider to find
 */
class Log extends BaseLog
{
    /**
     * @param LoggerInterface $logger
     * @param PipelineInterface[] $stages
     * @param mixed[]|null $args
     * @param string $identifier
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __construct(
        LoggerInterface $logger,
        array $stages = [],
        ?array $args = null,
        string $identifier = '',
    ) {
        parent::__construct($logger, $stages, $args, $identifier);
    }
}
