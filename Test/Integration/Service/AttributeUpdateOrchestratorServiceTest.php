<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Configuration\Service\Provider\ApiKeyProviderInterface;
use Klevu\Configuration\Service\Provider\ApiKeysProviderInterface;
use Klevu\Configuration\Service\Provider\ScopeProviderInterface;
use Klevu\Indexing\Model\Update\Attribute;
use Klevu\Indexing\Service\AttributeUpdateOrchestratorService;
use Klevu\IndexingApi\Model\Update\AttributeInterface as AttributeUpdateInterface;
use Klevu\IndexingApi\Model\Update\AttributeInterfaceFactory as AttributeUpdateInterfaceFactory;
use Klevu\IndexingApi\Service\AttributeUpdateOrchestratorServiceInterface;
use Klevu\TestFixtures\Store\StoreFixturesPool;
use Klevu\TestFixtures\Store\StoreTrait;
use Klevu\TestFixtures\Traits\AttributeApiCallTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\SetAuthKeysTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
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
    use AttributeApiCallTrait;
    use ObjectInstantiationTrait;
    use SetAuthKeysTrait;
    use StoreTrait;
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

        $this->storeFixturesPool = $this->objectManager->get(StoreFixturesPool::class);

        $this->mockSdkAttributeGetApiCall();
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->storeFixturesPool->rollback();

        $this->removeSharedApiInstances();
    }

    /**
     * @magentoAppIsolation enabled
     */
    public function testExecute_LogsNoSuchEntityException_WhenRetrievingApiKeys(): void
    {
        $this->createStore();
        $storeFixture = $this->storeFixturesPool->get('test_store');
        $scopeProvider = $this->objectManager->get(ScopeProviderInterface::class);
        $scopeProvider->setCurrentScope($storeFixture->get());
        $this->setAuthKeys(
            scopeProvider: $scopeProvider,
            jsApiKey: 'klevu-js-key',
            restAuthKey: 'klevu-rest-key',
        );

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
}
