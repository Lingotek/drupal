<?php

namespace Drupal\Tests\lingotek\Unit\FormComponent;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\lingotek\Annotation\LingotekFormComponentAnnotationBase;
use Drupal\lingotek\FormComponent\LingotekFormComponentInterface;
use Drupal\lingotek\FormComponent\LingotekFormComponentManagerBase;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @coversDefaultClass \Drupal\lingotek\FormComponent\LingotekFormComponentManagerBase
 * @group lingotek
 * @preserveGlobalState disabled
 */
class LingotekFormComponentManagerBaseTest extends UnitTestCase {

  /**
   * The form component manager under test.
   *
   * @var \Drupal\lingotek\FormComponent\LingotekFormComponentManagerBase
   */
  protected $formComponentManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $logger;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $module_handler = $this->createMock(ModuleHandlerInterface::class);

    $this->formComponentManager = new TestLingotekFormComponentManagerBase('whatever', new \ArrayObject(), $module_handler);

    $container = new ContainerBuilder();
    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactory::class);
    $this->logger = $this->createMock(LoggerInterface::class);
    $loggerFactory->expects($this->any())
      ->method('get')
      ->with('lingotek')
      ->willReturn($this->logger);
    $container->set('module_handler', $moduleHandler);
    $container->set('logger.factory', $loggerFactory);

    \Drupal::setContainer($container);
  }

  /**
   * @covers ::getDefinitions
   */
  public function testGetDefinitions() {
    $definitions = [];
    $definitions['object'] = [
      'id' => 'object',
    ];
    $definitions['array'] = [
      'id' => 'array',
    ];
    $discovery = $this->prophesize(DiscoveryInterface::class);
    $discovery->getDefinitions()->willReturn($definitions);
    $this->formComponentManager->setDiscovery($discovery->reveal());

    $expected = [
      'object' => ['id' => 'object'],
      'array' => ['id' => 'array'],
    ];
    $this->assertEquals($expected, $this->formComponentManager->getDefinitions());
  }

  /**
   * @covers ::getApplicable
   */
  public function testGetApplicableWithNoFormId() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The form_id argument is not specified.');

    $this->formComponentManager->getApplicable();
  }

  /**
   * @covers ::getApplicable
   */
  public function testGetApplicableWithNoEntityTypeId() {
    $definitions['with_same_form_id'] = [
      'id' => 'with_same_form_id',
      'form_ids' => [
        'test_form_id',
      ],
      'class' => TestLingotekFormComponent::class,
    ];
    $definitions['with_same_form_id_and_entity_type'] = [
      'id' => 'with_same_form_id_and_entity_type',
      'form_ids' => [
        'test_form_id',
      ],
      'entity_types' => ['my_entity_type_id'],
      'class' => TestLingotekFormComponent::class,
    ];
    $definitions['with_same_form_id_but_different_entity_type'] = [
      'id' => 'with_same_form_id_but_different_entity_type',
      'form_ids' => [
        'test_form_id',
      ],
      'entity_types' => ['different_entity_type'],
      'class' => TestLingotekFormComponent::class,
    ];
    $definitions['with_different_form_id'] = [
      'id' => 'with_different_form_id',
      'form_ids' => [
        'another_test_form_id',
      ],
      'class' => TestLingotekFormComponent::class,
    ];
    $discovery = $this->prophesize(DiscoveryInterface::class);
    $discovery->getDefinitions()->willReturn($definitions);
    $this->formComponentManager->setDiscovery($discovery->reveal());

    $components = $this->formComponentManager->getApplicable(['form_id' => 'test_form_id']);
    $this->assertCount(3, $components);
    $this->assertArrayHasKey('with_same_form_id', $components);
    $this->assertArrayHasKey('with_same_form_id_and_entity_type', $components);
    $this->assertArrayHasKey('with_same_form_id_but_different_entity_type', $components);
  }

  /**
   * @covers ::getApplicable
   */
  public function testGetApplicableWithMissingPlugin() {
    $definitions['missing_plugin'] = [
      'id' => 'missing_plugin',
      'form_ids' => [
        'test_form_id',
      ],
      'class' => 'MissingPlugin',
    ];
    $discovery = $this->prophesize(DiscoveryInterface::class);
    $discovery->getDefinitions()->willReturn($definitions);
    $this->formComponentManager->setDiscovery($discovery->reveal());

    $this->logger->expects($this->once())
      ->method('warning')
      ->with('No plugin found for <em class="placeholder">missing_plugin</em>. Error: <em class="placeholder">Plugin (missing_plugin) instance class &quot;MissingPlugin&quot; does not exist.</em>');

    $components = $this->formComponentManager->getApplicable(['form_id' => 'test_form_id']);
    $this->assertEmpty($components);
  }

  /**
   * @covers ::getApplicable
   */
  public function testGetApplicable() {
    $definitions['with_same_form_id'] = [
      'id' => 'with_same_form_id',
      'form_ids' => [
        'test_form_id',
      ],
      'class' => TestLingotekFormComponent::class,
    ];
    $definitions['with_same_form_id_and_entity_type'] = [
      'id' => 'with_same_form_id_and_entity_type',
      'form_ids' => [
        'test_form_id',
      ],
      'entity_types' => ['my_entity_type_id'],
      'class' => TestLingotekFormComponent::class,
    ];
    $definitions['with_same_form_id_but_different_entity_type'] = [
      'id' => 'with_same_form_id_but_different_entity_type',
      'form_ids' => [
        'test_form_id',
      ],
      'entity_types' => ['different_entity_type'],
      'class' => TestLingotekFormComponent::class,
    ];
    $definitions['with_different_form_id'] = [
      'id' => 'with_different_form_id',
      'form_ids' => [
        'another_test_form_id',
      ],
      'class' => TestLingotekFormComponent::class,
    ];
    $discovery = $this->prophesize(DiscoveryInterface::class);
    $discovery->getDefinitions()->willReturn($definitions);
    $this->formComponentManager->setDiscovery($discovery->reveal());

    $components = $this->formComponentManager->getApplicable([
      'form_id' => 'test_form_id',
      'entity_type_id' => 'my_entity_type_id',
    ]);
    $this->assertCount(2, $components);
    $this->assertArrayHasKey('with_same_form_id', $components);
    $this->assertArrayHasKey('with_same_form_id_and_entity_type', $components);
  }

}

class TestLingotekFormComponentManagerBase extends LingotekFormComponentManagerBase {

  public function setDiscovery(DiscoveryInterface $discovery) {
    $this->discovery = $discovery;
  }

}

class TestLingotekFormComponentAnnotation extends LingotekFormComponentAnnotationBase {}

class TestLingotekFormComponent implements LingotekFormComponentInterface {

  protected $configuration;
  protected $pluginId;
  protected $pluginDefinition;
  protected $entityTypeId;

  public function __construct(array $configuration, $plugin_id = NULL, $plugin_definition = NULL, $entity_type_id = NULL) {
    $this->configuration = $configuration;
    $this->pluginId = $plugin_id;
    $this->pluginDefinition = $plugin_definition;
    $this->entityTypeId = $entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $configuration['weight'] = $configuration['weight'] ?? 0;
    return new TestLingotekFormComponent($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function isApplicable(array $arguments = []) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntityTypeId(?string $entity_type_id) {
    $this->entityTypeId = $entity_type_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(string $entity_type_id) {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->configuration['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup() {
    return $this->configuration['group'];
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->configuration['weight'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupMachineName() {
    return $this->pluginDefinition['group'];
  }

}
