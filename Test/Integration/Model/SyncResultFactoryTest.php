<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Model;

use Klevu\Indexing\Model\SyncResult;
use Klevu\Indexing\Model\SyncResultFactory;
use Klevu\IndexingApi\Api\Data\SyncResultInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SyncResultFactoryTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

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

        $this->implementationFqcn = SyncResult::class;
        $this->interfaceFqcn = SyncResultInterface::class;
        $this->constructorArgumentDefaults = [
            'isSuccess' => true,
            'code' => 200,
            'messages' => [],
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testCreate_ReturnsFalse_WhenFalseArgumentProvided(): void
    {
        $syncResultFactory = $this->objectManager->get(SyncResultFactory::class);
        /** @var SyncResult $syncResult */
        $syncResult = $syncResultFactory->create([
            'isSuccess' => false,
            'code' => 500,
            'messages' => [
                'Message 1',
                'Message 2',
            ],
        ]);
        $this->assertFalse(condition: $syncResult->isSuccess(), message: 'Is Success');
        $this->assertSame(expected: 500, actual: $syncResult->getCode());
        $this->assertTrue(condition: $syncResult->hasMessages(), message: 'Has Messages');
        $messages = $syncResult->getMessages();
        $this->assertCount(expectedCount: 2, haystack: $messages, message: 'Message Count');
        $this->assertContains(needle: 'Message 1', haystack: $messages);
        $this->assertContains(needle: 'Message 2', haystack: $messages);
    }

    public function testCreate_ReturnsTrue_WhenTrueArgumentProvided(): void
    {
        $syncResultFactory = $this->objectManager->get(SyncResultFactory::class);
        /** @var SyncResult $syncResult */
        $syncResult = $syncResultFactory->create([
            'isSuccess' => true,
            'code' => 200,
        ]);
        $this->assertTrue(condition: $syncResult->isSuccess());
        $this->assertSame(expected: 200, actual: $syncResult->getCode());
        $this->assertFalse(condition: $syncResult->hasMessages(), message: 'Has Messages');
        $this->assertCount(expectedCount: 0, haystack: $syncResult->getMessages(), message: 'Message Count');
    }
}
