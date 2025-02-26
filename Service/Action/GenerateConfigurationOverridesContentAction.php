<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Service\Action;

use Klevu\Indexing\Exception\AttributeMappingMissingException;
use Klevu\IndexingApi\Model\MagentoAttributeInterface;
use Klevu\IndexingApi\Service\Provider\AttributeProviderInterface;
use Klevu\PhpSDK\Model\Indexing\DataType;
use Klevu\Pipelines\Pipeline\ConfigurationBuilder;
use Klevu\Pipelines\Pipeline\ConfigurationElements;
use Klevu\PlatformPipelines\Api\ConfigurationOverridesBuilderInterface;
use Klevu\PlatformPipelines\Api\GenerateConfigurationOverridesContentActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Symfony\Component\Yaml\Yaml;

class GenerateConfigurationOverridesContentAction implements GenerateConfigurationOverridesContentActionInterface
{
    /**
     * @var AttributeProviderInterface|null
     */
    private readonly ?AttributeProviderInterface $attributesForConfigurationOverridesProvider;
    /**
     * @var ConfigurationBuilder
     */
    private readonly ConfigurationBuilder $configurationBuilder;
    /**
     * @var ConfigurationOverridesBuilderInterface
     */
    private readonly ConfigurationOverridesBuilderInterface $configurationOverridesBuilder;
    /**
     * @var string
     */
    private readonly string $entityType;
    /**
     * @var string
     */
    private readonly string $currentEntityExtractionAccessor;
    /**
     * @var array<string, string>
     */
    private readonly array $entitySubtypeToParentPathMap;
    /**
     * @var int
     */
    private readonly int $yamlExpandedNestingDepth;
    /**
     * @var int
     */
    private readonly int $yamlIndentationLevel;
    /**
     * @var string[]
     */
    private readonly array $permittedHtmlTags;

    /**
     * @param AttributeProviderInterface|null $attributesForConfigurationOverridesProvider
     * @param ConfigurationBuilder $pipelineConfigurationBuilder
     * @param ConfigurationOverridesBuilderInterface $configurationOverridesBuilder
     * @param string $entityType
     * @param string $currentEntityExtractionAccessor
     * @param string[] $entitySubtypeToParentPathMap
     * @param int $yamlExpandedNestingDepth
     * @param int $yamlIndentationLevel
     * @param string[] $permittedHtmlTags
     */
    public function __construct(
        ?AttributeProviderInterface $attributesForConfigurationOverridesProvider,
        ConfigurationBuilder $pipelineConfigurationBuilder,
        ConfigurationOverridesBuilderInterface $configurationOverridesBuilder,
        string $entityType,
        string $currentEntityExtractionAccessor,
        array $entitySubtypeToParentPathMap = [],
        int $yamlExpandedNestingDepth = 100,
        int $yamlIndentationLevel = 2,
        array $permittedHtmlTags = [
            'p', 'br', 'hr',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'strong', 'em',
            'ul', 'ol', 'li',
            'dl', 'dt', 'dd',
            'img',
            'sub', 'sup', 'small',
        ],
    ) {
        $this->attributesForConfigurationOverridesProvider = $attributesForConfigurationOverridesProvider;
        $this->configurationBuilder = $pipelineConfigurationBuilder;
        $this->configurationOverridesBuilder = $configurationOverridesBuilder;
        $this->entityType = $entityType;
        $this->currentEntityExtractionAccessor = $currentEntityExtractionAccessor;
        $this->entitySubtypeToParentPathMap = array_map('strval', $entitySubtypeToParentPathMap);
        $this->yamlExpandedNestingDepth = $yamlExpandedNestingDepth;
        $this->yamlIndentationLevel = $yamlIndentationLevel;
        $this->permittedHtmlTags = array_map('strval', $permittedHtmlTags);
    }

    /**
     * @return string
     */
    public function getEntityType(): string
    {
        return $this->entityType;
    }

