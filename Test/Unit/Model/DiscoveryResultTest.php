<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Model;

use Klevu\Indexing\Model\DiscoveryResult;
use PHPUnit\Framework\TestCase;

class DiscoveryResultTest extends TestCase
{
    public function testIsSuccess_ReturnsFalse_WhenSetToFalse(): void
    {
        $syncResult = new DiscoveryResult(
            isSuccess: false,
            messages: [
                'An Error Occurred',
                'And Another',
            ],
        );

        $this->assertFalse(condition: $syncResult->isSuccess(), message: 'Is Success');
        $this->assertTrue(condition: $syncResult->hasMessages(), message: 'Has Messages');
        $messages = $syncResult->getMessages();
        $this->assertContains(needle: 'An Error Occurred', haystack: $messages, message: 'Has Messages');
        $this->assertContains(needle: 'And Another', haystack: $messages, message: 'Has Messages');
    }

    public function testIsSuccess_ReturnsTrue_WhenSetToTrue(): void
    {
        $syncResult = new DiscoveryResult(true);

        $this->assertTrue(condition: $syncResult->isSuccess(), message: 'Is Success');
        $this->assertFalse(condition: $syncResult->hasMessages(), message: 'Has Messages');
    }
}
