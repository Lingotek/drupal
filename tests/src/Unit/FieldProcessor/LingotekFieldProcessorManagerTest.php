<?php

namespace Drupal\Tests\lingotek\Unit\FieldProcessor {

  use Drupal\Component\Plugin\Discovery\StaticDiscovery;
  use Drupal\Core\Cache\CacheBackendInterface;
  use Drupal\Core\DependencyInjection\ContainerBuilder;
  use Drupal\Core\Entity\ContentEntityInterface;
  use Drupal\Core\Entity\EntityTypeManager;
  use Drupal\Core\Extension\ModuleHandlerInterface;
  use Drupal\Core\Field\FieldDefinitionInterface;
  use Drupal\Core\Logger\LoggerChannelFactory;
  use Drupal\lingotek\FieldProcessor\LingotekFieldProcessorManager;
  use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
  use Drupal\lingotek\LingotekConfigurationServiceInterface;
  use Drupal\lingotek\LingotekContentTranslationServiceInterface;
  use Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekDefaultProcessor;
  use Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekEntityReferenceProcessor;
  use Drupal\lingotek\Plugin\LingotekFieldProcessor\LingotekPathProcessor;
  use Drupal\Tests\UnitTestCase;
  use Psr\Log\LoggerInterface;

  /**
   * @coversDefaultClass \Drupal\lingotek\FieldProcessor\LingotekFieldProcessorManager
   * @group lingotek
   * @preserveGlobalState disabled
   */
  class LingotekFieldProcessorManagerTest extends UnitTestCase {

    /**
     * The class instance under test.
     *
     * @var \Drupal\lingotek\FieldProcessor\LingotekFieldProcessorManager
     */
    protected $processorManager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void {
      parent::setUp();
      $cache = $this->createMock(CacheBackendInterface::class);
      $module_handler = $this->createMock(ModuleHandlerInterface::class);

      $this->processorManager = new TestLingotekFieldProcessorManager(new \ArrayObject(), $cache, $module_handler);

      $container = new ContainerBuilder();
      $lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
      $entityTypeManager = $this->createMock(EntityTypeManager::class);
      $lingotekConfigTranslation = $this->createMock(LingotekConfigTranslationServiceInterface::class);
      $lingotekContentTranslation = $this->createMock(LingotekContentTranslationServiceInterface::class);
      $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
      $loggerFactory = $this->createMock(LoggerChannelFactory::class);
      $logger = $this->createMock(LoggerInterface::class);
      $loggerFactory->expects($this->any())
        ->method('get')
        ->with('lingotek')
        ->willReturn($logger);
      $container->set('lingotek.configuration', $lingotekConfiguration);
      $container->set('entity_type.manager', $entityTypeManager);
      $container->set('lingotek.config_translation', $lingotekConfigTranslation);
      $container->set('lingotek.content_translation', $lingotekContentTranslation);
      $container->set('module_handler', $moduleHandler);
      $container->set('logger.factory', $loggerFactory);

      \Drupal::setContainer($container);

    }

    /**
     * @covers ::getProcessorsForField
     * @dataProvider dataProviderProcessorsFactory
     */
    public function testGetProcessorsForField($field_type, $expected_class) {
      $entity = $this->createMock(ContentEntityInterface::class);
      $field_definition = $this->createMock(FieldDefinitionInterface::class);
      $field_definition->expects($this->any())
        ->method('getType')
        ->willReturn($field_type);

      $processors = $this->processorManager->getProcessorsForField($field_definition, $entity);
      $this->assertInstanceOf($expected_class, end($processors));
    }

    public function dataProviderProcessorsFactory() {
      yield 'path field' => ['path', LingotekPathProcessor::class];
      yield 'string_text field' => ['string_text', LingotekDefaultProcessor::class];
      yield 'entity_reference field' => ['entity_reference', LingotekEntityReferenceProcessor::class];
      yield 'not handled field' => ['nah', LingotekDefaultProcessor::class];
    }

    /**
     * @covers ::getDefaultProcessor
     */
    public function testGetDefaultProcessor() {
      $processor = $this->processorManager->getDefaultProcessor();
      $this->assertInstanceOf(LingotekDefaultProcessor::class, $processor);
    }

  }

  /**
   * Defines a plugin manager used by unit tests.
   */
  class TestLingotekFieldProcessorManager extends LingotekFieldProcessorManager {

    /**
     * {@inheritdoc}
     */
    public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
      parent::__construct($namespaces, $cache_backend, $module_handler);

      // Create the object that can be used to return definitions for all the
      // plugins available for this type. Most real plugin managers use a richer
      // discovery implementation, but StaticDiscovery lets us add some simple
      // mock plugins for unit testing.
      $this->discovery = new StaticDiscovery();

      $this->discovery->setDefinition('default', [
        'class' => LingotekDefaultProcessor::class,
        'weight' => 0,
      ]);
      $this->discovery->setDefinition('entity_reference', [
        'class' => LingotekEntityReferenceProcessor::class,
        'weight' => 5,
      ]);
      $this->discovery->setDefinition('path', [
        'class' => LingotekPathProcessor::class,
        'weight' => 5,
      ]);
    }

  }

}