    /**
     * @return string
     * @throws AttributeMappingMissingException
     * @throws NoSuchEntityException
     */
    public function execute(): string
    {
        foreach ($this->attributesForConfigurationOverridesProvider?->get() as $attribute) {
            // Note, the provider should filter these before reaching us; this is an additional check
            if (!$attribute->isIndexable()) {
                continue;
            }

            foreach ($attribute->getGenerateConfigurationForEntitySubtypes() as $entitySubtype) {
                $entitySubtypePath = $this->entitySubtypeToParentPathMap[$entitySubtype] ?? '';
                if (!$entitySubtypePath) {
                    continue;
                }

                $path = $this->injectStagesElements($entitySubtypePath)
                    . ConfigurationOverridesBuilderInterface::PATH_PARTS_SEPARATOR
                    . ConfigurationElements::ADD_STAGES->value
                    . ConfigurationOverridesBuilderInterface::PATH_PARTS_SEPARATOR
                    . $attribute->getKlevuAttributeName();

                $this->configurationOverridesBuilder->addConfigurationByPath(
                    path: $path,
                    configuration: $this->generateAttributeConfiguration(
                        entitySubtype: $entitySubtype,
                        attribute: $attribute,
                    ),
                );
            }
        }

        $configurationOverridesDefinition = $this->configurationOverridesBuilder->build();
        $configuration = $this->configurationBuilder->build(
            pipelineDefinition: $configurationOverridesDefinition,
        );

        $warningMessage = <<<'WARNING'
# WARNING: This file is autogenerated.
# Any changes will be reflected in the next applicable pipeline execution, without requiring recompilation
#   however, they will be lost when this file is regenerated
WARNING;

        return $warningMessage
            . PHP_EOL
            . Yaml::dump(
                input: $configuration,
                inline: $this->yamlExpandedNestingDepth,
                indent: $this->yamlIndentationLevel,
            );
    }

    /**
     * Add plugins to modify the extraction accessor used for specific attributes
     *
     * @param string $entitySubtype
     * @param MagentoAttributeInterface $attribute
     *
     * @return string
     */
    public function getExtractionAccessorForAttribute(
        string $entitySubtype, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        MagentoAttributeInterface $attribute,
    ): string {
        return $attribute->usesSourceModel()
            ? $this->currentEntityExtractionAccessor
            : sprintf(
                '%sget%s()',
                $this->currentEntityExtractionAccessor,
                str_replace('_', '', ucwords($attribute->getAttributeCode(), '_')),
            );
    }

    /**
     * Add plugins to modify transformations applied to specific attributes
     *
     * @param string $entitySubtype
     * @param MagentoAttributeInterface $attribute
     *
     * @return string[]
     */
    public function getPreValidationTransformationsForAttribute(
        string $entitySubtype, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        MagentoAttributeInterface $attribute,
    ): array {
        $transformations = [];
        if ($attribute->usesSourceModel()) {
            // Extraction performed by transformation as cannot pass args to extraction accessor
            $transformations[] = sprintf(
                'GetAttributeText("%s")',
                $attribute->getAttributeCode(),
            );
        }
        if (
            in_array(
                needle: $attribute->getKlevuAttributeType(),
                haystack: [DataType::MULTIVALUE, DataType::MULTIVALUE_NUMBER],
                strict: true)
        ) {
            $transformations[] = 'ToArray';
        }

        return $transformations;
    }

    /**
     * Add plugins to modify transformations applied to specific attributes
     *
     * @param string $entitySubtype
     * @param MagentoAttributeInterface $attribute
     *
     * @return string
     */
    public function getPostValidationTransformationsForAttribute(
        string $entitySubtype, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        MagentoAttributeInterface $attribute,
    ): string {
        $transformations = [];

        switch (true) {
            case $attribute->getKlevuAttributeType() === DataType::MULTIVALUE_NUMBER:
            case $attribute->getKlevuAttributeType() === DataType::NUMBER:
                // @TODO we should handle int as well, check db backend type
                $transformations[] = 'ToFloat';
                break;
            default:
                $transformations[] = 'ToString';
                if ($attribute->isHtmlAllowed()) {
                    $permittedHtmlTagsString = $this->permittedHtmlTags
                        ? sprintf('["%s"]', implode('", "', $this->permittedHtmlTags))
                        : 'null';
                    $transformations[] = sprintf(
                        'StripTags(%s, ["script"])',
                        $permittedHtmlTagsString,
                    );
                    $transformations[] = 'EscapeHtml';
                } else {
                    $transformations[] = 'StripTags(null, ["script"])';
                }
                $transformations[] = 'Trim';
                break;
        }
        if (
            in_array(
                needle: $attribute->getKlevuAttributeType(),
                haystack: [DataType::MULTIVALUE, DataType::MULTIVALUE_NUMBER],
                strict: true,
            )
        ) {
            $transformations[] = 'FilterCompare([$, "nempty"])';
        }

        return implode('|', $transformations);
    }

