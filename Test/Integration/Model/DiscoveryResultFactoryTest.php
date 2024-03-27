<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Model\DiscoveryResult;
use Klevu\Indexing\Model\DiscoveryResultFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class DiscoveryResultFactoryTest extends TestCase
{
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

        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testCreate_ReturnsFalse_WhenFalseArgumentProvided(): void
    {
        $syncResultFactory = $this->objectManager->get(DiscoveryResultFactory::class);
        /** @var DiscoveryResult $syncResult */
        $syncResult = $syncResultFactory->create([
            'isSuccess' => false,
            'messages' => [
                'Message 1',
                'Message 2',
            ],
        ]);
        $this->assertFalse(condition: $syncResult->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $syncResult->hasMessages(), message: 'Has Messages');
        $messages = $syncResult->getMessages();
        $this->assertCount(expectedCount: 2, haystack: $messages, message: 'Message Count');
        $this->assertContains(needle: 'Message 1', haystack: $messages);
        $this->assertContains(needle: 'Message 2', haystack: $messages);
    }

    public function testCreate_ReturnsTrue_WhenTrueArgumentProvided(): void
    {
        $syncResultFactory = $this->objectManager->get(DiscoveryResultFactory::class);
        /** @var DiscoveryResult $syncResult */
        $syncResult = $syncResultFactory->create([
            'isSuccess' => true,
        ]);
        $this->assertTrue($syncResult->isSuccess());
        $this->assertFalse(condition: $syncResult->hasMessages(), message: 'Has Messages');
        $this->assertCount(expectedCount: 0, haystack: $syncResult->getMessages(), message: 'Message Count');
    }
}
