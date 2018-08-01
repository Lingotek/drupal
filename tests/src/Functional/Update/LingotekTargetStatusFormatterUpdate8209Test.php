<?php

namespace Drupal\Tests\lingotek\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the upgrade path for changing the lingotek_target_status id to
 * lingotek_target_statuses.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekTargetStatusFormatterUpdate8209Test extends UpdatePathTestBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->configFactory = $this->container->get('config.factory');
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../fixtures/update/drupal-8.lingotek.standard.pre8209.php.gz',
      __DIR__ . '/../../../fixtures/update/lingotektargetstatusupgrade2985742.php',
    ];
  }

  /**
   * Tests that the upgrade sets the correct field formatter id in a view.
   */
  public function testUpgrade() {
    $view = $this->configFactory->get('views.view.lingotektargetstatusupgrade2985742');

    $field_type = $view->get('display.default.display_options.fields.translation_status_value.type');
    $this->assertEquals($field_type, 'lingotek_translation_status');

    $this->runUpdates();

    $view = $this->configFactory->get('views.view.lingotektargetstatusupgrade2985742');
    $field_type = $view->get('display.default.display_options.fields.translation_status_value.type');
    $this->assertEquals($field_type, 'lingotek_translation_statuses');

    $basepath = \Drupal::request()->getBasePath();

    $this->drupalGet('/lingotektargetstatusupgrade2985742');
    $this->assertSession()->responseNotContains('IGNORED-SEPARATOR');
    $this->assertSession()->responseContains('<a href="' . $basepath . '/admin/lingotek/entity/add_target/document_id_1/de_DE?destination=' . $basepath . '/lingotektargetstatusupgrade2985742" class="language-icon target-request" title="German - Request translation">DE</a><a href="' . $basepath . '/admin/lingotek/workbench/document_id_1/es_ES" target="_blank" class="language-icon target-current" title="Spanish - Current">ES</a>');
  }

}
