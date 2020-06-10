<?php

namespace Drupal\Tests\lingotek\Unit\EventSubscriber {

  use Drupal\config_translation\ConfigMapperManagerInterface;
  use Drupal\config_translation\ConfigNamesMapper;
  use Drupal\Core\Config\Config;
  use Drupal\Core\Config\ConfigCrudEvent;
  use Drupal\Core\Entity\EntityFieldManagerInterface;
  use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
  use Drupal\Core\Entity\EntityTypeManagerInterface;
  use Drupal\field\Entity\FieldConfig;
  use Drupal\lingotek\EventSubscriber\LingotekConfigSubscriber;
  use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
  use Drupal\lingotek\LingotekConfigurationServiceInterface;
  use Drupal\Tests\UnitTestCase;

  /**
   * @coversDefaultClass \Drupal\lingotek\EventSubscriber\LingotekConfigSubscriber
   * @group lingotek
   * @preserveGlobalState disabled
   */
  class LingotekConfigSubscriberUnitTest extends UnitTestCase {

    /**
     * The Lingotek content translation service.
     *
     * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $translationService;

    /**
     * The mapper manager.
     *
     * @var \Drupal\config_translation\ConfigMapperManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mapperManager;

    /**
     * A array of configuration mapper instances.
     *
     * @var \Drupal\config_translation\ConfigMapperInterface[]|\PHPUnit_Framework_MockObject_MockObject[]
     */
    protected $mappers;

    /**
     * A configuration mapper instance.
     *
     * @var \Drupal\config_translation\ConfigMapperInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mapper;

    /**
     * The Lingotek configuration service.
     *
     * @var \Drupal\lingotek\LingotekConfigurationServiceInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $lingotekConfiguration;

    /**
     * Entity manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityTypeManager;

    /**
     * The entity field manager.
     *
     * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityFieldManager;

    /**
     * The entity type bundle info.
     *
     * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityTypeBundleInfo;

    /**
     * The config subscriber under test.
     *
     * @var \Drupal\lingotek\EventSubscriber\LingotekConfigSubscriber
     */
    protected $configSubscriber;

    protected function setUp(): void {
      parent::setUp();

      $this->translationService = $this->createMock(LingotekConfigTranslationServiceInterface::class);
      $this->mapperManager = $this->createMock(ConfigMapperManagerInterface::class);
      $this->mapper = $this->createMock(ConfigNamesMapper::class);
      $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
      $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
      $this->entityFieldManager = $this->createMock(EntityFieldManagerInterface::class);

      $this->mappers = [$this->mapper];
      $this->mapperManager->expects($this->any())
        ->method('getMappers')
        ->willReturn($this->mappers);

      $this->configSubscriber = new LingotekConfigSubscriber(
        $this->translationService,
        $this->mapperManager,
        $this->lingotekConfiguration,
        $this->entityTypeManager,
        $this->entityFieldManager
      );
    }

    /**
     * @covers ::onConfigSave
     */
    public function testOnConfigSaveWhenFieldDefinitionDoesntExist() {
      $id = 'node.article.myfieldname';
      $field_id = 'field.field.' . $id;

      $config = $this->createMock(FieldConfig::class);
      $config->expects($this->at(1))
        ->method('getName')
        ->willReturn($field_id);
      $config->expects($this->at(2))
        ->method('get')
        ->with('id')
        ->willReturn($id);
      $config->expects($this->at(3))
        ->method('get')
        ->with('translatable')
        ->willReturn(FALSE);

      $event = $this->createMock(ConfigCrudEvent::class);
      $event->expects($this->any())
        ->method('getConfig')
        ->willReturn($config);
      $event->expects($this->once())
        ->method('isChanged')
        ->with('translatable')
        ->willReturn(TRUE);

      $this->entityFieldManager->expects($this->once())
        ->method('getFieldDefinitions')
        ->with('node', 'article')
        ->willReturn(['myfieldname' => NULL]);

      $this->configSubscriber->onConfigSave($event);
    }

    /**
     * @covers ::onConfigSave
     */
    public function testOnConfigSaveWhenProfileIsNull() {
      $id = 'my.settings';

      $this->mapper->expects($this->once())
        ->method('getConfigNames')
        ->willReturn([$id]);

      $config = $this->createMock(Config::class);
      $config->expects($this->at(0))
        ->method('getName')
        ->willReturn($id);

      $event = $this->createMock(ConfigCrudEvent::class);
      $event->expects($this->any())
        ->method('getConfig')
        ->willReturn($config);

      $this->mapper->expects($this->once())
        ->method('getPluginId')
        ->willReturn('a_config_plugin_for_config');

      $this->lingotekConfiguration->expects($this->once())
        ->method('getConfigProfile')
        ->with('a_config_plugin_for_config')
        ->willReturn(NULL);

      $this->configSubscriber->onConfigSave($event);
    }

  }

}

namespace {

  if (!function_exists('drupal_installation_attempted')) {

    function drupal_installation_attempted() {
      return FALSE;
    }

  }

}
