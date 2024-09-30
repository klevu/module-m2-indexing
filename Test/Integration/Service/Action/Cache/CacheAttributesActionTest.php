<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action\Cache;

use Klevu\Indexing\Cache\Attributes as AttributesCache;
use Klevu\Indexing\Service\Action\Cache\CacheAttributesAction;
use Klevu\IndexingApi\Service\Action\Cache\CacheAttributesActionInterface;
use Klevu\IndexingApi\Service\Provider\Cache\AttributesCacheKeyProviderInterface;
use Klevu\PhpSDK\Model\Indexing\Attribute;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Traits\AttributesIteratorTrait;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Klevu\TestFixtures\Traits\TestInterfacePreferenceTrait;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers CacheAttributesAction::class
 * @method CacheAttributesActionInterface instantiateTestObject(?array $arguments = null)
 * @method CacheAttributesActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class CacheAttributesActionTest extends TestCase
{
    use AttributesIteratorTrait;
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;
    use TestInterfacePreferenceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line
    /**
     * @var SerializerInterface|null
     */
    private ?SerializerInterface $serializer = null;
    /**
     * @var AttributesCacheKeyProviderInterface|null
     */
    private ?AttributesCacheKeyProviderInterface $attributesCacheKeyProvider = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->implementationFqcn = CacheAttributesAction::class;
        $this->interfaceFqcn = CacheAttributesActionInterface::class;
        $this->objectManager = Bootstrap::getObjectManager();

        $this->serializer = $this->objectManager->get(SerializerInterface::class);
        $this->attributesCacheKeyProvider = $this->objectManager->get(AttributesCacheKeyProviderInterface::class);
    }

    public function testExecute_SavesDataAndInfoLogged(): void
    {
        $apiKey = 'klevu-js-api-key';
        $attributeIterator = $this->createAttributeIterator(
            attributes: [
                $this->objectManager->create(
                    type: Attribute::class,
                    arguments: [
                        'attributeName' => 'my_custom_attribute',
                        'datatype' => DataType::STRING->value,
                        'label' => [
                            'default' => 'My Custom Attribute',
                            'en-GB' => 'A Custom Attribute',
                            'en-AU' => 'Custom Attribute',
                        ],
                        'searchable' => false,
                        'filterable' => true,
                        'returnable' => false,
                        'aliases' => [],
                        'immutable' => true,
                    ],
                ),
            ],
            includeStandardAttributes: false,
        );

        $mockCache = $this->getMockBuilder(CacheInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockCache->expects($this->once())
            ->method('save')
            ->with(
                $this->serializer->serialize(data: [[
                    'attributeName' => 'my_custom_attribute',
                    'datatype' => DataType::STRING->value,
                    'label' => [
                        'default' => 'My Custom Attribute',
                        'en-GB' => 'A Custom Attribute',
                        'en-AU' => 'Custom Attribute',
                    ],
                    'searchable' => false,
                    'filterable' => true,
                    'returnable' => false,
                    'abbreviate' => false,
                    'rangeable' => false,
                    'aliases' => [],
                    'immutable' => true,
                ]]),
                $this->attributesCacheKeyProvider->get(apiKey: $apiKey),
                [AttributesCache::CACHE_TAG],
                CacheAttributesAction::CACHE_LIFETIME,
        );

        $mockLogger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockLogger->expects($this->once())
            ->method('info')
            ->with(
                'Method: {method}, Info: {message}',
                [
                    'method' => 'Klevu\Indexing\Service\Action\Cache\CacheAttributesAction::execute',
                    'message' => __(
                        'Attributes SDK GET call cached for API key: %1',
                        $apiKey,
                    ),
                ],
            );

        $action = $this->instantiateTestObject([
            'cache' => $mockCache,
            'logger' => $mockLogger,
        ]);
        $action->execute(attributeIterator: $attributeIterator, apiKey: $apiKey);
    }
}
