<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\Indexing\Model\Update\Entity;
use Klevu\Indexing\Model\Update\Entity as EntityUpdate;
use Klevu\Indexing\Service\EntityUpdateOrchestratorService;
use Klevu\IndexingApi\Model\Update\EntityInterface as EntityUpdateInterface;
use Klevu\IndexingApi\Model\Update\EntityInterfaceFactory as EntityUpdateInterfaceFactory;
use Klevu\IndexingApi\Service\EntityUpdateOrchestratorServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Service\EntityUpdateOrchestratorService::class
 * @method EntityUpdateOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityUpdateOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityUpdateOrchestratorServiceTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

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

        $this->implementationFqcn = EntityUpdateOrchestratorService::class;
        $this->interfaceFqcn = EntityUpdateOrchestratorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_LogsNoSuchEntityException_WhenRetrievingApiKeys(): void
    {
        $exceptionMessage = 'No Such Entity Exception Message';

        $entityUpdateFactory = $this->objectManager->get(EntityUpdateInterfaceFactory::class);
        /** @var EntityUpdateInterface $entityUpdate */
        $entityUpdate = $entityUpdateFactory->create([
            'data' => [
                Entity::ENTITY_TYPE => 'KLEVU_CMS',
                Entity::ENTITY_IDS => [1, 2, 3],
                Entity::STORE_IDS => [1],
                EntityUpdate::ENTITY_SUBTYPES => [],
            ],
        ]);

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('error')
            ->with(
                'Method: {method} - Error: {message}',
                [
                    'method' => 'Klevu\Configuration\Service\Provider\ApiKeysProvider::get',
                    'message' => $exceptionMessage,
                ],
            );
        $mockApiKeyProvider = $this->getMockBuilder(ApiKeyProviderInterface::class)
            ->getMock();
        $mockApiKeyProvider->expects($this->once())
            ->method('get')
            ->willThrowException(new NoSuchEntityException(__($exceptionMessage)));

        $apiKeysProvider = $this->objectManager->create(ApiKeysProviderInterface::class, [
            'logger' => $mockLogger,
            'apiKeyProvider' => $mockApiKeyProvider,
        ]);

        $service = $this->instantiateTestObject([
            'apiKeysProvider' => $apiKeysProvider,
        ]);
        $service->execute($entityUpdate);
    }
}
