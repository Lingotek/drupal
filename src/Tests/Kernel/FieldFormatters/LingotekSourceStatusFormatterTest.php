<?php

namespace Drupal\lingotek\Tests\Kernel\FieldFormatters;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the Lingotek source status formatter.
 *
 * @group lingotek
 */
class LingotekSourceStatusFormatterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'field', 'text', 'locale', 'language', 'config_translation', 'content_translation', 'lingotek', 'user', 'entity_test'];

  /**
   * @var string
   */
  protected $entityType;

  /**
   * @var string
   */
  protected $bundle;

  /**
   * @var string
   */
  protected $fieldName;

  /**
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $display;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = \Drupal::service('entity_type.manager');

    $this->installConfig(['system']);
    $this->installConfig(['field']);
    $this->installConfig(['language']);
    $this->installEntitySchema('entity_test');

    $this->entityType = 'entity_test';
    $this->bundle = $this->entityType;
    $this->fieldName = mb_strtolower($this->randomMachineName());

    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $this->entityType,
      'type' => 'language',
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->bundle,
      'label' => $this->randomMachineName(),
    ]);
    $instance->save();

    $this->display = $this->entityTypeManager->getStorage('entity_view_display')
      ->create([
        'targetEntityType' => $this->entityType,
        'bundle' => $this->bundle,
        'mode' => 'default',
        'status' => TRUE,
      ])
      ->setComponent($this->fieldName, []);
    $this->display->save();
  }

  /**
   * Renders fields of a given entity with a given display.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object with attached fields to render.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display to render the fields in.
   *
   * @return string
   *   The rendered entity fields.
   */
  protected function renderEntityFields(FieldableEntityInterface $entity, EntityViewDisplayInterface $display) {
    $content = $display->build($entity);
    $content = $this->render($content);
    return $content;
  }

  /**
   * Tests LingotekSourceStatusFormatter.
   */
  public function testLingotekSourceStatusFormatter() {
    $expected = '<span class="language-icon source-untracked" title="Upload"><a href="/admin/lingotek/entity/upload/entity_test/1?destination=/">EN</a></span>';

    $english = ConfigurableLanguage::load('en');

    $component = $this->display->getComponent($this->fieldName);
    $component['type'] = 'lingotek_source_status';
    $this->display->setComponent($this->fieldName, $component);

    $entity = EntityTest::create(['id' => 1]);
    $entity->{$this->fieldName}->value = $english;

    $this->renderEntityFields($entity, $this->display);
    $this->assertRaw($expected);
  }

}
