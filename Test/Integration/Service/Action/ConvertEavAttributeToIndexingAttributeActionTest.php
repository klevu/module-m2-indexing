<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service\Action;

use Klevu\Configuration\Service\Provider\ApiKeyProvider;
use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\Indexing\Service\Action\ConvertEavAttributeToIndexingAttributeAction;
use Klevu\IndexingApi\Api\ConvertEavAttributeToIndexingAttributeActionInterface;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\Determiner\IsAttributeIndexableDeterminerInterface;
use Klevu\IndexingApi\Service\Mapper\AttributeTypeMapperServiceInterface;
use Klevu\IndexingApi\Service\Mapper\MagentoToKlevuAttributeMapperInterface;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Eav\Model\Entity\Attribute as EavAttribute;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TddWizard\Fixtures\Core\ConfigFixture;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @covers \Klevu\Indexing\Service\Action\ConvertEavAttributeToIndexingAttributeAction::class
 * @method ConvertEavAttributeToIndexingAttributeActionInterface instantiateTestObject(?array $arguments = null)
 * @method ConvertEavAttributeToIndexingAttributeActionInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
// phpcs:enable Generic.Files.LineLength.TooLong
class ConvertEavAttributeToIndexingAttributeActionTest extends TestCase
{
    use ObjectInstantiationTrait;
    use TestImplementsInterfaceTrait;

    /**
     * @var ObjectManagerInterface|null
     */
    private ?ObjectManagerInterface $objectManager = null; // @phpstan-ignore-line Used by traits
    /**
     * @var StoreManagerInterface|null
     */
    private ?StoreManagerInterface $storeManager = null;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = ObjectManager::getInstance();

        $this->implementationFqcn = ConvertEavAttributeToIndexingAttributeAction::class;
        $this->interfaceFqcn = ConvertEavAttributeToIndexingAttributeActionInterface::class;

