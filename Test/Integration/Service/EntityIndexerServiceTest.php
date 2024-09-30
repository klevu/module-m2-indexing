<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Indexing\Test\Integration\Service;

use Klevu\Indexing\Service\EntityIndexerService;
use Klevu\IndexingApi\Model\Source\IndexerResultStatuses;
use Klevu\IndexingApi\Service\EntityIndexerServiceInterface;
use Klevu\IndexingApi\Service\Provider\Sync\EntityIndexingRecordProviderInterface;
use Klevu\Pipelines\Exception\ExtractionException;
use Klevu\Pipelines\Exception\Pipeline\InvalidPipelineConfigurationException;
use Klevu\Pipelines\Exception\Pipeline\StageException;
use Klevu\Pipelines\Exception\TransformationException;
use Klevu\Pipelines\Exception\ValidationException;
use Klevu\Pipelines\Pipeline\PipelineBuilderInterface;
use Klevu\Pipelines\Pipeline\PipelineInterface;
use Klevu\PlatformPipelines\Api\PipelineConfigurationOverridesFilepathsProviderInterface;
use Klevu\PlatformPipelines\Api\PipelineConfigurationProviderInterface;
use Klevu\PlatformPipelines\Pipeline\PipelineBuilder;
use Klevu\TestFixtures\Traits\ObjectInstantiationTrait;
use Klevu\TestFixtures\Traits\TestImplementsInterfaceTrait;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers EntityIndexerService
 * @method EntityIndexerServiceInterface instantiateTestObject(?array $arguments = null)
 * @method EntityIndexerServiceInterface instantiateTestObjectFromInterface(?array $arguments = null)
 */