    /**
     * @param string $entitySubtype
     * @param MagentoAttributeInterface $attribute
     *
     * @return mixed[]
     */
    public function getValidationsForAttribute(
        string $entitySubtype, // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
        MagentoAttributeInterface $attribute,
    ): array {
        $validations = [];

        switch (true) {
            case $attribute->getKlevuAttributeType() === DataType::MULTIVALUE_NUMBER:
            case $attribute->getKlevuAttributeType() === DataType::MULTIVALUE:
                $validations[] = 'IsNotEqualTo([], true)';
             break;
            default:
                $validations[] = 'IsNotIn([null, ""], true)';
                break;
        }

        return $validations;
    }

    /**
     * Add plugins to modify behaviour for specific attributes / groups of attributes
     *
     * @param string $entitySubtype
     * @param MagentoAttributeInterface $attribute
     *
     * @return mixed[]
     */
    public function generateAttributeConfiguration(
        string $entitySubtype,
        MagentoAttributeInterface $attribute,
    ): array {
        return [
            ConfigurationElements::STAGES->value => [
                'processAttribute' => [
                    ConfigurationElements::PIPELINE->value => 'Pipeline\\Fallback',
                    ConfigurationElements::STAGES->value => [
                        'getData' => [
                            ConfigurationElements::STAGES->value => [
                                'extract' => $this->generateExtractionConfigurationWithoutLocales(
                                    entitySubtype: $entitySubtype,
                                    attribute: $attribute,
                                ),
                                'validate' => $this->generateValidationConfigurationWithoutLocales(
                                    entitySubtype: $entitySubtype,
                                    attribute: $attribute,
                                ),
                                'transform' => $this->generateTransformationConfigurationWithoutLocales(
                                    entitySubtype: $entitySubtype,
                                    attribute: $attribute,
                                ),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param string $stagesPath
     *
     * @return string
     */
    private function injectStagesElements(string $stagesPath): string
    {
        $pathParts = explode(
            separator: ConfigurationOverridesBuilderInterface::PATH_PARTS_SEPARATOR,
            string: $stagesPath,
        );

        $return = [];
        $skipNext = false;
        foreach ($pathParts as $pathPart) {
            if (ConfigurationElements::STAGES->value === $pathPart) {
                $skipNext = true;
                continue;
            }
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            $skipNext = false;

            $return[] = ConfigurationElements::STAGES->value;
            $return[] = $pathPart;
        }

        return implode(
            separator: ConfigurationOverridesBuilderInterface::PATH_PARTS_SEPARATOR,
            array: $return,
        );
    }

    /**
     * @param string $entitySubtype
     * @param MagentoAttributeInterface $attribute
     *
     * @return mixed[]
     */
    private function generateExtractionConfigurationWithoutLocales(
        string $entitySubtype,
        MagentoAttributeInterface $attribute,
    ): array {
        return [
            ConfigurationElements::PIPELINE->value => 'Stage\\Extract',
            ConfigurationElements::ARGS->value => [
                'extraction' => $this->getExtractionAccessorForAttribute(
                    entitySubtype: $entitySubtype,
                    attribute: $attribute,
                ),
                'transformations' => $this->getPreValidationTransformationsForAttribute(
                    entitySubtype: $entitySubtype,
                    attribute: $attribute,
                ),
            ],
        ];
    }

    /**
     * @param string $entitySubtype
     * @param MagentoAttributeInterface $attribute
     *
     * @return mixed[]
     */
    private function generateValidationConfigurationWithoutLocales(
        string $entitySubtype,
        MagentoAttributeInterface $attribute,
    ): array {
        return [
            ConfigurationElements::PIPELINE->value => 'Stage\\Validate',
            ConfigurationElements::ARGS->value => [
                'validation' => $this->getValidationsForAttribute(
                    entitySubtype: $entitySubtype,
                    attribute: $attribute,
                ),
            ],
        ];
    }

    /**
     * @param string $entitySubtype
     * @param MagentoAttributeInterface $attribute
     *
     * @return mixed[]
     */
    private function generateTransformationConfigurationWithoutLocales(
        string $entitySubtype,
        MagentoAttributeInterface $attribute,
    ): array {
        return [
            ConfigurationElements::PIPELINE->value => 'Stage\\Transform',
            ConfigurationElements::ARGS->value => [
                'transformation' => $this->getPostValidationTransformationsForAttribute(
                    entitySubtype: $entitySubtype,
                    attribute: $attribute,
                ),
            ],
        ];
    }
}