        $this->storeManager = $this->objectManager->get(StoreManagerInterface::class);
    }

    public function testExecute_WithoutStore(): void
    {
        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $result = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'PHPUNIT_TEST',
            attribute: $this->getMockEavAttribute(
                attributeData: [
                    'attribute_id' => 123,
                    'attribute_code' => 'klevu_test_attribute',
                    'is_global' => 1,
                    'is_html_allowed_on_front' => 1,
                    'klevu_is_indexable' => 1,
                    'klevu_generate_config_for' => [
                        'simple',
                    ],
                ],
            ),
            store: null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $result,
        );

        $this->assertSame(
            expected: 123,
            actual: $result->getAttributeId(),
            message: 'attribute_id',
        );
        $this->assertSame(
            expected: 'klevu_test_attribute',
            actual: $result->getAttributeCode(),
            message: 'attribute_code',
        );
        $this->assertSame(
            expected: '',
            actual: $result->getApiKey(),
            message: 'api_key',
        );
        $this->assertSame(
            expected: true,
            actual: $result->isIndexable(),
            message: 'is_indexable',
        );
        $this->assertSame(
            expected: 'klevu_test_attribute',
            actual: $result->getKlevuAttributeName(),
            message: 'klevu_attribute_name',
        );
        $this->assertSame(
            expected: [
                'simple',
            ],
            actual: $result->getGenerateConfigurationForEntitySubtypes(),
            message: 'generate_configuration_for_entity_subtypes',
        );
        $this->assertEquals(
            expected: null,
            actual: $result->getKlevuAttributeType(),
            message: 'klevu_attribute_type',
        );
        $this->assertSame(
            expected: true,
            actual: $result->isGlobal(),
            message: 'is_global',
        );
        $this->assertSame(
            expected: false,
            actual: $result->usesSourceModel(),
            message: 'uses_source_model',
        );
        $this->assertSame(
            expected: true,
            actual: $result->isHtmlAllowed(),
            message: 'is_html_allowed',
        );
        $this->assertSame(
            expected: false,
            actual: $result->allowsMultipleValues(),
            message: 'allows_multiple_values',
        );
    }

    public function testExecute_WithStore(): void
    {
        ConfigFixture::setForStore(
            path: ApiKeyProvider::CONFIG_XML_PATH_JS_API_KEY,
            value: 'klevu-1234567890',
            storeCode: 'default',
        );

        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $result = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'PHPUNIT_TEST',
            attribute: $this->getMockEavAttribute(
                attributeData: [
                    'attribute_id' => 123,
                    'attribute_code' => 'klevu_test_attribute',
                    'is_global' => 1,
                    'is_html_allowed_on_front' => 1,
                    'klevu_is_indexable' => 1,
                    'klevu_generate_config_for' => [
                        'bundle',
                    ],
                ],
            ),
            store: $this->storeManager->getStore('default'),
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $result,
        );

        $this->assertSame(
            expected: 123,
            actual: $result->getAttributeId(),
            message: 'attribute_id',
        );
        $this->assertSame(
            expected: 'klevu_test_attribute',
            actual: $result->getAttributeCode(),
            message: 'attribute_code',
        );
        $this->assertSame(
            expected: 'klevu-1234567890',
            actual: $result->getApiKey(),
            message: 'api_key',
        );
        $this->assertSame(
            // klevu_is_indexable only applies where store is not passed as determiner requires a store
            expected: true,
            actual: $result->isIndexable(),
            message: 'is_indexable',
        );
        $this->assertSame(
            expected: 'klevu_test_attribute',
            actual: $result->getKlevuAttributeName(),
            message: 'klevu_attribute_name',
        );
        $this->assertSame(
            expected: [
                'bundle',
            ],
            actual: $result->getGenerateConfigurationForEntitySubtypes(),
            message: 'generate_configuration_for_entity_subtypes',
        );
        $this->assertEquals(
            expected: null,
            actual: $result->getKlevuAttributeType(),
            message: 'klevu_attribute_type',
        );
        $this->assertSame(
            expected: true,
            actual: $result->isGlobal(),
            message: 'is_global',
        );
        $this->assertSame(
            expected: false,
            actual: $result->usesSourceModel(),
            message: 'uses_source_model',
        );
        $this->assertSame(
            expected: true,
            actual: $result->isHtmlAllowed(),
            message: 'is_html_allowed',
        );
        $this->assertSame(
            expected: false,
            actual: $result->allowsMultipleValues(),
            message: 'allows_multiple_values',
        );
    }

    public function testExecute_GenerateConfigurationForEntitySubtypes(): void
    {
        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $result = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'PHPUNIT_TEST',
            attribute: $this->getMockEavAttribute(
                attributeData: [
                    'attribute_id' => 123,
                    'attribute_code' => 'klevu_test_attribute',
                    'is_global' => 1,
                    'is_html_allowed_on_front' => 1,
                    'klevu_is_indexable' => 1,
                    'klevu_generate_config_for' => 'simple,virtual,downloadable',
                ],
            ),
            store: null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $result,
        );

        $this->assertSame(
            expected: [
                'simple',
                'virtual',
                'downloadable',
            ],
            actual: $result->getGenerateConfigurationForEntitySubtypes(),
            message: 'generate_configuration_for_entity_subtypes',
        );
    }

    /**
     * @testWith [false, false, null, false]
     *           [false, false, "default", false]
     *           [false, true, null, true]
     *           [false, true, "default", false]
     *           [true, false, null, false]
     *           [true, false, "default", true]
     *           [true, true, null, true]
     *           [true, true, "default", true]
     *
     * @param bool $isIndexableReturn
     * @param bool $attributeIsIndexable
     * @param string|null $storeCode
     * @param bool $expectedResult
     *
     * @return void
     * @throws AttributeMappingMissingException
     * @throws NoSuchEntityException
     */
    public function testExecute_IsIndexable(
        bool $isIndexableReturn,
        bool $attributeIsIndexable,
        ?string $storeCode,
        bool $expectedResult,
    ): void {
        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject([
            'isIndexableDeterminers' => [
                'PHPUNIT_TEST' => $this->getMockIsIndexableDeterminer($isIndexableReturn),
            ],
        ]);

        $result = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'PHPUNIT_TEST',
            attribute: $this->getMockEavAttribute(
                attributeData: [
                    'attribute_id' => 123,
                    'attribute_code' => 'klevu_test_attribute',
                    'is_global' => 1,
                    'is_html_allowed_on_front' => 1,
                    'klevu_is_indexable' => $attributeIsIndexable,
                    'klevu_generate_config_for' => 'bundle,grouped,configurable',
                ],
            ),
            store: $storeCode
                ? $this->storeManager->getStore($storeCode)
                : null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $result,
        );

        $this->assertSame(
            expected: $expectedResult,
            actual: $result->isIndexable(),
        );
    }

    /**
     * @testWith ["foo", "", null, "foo"]
     *           ["foo", "", "default", "foo"]
     *           ["foo", "foo", null, "foo"]
     *           ["foo", "foo", "default", "foo"]
     *           ["foo", "bar", null, "bar"]
     *           ["foo", "bar", "default", "bar"]
     *
     * @param string $attributeCode
     * @param string $attributeMapperReturn
     * @param string|null $storeCode
     * @param string $expectedResult
     *
     * @return void
     * @throws AttributeMappingMissingException
     * @throws NoSuchEntityException
     */
    public function testExecute_KlevuAttributeName(
        string $attributeCode,
        string $attributeMapperReturn,
        ?string $storeCode,
        string $expectedResult,
    ): void {
        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject([
            'attributeMappers' => [
                'PHPUNIT_TEST' => $this->getMockAttributeMapper($attributeMapperReturn),
            ],
        ]);

        $result = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'PHPUNIT_TEST',
            attribute: $this->getMockEavAttribute(
                attributeData: [
                    'attribute_id' => 123,
                    'attribute_code' => $attributeCode,
                    'is_global' => 1,
                    'is_html_allowed_on_front' => 1,
                    'klevu_is_indexable' => 1,
                    'klevu_generate_config_for' => 'bundle,grouped,configurable',
                ],
            ),
            store: $storeCode
                ? $this->storeManager->getStore($storeCode)
                : null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $result,
        );

        $this->assertSame(
            expected: $expectedResult,
            actual: $result->getKlevuAttributeName(),
        );
    }

    /**
     * @testWith ["STRING", null]
     *           ["STRING", "default"]
     *           ["NUMBER", null]
     *           ["NUMBER", "default"]
     *           ["DATETIME", null]
     *           ["DATETIME", "default"]
     *           ["MULTIVALUE", null]
     *           ["MULTIVALUE", "default"]
     *           ["MULTIVALUE_NUMBER", null]
     *           ["MULTIVALUE_NUMBER", "default"]
     *           ["JSON", null]
     *           ["JSON", "default"]
     *           ["BOOLEAN", null]
     *           ["BOOLEAN", "default"]
     *
     * @param string|null $attributeTypeMapperReturn
     * @param string|null $storeCode
     *
     * @return void
     * @throws AttributeMappingMissingException
     * @throws NoSuchEntityException
     */
    public function testExecute_KlevuAttributeType(
        ?string $attributeTypeMapperReturn,
        ?string $storeCode,
    ): void {
        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject([
            'attributeTypeMappers' => [
                'PHPUNIT_TEST' => $this->getMockAttributeTypeMapper($attributeTypeMapperReturn),
            ],
        ]);

        $result = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'PHPUNIT_TEST',
            attribute: $this->getMockEavAttribute(
                attributeData: [
                    'attribute_id' => 123,
                    'attribute_code' => 'klevu_test_attribute',
                    'is_global' => 1,
                    'is_html_allowed_on_front' => 1,
                    'klevu_is_indexable' => 1,
                    'klevu_generate_config_for' => 'bundle,grouped,configurable',
                ],
            ),
            store: $storeCode
                ? $this->storeManager->getStore($storeCode)
                : null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $result,
        );

        $this->assertSame(
            expected: $attributeTypeMapperReturn,
            actual: $result->getKlevuAttributeType()?->value,
        );
    }

    /**
     * @testWith [0, null, false]
     *           [0, "default", false]
     *           [1, null, true]
     *           [1, "default", true]
     *           [2, null, false]
     *           [2, "default", false]
     *
     * @param int $scope
     * @param string|null $storeCode
     * @param bool $expectedIsGlobal
     *
     * @return void
     * @throws AttributeMappingMissingException
     * @throws NoSuchEntityException
     */
    public function testExecute_IsGlobal(
        int $scope,
        ?string $storeCode,
        bool $expectedIsGlobal,
    ): void {
        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $result = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'PHPUNIT_TEST',
            attribute: $this->getMockEavAttribute(
                attributeData: [
                    'attribute_id' => 123,
                    'attribute_code' => 'klevu_test_attribute',
                    'is_global' => $scope,
                    'is_html_allowed_on_front' => 1,
                    'klevu_is_indexable' => 1,
                    'klevu_generate_config_for' => [
                        'simple',
                    ],
                ],
            ),
            store: $storeCode
                ? $this->storeManager->getStore($storeCode)
                : null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $result,
        );

        $this->assertSame(
            expected: $expectedIsGlobal,
            actual: $result->isGlobal(),
        );
    }

    /**
     * @return mixed[][]
     */
    public static function dataProvider_testExecute_UsesSourceModel(): array
    {
        return [
            [null, null, 'select', null, false],
            [null, null, 'select', 'default', false],
            [null, null, 'multiselect', null, true],
            [null, null, 'multiselect', 'default', true],
            [
                'Magento\Customer\Model\Customer\Attribute\Backend\Website',
                'Magento\Customer\Model\Customer\Attribute\Source\Website ',
                'select',
                null,
                true,
            ],
            [
                'Magento\Customer\Model\Customer\Attribute\Backend\Store',
                'Magento\Customer\Model\Customer\Attribute\Source\Store',
                'select',
                null,
                true,
            ],
            [
                'Magento\Eav\Model\Entity\Attribute\Backend\Datetime',
                null,
                'select',
                null,
                false,
                ],
            [
                'Magento\Customer\Model\Customer\Attribute\Backend\Password',
                null,
                'select',
                null,
                false,
                ],
            [
                'Magento\Customer\Model\Customer\Attribute\Backend\Billing',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Customer\Model\Customer\Attribute\Backend\Shipping',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Customer\Model\Attribute\Backend\Data\Boolean',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Eav\Model\Entity\Attribute\Backend\DefaultBackend',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Customer\Model\ResourceModel\Address\Attribute\Backend\Region',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Catalog\Model\Category\Attribute\Backend\Image',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Catalog\Model\Attribute\Backend\Startdate',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Catalog\Model\Attribute\Backend\Customlayoutupdate',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Catalog\Model\Category\Attribute\Backend\Sortby',
                'Magento\Catalog\Model\Category\Attribute\Source\Sortby',
                'select',
                null,
                true,
            ],
            [
                'Magento\Catalog\Model\Product\Attribute\Backend\Sku',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Catalog\Model\Product\Attribute\Backend\Price',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Catalog\Model\Product\Attribute\Backend\Weight',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Catalog\Model\Product\Attribute\Backend\Tierprice',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Catalog\Model\Product\Attribute\Backend\Category',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Catalog\Model\Product\Attribute\Backend\Stock',
                'Magento\CatalogInventory\Model\Source\Stock',
                'select',
                null,
                true,
            ],
            [
                'Magento\Catalog\Model\Product\Attribute\Backend\LayoutUpdate',
                'Magento\Catalog\Model\Product\Attribute\Source\LayoutUpdate',
                'select',
                null,
                true,
            ],
            [
                'Magento\Catalog\Model\Category\Attribute\Backend\LayoutUpdate',
                'Magento\Catalog\Model\Category\Attribute\Source\LayoutUpdate',
                'select',
                null,
                true,
            ],
            [
                'Magento\Catalog\Model\Product\Attribute\Backend\Boolean',
                'Magento\Msrp\Model\Product\Attribute\Source\Type\Price',
                'select',
                null,
                true,
            ],
            [
                'Magento\Catalog\Model\Product\Attribute\Backend\Boolean',
                'Magento\Catalog\Model\Product\Attribute\Source\Boolean',
                'select',
                null,
                true,
            ],
            [
                'Magento\GiftCard\Model\Attribute\Backend\Giftcard\Amount',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\TargetRule\Model\Catalog\Product\Attribute\Backend\Rule',
                null,
                'select',
                null,
                false,
            ],
            [
                'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                null,
                'select',
                null,
                true,
            ],
            [
                'Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend',
                null,
                'select',
                'default',
                true,
            ],
            [
                null,
                'Magento\Eav\Model\Entity\Attribute\Source\Table',
                'select',
                null,
                true,
            ],
            [
                null,
                'Magento\Eav\Model\Entity\Attribute\Source\Table',
                'select',
                'default',
                true,
            ],
            [
                null,
                'Magento\Customer\Model\Customer\Attribute\Source\Group',
                'select',
                null,
                true,
            ],
            [
                null,
                'Magento\Catalog\Model\Category\Attribute\Source\Mode',
                'select',
                null,
                true,
            ],
            [
                null,
                'Magento\Catalog\Model\Category\Attribute\Source\Page',
                'select',
                null,
                true,
            ],
            [
                null,
                'Magento\Catalog\Model\Product\Attribute\Source\Status',
                'select',
                null,
                true,
            ],
            [
                null,
                'Magento\Catalog\Model\Product\Visibility',
                'select',
                null,
                true,
            ],
            [
                null,
                'Magento\Catalog\Model\Product\Attribute\Source\Layout',
                'select',
                null,
                true,
            ],
            [
                null,
                'Magento\GiftCard\Model\Source\Open',
                'select',
                null,
                true,
            ],
            [
                null,
                'Magento\GiftCard\Model\Source\Type',
                'select',
                null,
                true,
            ],
        ];
    }

    /**
     * @dataProvider dataProvider_testExecute_UsesSourceModel
     *
     * @param string|null $backendModel
     * @param string|null $sourceModel
     * @param string $frontendInput
     * @param string|null $storeCode
     * @param bool $expectedUsesSourceModel
     *
     * @return void
     * @throws AttributeMappingMissingException
     * @throws NoSuchEntityException
     *
     * @group wipm
     */
    public function testExecute_UsesSourceModel(
        ?string $backendModel,
        ?string $sourceModel,
        string $frontendInput,
        ?string $storeCode,
        bool $expectedUsesSourceModel,
    ): void {
        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $result = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'PHPUNIT_TEST',
            attribute: $this->getMockEavAttribute(
                attributeData: [
                    'attribute_id' => 123,
                    'attribute_code' => 'klevu_test_attribute',
                    'is_global' => 1,
                    'is_html_allowed_on_front' => 1,
                    'backend_model' => $backendModel,
                    'source_model' => $sourceModel,
                    'frontend_input' => $frontendInput,
                    'klevu_is_indexable' => 1,
                    'klevu_generate_config_for' => [
                        'simple',
                    ],
                ],
            ),
            store: $storeCode
                ? $this->storeManager->getStore($storeCode)
                : null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $result,
        );

        $this->assertSame(
            expected: $expectedUsesSourceModel,
            actual: $result->usesSourceModel(),
        );
    }

    /**
     * @testWith [null, null, false]
     *           [null, "default", false]
     *           ["boolean", null, false]
     *           ["date", null, false]
     *           ["gallery", null, false]
     *           ["hidden", null, false]
     *           ["image", null, false]
     *           ["media_image", null, false]
     *           ["multiline", null, false]
     *           ["multiselect", null, true]
     *           ["multiselect", "default", true]
     *           ["price", null, false]
     *           ["select", null, false]
     *           ["text", null, false]
     *           ["textarea", null, false]
     *           ["weight", null, false]
     *
     * @param string|null $frontendInput
     * @param string|null $storeCode
     * @param bool $expectedAllowsMultipleValues
     *
     * @return void
     * @throws AttributeMappingMissingException
     * @throws NoSuchEntityException
     */
    public function testExecute_AllowsMultipleValues(
        ?string $frontendInput,
        ?string $storeCode,
        bool $expectedAllowsMultipleValues,
    ): void {
        $convertEavAttributeToIndexingAttributeAction = $this->instantiateTestObject();

        $result = $convertEavAttributeToIndexingAttributeAction->execute(
            entityType: 'PHPUNIT_TEST',
            attribute: $this->getMockEavAttribute(
                attributeData: [
                    'attribute_id' => 123,
                    'attribute_code' => 'klevu_test_attribute',
                    'is_global' => 1,
                    'is_html_allowed_on_front' => 1,
                    'frontend_input' => $frontendInput,
                    'klevu_is_indexable' => 1,
                    'klevu_generate_config_for' => [
                        'simple',
                    ],
                ],
            ),
            store: $storeCode
                ? $this->storeManager->getStore($storeCode)
                : null,
        );

        $this->assertInstanceOf(
            expected: MagentoAttributeInterface::class,
            actual: $result,
        );

        $this->assertSame(
            expected: $expectedAllowsMultipleValues,
            actual: $result->allowsMultipleValues(),
        );
    }

    /**
     * @param mixed[] $attributeData
     *
     * @return MockObject&AttributeInterface
     */
    private function getMockEavAttribute(array $attributeData): MockObject
    {
        $mockEavAttribute = $this->getMockBuilder(EavAttribute::class)
            ->disableOriginalConstructor()
            ->getMock();

        foreach ($attributeData as $attributeName => $value) {
            $methodName = 'get' . str_replace(
                    search: '_',
                    replace: '',
                    subject: ucwords($attributeName, '_'),
                );
            if (method_exists($mockEavAttribute, $methodName)) {
                $mockEavAttribute->method($methodName)
                    ->willReturn($value);
            }
        }
        $mockEavAttribute->method('getData')
            ->willReturnCallback(
                static function (?string $key) use ($attributeData): mixed {
                    if (null === $key) {
                        return $attributeData;
                    }

                    return $attributeData[$key] ?? null;
                },
            );
        $mockEavAttribute->method('getDataUsingMethod')
            ->willReturnCallback(
                static function (?string $key) use ($attributeData): mixed {
                    if (null === $key) {
                        return $attributeData;
                    }

                    return $attributeData[$key] ?? null;
                },
            );

        return $mockEavAttribute;
    }

    /**
     * @param bool $isIndexable
     *
     * @return MockObject&IsAttributeIndexableDeterminerInterface
     */
    private function getMockIsIndexableDeterminer(bool $isIndexable): MockObject
    {
        $mockIsIndexableDeterminer = $this->getMockBuilder(IsAttributeIndexableDeterminerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockIsIndexableDeterminer->method('execute')
            ->willReturn($isIndexable);

        return $mockIsIndexableDeterminer;
    }

    /**
     * @param string|null $mappedAttributeName
     *
     * @return MockObject&MagentoToKlevuAttributeMapperInterface
     */
    private function getMockAttributeMapper(?string $mappedAttributeName): MockObject
    {
        $mockAttributeMapper = $this->getMockBuilder(MagentoToKlevuAttributeMapperInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockAttributeMapper->method('get')
            ->willReturn($mappedAttributeName);

        return $mockAttributeMapper;
    }

    /**
     * @param string $mappedAttributeTypeKey
     *
     * @return MockObject&AttributeTypeMapperServiceInterface
     */
    private function getMockAttributeTypeMapper(string $mappedAttributeTypeKey): MockObject
    {
        $mockAttributeTypeMapper = $this->getMockBuilder(AttributeTypeMapperServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockAttributeTypeMapper->method('execute')
            ->willReturn(
                value: DataType::from($mappedAttributeTypeKey),
            );

        return $mockAttributeTypeMapper;
    }
}
