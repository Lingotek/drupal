<?php

namespace Drupal\Tests\lingotek\Unit\EventSubscriber {

  use Drupal\config_translation\ConfigMapperManagerInterface;
  use Drupal\config_translation\ConfigNamesMapper;
  use Drupal\Core\Config\Config;
  use Drupal\Core\Config\ConfigCrudEvent;
  use Drupal\Core\Entity\EntityManagerInterface;
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
     * @var \Drupal\Core\Entity\EntityManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $entityManager;

    /**
     * The config subscriber under test.
     *
     * @var \Drupal\lingotek\EventSubscriber\LingotekConfigSubscriber
     */
    protected $configSubscriber;

    protected function setUp() {
      parent::setUp();

      $this->translationService = $this->createMock(LingotekConfigTranslationServiceInterface::class);
      $this->mapperManager = $this->createMock(ConfigMapperManagerInterface::class);
      $this->mapper = $this->createMock(ConfigNamesMapper::class);
      $this->lingotekConfiguration = $this->createMock(LingotekConfigurationServiceInterface::class);
      $this->entityManager = $this->createMock(EntityManagerInterface::class);

      $this->mappers = [$this->mapper];
      $this->mapperManager->expects($this->any())
        ->method('getMappers')
        ->willReturn($this->mappers);

      $this->configSubscriber = new LingotekConfigSubscriber(
        $this->translationService,
        $this->mapperManager,
        $this->lingotekConfiguration,
        $this->entityManager
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

      $this->entityManager->expects($this->once())
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

  // @todo Delete after https://drupal.org/node/1858196 is in.
  if (!function_exists('drupal_set_message')) {

    function drupal_set_message() {
    }

  }

  if (!function_exists('drupal_installation_attempted')) {

    function drupal_installation_attempted() {
      return FALSE;
    }

  }

}