class EntityIndexerServiceTest extends TestCase
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

        $mockEntityIndexingRecordProvider = $this->getMockBuilder(
            className: EntityIndexingRecordProviderInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->implementationFqcn = EntityIndexerService::class;
        $this->interfaceFqcn = EntityIndexerServiceInterface::class;
        $this->constructorArgumentDefaults = [
            'entityIndexingRecordProvider' => $mockEntityIndexingRecordProvider,
            'pipelineConfigurationFilepath' => 'Klevu_Indexing::etc/pipeline/process-batch-payload.yml',
            'pipelineIdentifier' => 'KLEVU_PRODUCT::add',
        ];
        $this->objectManager = Bootstrap::getObjectManager();
    }

    public function testExecute_ThrowsException_ForInvalidIndexerService(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage(
            'Invalid parameter configuration provided for $entityIndexingRecordProvider'
            . ' argument of Klevu\Indexing\Service\EntityIndexerService',
        );

        $this->instantiateTestObject([
            'entityIndexingRecordProvider' => '',
            'pipelineConfigurationFilepath' => '',
        ]);
    }

    public function testExecute_throwsException_WhenPipelineConfigurationFileIsMissing(): void
    {
        $pipelineIdentifier = 'KLEVU_PRODUCT::add';
        $filepath = 'uerguerhg';

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage(
            sprintf(
                'File %s does not exist',
                $filepath,
            ),
        );

        $mockEntityIndexingRecordProvider = $this->getMockBuilder(
            className: EntityIndexingRecordProviderInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();

        $this->instantiateTestObject([
            'entityIndexingRecordProvider' => $mockEntityIndexingRecordProvider,
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: $filepath,
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
        ]);
    }

    public function testExecute_ThrowsException_WhenPipelineConfigurationIsInvalid(): void
    {
        $pipelineIdentifier = 'KLEVU_PRODUCT::add';
        $filepath = 'Klevu_TestFixtures::_files/pipeline/invalid_configuration.yml';
        
        $this->expectException(InvalidPipelineConfigurationException::class);
        $this->expectExceptionMessageMatches(
            '#A YAML file cannot contain tabs as indentation in '
            . '".*_files\/pipeline\/invalid_configuration\.yml" at line 2 \(near "	tab-indented: thing"\)#',
        );

        $mockEntityIndexingRecordProvider = $this->getMockBuilder(
            className: EntityIndexingRecordProviderInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();

        $service = $this->instantiateTestObject([
            'entityIndexingRecordProvider' => $mockEntityIndexingRecordProvider,
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: $filepath,
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
        ]);
        $service->execute(apiKey: '');
    }

    public function testExecute_ThrowsException_WhenPipelineConfigurationInvalidStages(): void
    {
        $pipelineIdentifier = 'KLEVU_PRODUCT::add';
        $filepath = 'Klevu_TestFixtures::_files/pipeline/invalid_stages.yml';
        
        $this->expectException(InvalidPipelineConfigurationException::class);
        $this->expectExceptionMessage(
            'array_map(): Argument #2 ($array) must be of type array, string given',
        );

        $mockEntityIndexingRecordProvider = $this->getMockBuilder(
            className: EntityIndexingRecordProviderInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();

        $service = $this->instantiateTestObject([
            'entityIndexingRecordProvider' => $mockEntityIndexingRecordProvider,
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: $filepath,
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
        ]);
        $service->execute(apiKey: '');
    }

    public function testExecute_HandlesValidationException(): void
    {
        $pipelineIdentifier = 'KLEVU_PRODUCT::add';
        $filepath = 'Klevu_TestFixtures::_files/pipeline/valid_no_steps.yml';
        
        $mockPipeline = $this->getMockBuilder(PipelineInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipeline->expects($this->once())
            ->method('execute')
            ->willThrowException(
                $this->objectManager->create(
                    ValidationException::class,
                    [
                        'validatorName' => 'ValidatorName',
                        'errors' => [],
                        'message' => 'Validation Failed',
                    ],
                ),
            );

        $mockPipelineBuilder = $this->getMockBuilder(PipelineBuilderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipelineBuilder->expects($this->once())
            ->method('buildFromFiles')
            ->willReturn($mockPipeline);

        $mockEntityIndexingRecordProvider = $this->getMockBuilder(
            className: EntityIndexingRecordProviderInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();

        $service = $this->instantiateTestObject([
            'pipelineBuilder' => $mockPipelineBuilder,
            'entityIndexingRecordProvider' => $mockEntityIndexingRecordProvider,
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: $filepath,
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
        ]);
        $result = $service->execute(apiKey: '');

        $this->assertSame(expected: IndexerResultStatuses::ERROR, actual: $result->getStatus(), message: 'Status');
        $this->assertContains(needle: 'Validation Failed', haystack: $result->getMessages(), message: 'Messages');
    }

    public function testExecute_HandlesExtractionException(): void
    {
        $pipelineIdentifier = 'KLEVU_PRODUCT::add';
        $filepath = 'Klevu_TestFixtures::_files/pipeline/valid_no_steps.yml';
        
        $mockPipeline = $this->getMockBuilder(PipelineInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipeline->expects($this->once())
            ->method('execute')
            ->willThrowException(
                $this->objectManager->create(
                    ExtractionException::class,
                    [
                        'message' => 'Extraction Failed',
                    ],
                ),
            );

        $mockPipelineBuilder = $this->getMockBuilder(PipelineBuilderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipelineBuilder->expects($this->once())
            ->method('buildFromFiles')
            ->willReturn($mockPipeline);

        $mockEntityIndexingRecordProvider = $this->getMockBuilder(
            className: EntityIndexingRecordProviderInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();

        $service = $this->instantiateTestObject([
            'pipelineBuilder' => $mockPipelineBuilder,
            'entityIndexingRecordProvider' => $mockEntityIndexingRecordProvider,
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: $filepath,
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
        ]);
        $result = $service->execute(apiKey: '');

        $this->assertSame(expected: IndexerResultStatuses::ERROR, actual: $result->getStatus(), message: 'Status');
        $this->assertContains(needle: 'Extraction Failed', haystack: $result->getMessages(), message: 'Messages');
    }

    public function testExecute_HandlesTransformationException(): void
    {
        $pipelineIdentifier = 'KLEVU_PRODUCT::add';
        $filepath = 'Klevu_TestFixtures::_files/pipeline/valid_no_steps.yml';
        
        $mockPipeline = $this->getMockBuilder(PipelineInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipeline->expects($this->once())
            ->method('execute')
            ->willThrowException(
                $this->objectManager->create(
                    TransformationException::class,
                    [
                        'transformerName' => 'TransformerName',
                        'errors' => [
                            'An Error Occurred',
                        ],
                        'message' => 'Transformation Failed',
                    ],
                ),
            );

        $mockPipelineBuilder = $this->getMockBuilder(PipelineBuilderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipelineBuilder->expects($this->once())
            ->method('buildFromFiles')
            ->willReturn($mockPipeline);

        $mockEntityIndexingRecordProvider = $this->getMockBuilder(
            className: EntityIndexingRecordProviderInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();

        $service = $this->instantiateTestObject([
            'pipelineBuilder' => $mockPipelineBuilder,
            'entityIndexingRecordProvider' => $mockEntityIndexingRecordProvider,
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: $filepath,
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
        ]);
        $result = $service->execute(apiKey: '');

        $this->assertSame(expected: IndexerResultStatuses::ERROR, actual: $result->getStatus(), message: 'Status');
        $this->assertContains(needle: 'Transformation Failed', haystack: $result->getMessages(), message: 'Messages');
    }

    public function testExecute_HandlesStageException(): void
    {
        $pipelineIdentifier = 'KLEVU_PRODUCT::add';
        $filepath = 'Klevu_TestFixtures::_files/pipeline/valid_no_steps.yml';
        $stageException = new \Exception('Something went wrong');

        $mockPipeline = $this->getMockBuilder(PipelineInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipeline->expects($this->once())
            ->method('execute')
            ->willThrowException(
                $this->objectManager->create(
                    StageException::class,
                    [
                        'pipeline' => $mockPipeline,
                        'message' => 'Stage Failed',
                        'previous' => $stageException,
                    ],
                ),
            );

        $mockPipelineBuilder = $this->getMockBuilder(PipelineBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockPipelineBuilder->expects($this->once())
            ->method('buildFromFiles')
            ->willReturn($mockPipeline);

        $mockEntityIndexingRecordProvider = $this->getMockBuilder(
            className: EntityIndexingRecordProviderInterface::class,
        )
            ->disableOriginalConstructor()
            ->getMock();

        $service = $this->instantiateTestObject([
            'pipelineBuilder' => $mockPipelineBuilder,
            'entityIndexingRecordProvider' => $mockEntityIndexingRecordProvider,
            'pipelineConfigurationProvider' => $this->getPipelineConfigurationProvider(
                pipelineIdentifier: $pipelineIdentifier,
                pipelineConfigurationFilepath: $filepath,
            ),
            'pipelineIdentifier' => $pipelineIdentifier,
        ]);
        $result = $service->execute(apiKey: '');

        $this->assertSame(expected: IndexerResultStatuses::ERROR, actual: $result->getStatus(), message: 'Status');
        $this->assertContains(needle: 'Something went wrong', haystack: $result->getMessages(), message: 'Messages');
    }

    /**
     * @param string $pipelineIdentifier
     * @param string $pipelineConfigurationFilepath
     * @param string[] $pipelineConfigurationOverridesFilepaths
     *
     * @return PipelineConfigurationProviderInterface
     * @throws PHPUnitException
     */
    private function getPipelineConfigurationProvider(
        string $pipelineIdentifier,
        string $pipelineConfigurationFilepath,
        array $pipelineConfigurationOverridesFilepaths = [],
    ): PipelineConfigurationProviderInterface {
        /** @var MockObject&PipelineConfigurationOverridesFilepathsProviderInterface $mockPipelineConfigurationOverridesFilepathsProvider */
        $mockPipelineConfigurationOverridesFilepathsProvider = $this->getMockBuilder(
            className: PipelineConfigurationOverridesFilepathsProviderInterface::class,
        )->disableOriginalConstructor()
            ->getMock();
        $mockPipelineConfigurationOverridesFilepathsProvider
            ->method('get')
            ->willReturn($pipelineConfigurationOverridesFilepaths);

        return $this->objectManager->create(
            type: PipelineConfigurationProviderInterface::class,
            arguments: [
                'pipelineConfigurationFilepaths' => [
                    $pipelineIdentifier => $pipelineConfigurationFilepath,
                ],
                'pipelineConfigurationOverridesFilepathsProviders' => [
                    $pipelineIdentifier => $mockPipelineConfigurationOverridesFilepathsProvider,
                ],
            ],
        );
    }
}
