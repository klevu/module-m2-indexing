<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\Indexing\Model\Update\Attribute;
use Klevu\Indexing\Service\AttributeUpdateOrchestratorService;
use Klevu\IndexingApi\Model\Update\AttributeInterface as AttributeUpdateInterface;
use Klevu\IndexingApi\Model\Update\AttributeInterfaceFactory as AttributeUpdateInterfaceFactory;
use Klevu\IndexingApi\Service\AttributeUpdateOrchestratorServiceInterface;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Klevu\Indexing\Service\AttributeUpdateOrchestratorService::class
 * @method AttributeUpdateOrchestratorServiceInterface instantiateTestObject(?array $arguments = null)
 * @method AttributeUpdateOrchestratorServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class AttributeUpdateOrchestratorServiceTest extends TestCase
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

        $this->implementationFqcn = AttributeUpdateOrchestratorService::class;
        $this->interfaceFqcn = AttributeUpdateOrchestratorServiceInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_LogsNoSuchEntityException_WhenRetrievingApiKeys(): void
    {
        $exceptionMessage = 'No Such Attribute Exception Message';

        $attributeUpdateFactory = $this->objectManager->get(AttributeUpdateInterfaceFactory::class);
        /** @var AttributeUpdateInterface $attributeUpdate */
        $attributeUpdate = $attributeUpdateFactory->create([
            'data' => [
                Attribute::ATTRIBUTE_TYPE => 'KLEVU_CATEGORY',
                Attribute::ATTRIBUTE_IDS => [1, 2, 3],
                Attribute::STORE_IDS => [1],
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
        $service->execute($attributeUpdate);
    }

    public function testExecute_ApiKeyLookUp_IsSkippedIfNoStoreIdsProvided(): void
    {
        $attributeUpdateFactory = $this->objectManager->get(AttributeUpdateInterfaceFactory::class);
        /** @var AttributeUpdateInterface $entityUpdate */
        $entityUpdate = $attributeUpdateFactory->create([
            'data' => [
                Attribute::ATTRIBUTE_TYPE => 'KLEVU_PRODUCT',
                Attribute::ATTRIBUTE_IDS => [1, 2, 3],
                Attribute::STORE_IDS => [],
            ],
        ]);
        $mockStoreManager = $this->getMockBuilder(StoreManagerInterface::class)
            ->getMock();
        $mockStoreManager->expects($this->never())
            ->method('getStores');

        $apiKeysProvider = $this->objectManager->create(ApiKeysProviderInterface::class, [
            'storeManager' => $mockStoreManager,
        ]);

        $service = $this->instantiateTestObject([
            'apiKeysProvider' => $apiKeysProvider,
        ]);
        $service->execute($entityUpdate);
    }
}
