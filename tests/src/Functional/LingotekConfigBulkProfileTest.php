<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Tests changing a profile using the bulk management form.
 *
 * @group lingotek
 */
class LingotekConfigBulkProfileTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  protected function setUp() {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();
  }

  /**
   * Tests that the translation profiles can be updated with the bulk actions.
   */
  public function testChangeTranslationProfileBulk() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'change_profile:automatic'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 1, 'Automatic Profile set');

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'change_profile:manual'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there is one node with the Manual Profile
    // Check that there are two nodes with the Automatic Profile
    $manual_profile = $this->xpath("//td[contains(text(), 'Manual')]");
    $this->assertEqual(count($manual_profile), 1, 'Manual Profile set');

    $this->clickLink('EN', 1);

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'change_profile:disabled'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there are three nodes with the Disabled Profile
    $disabled_profile = $this->xpath("//td[contains(text(), 'Disabled')]");
    $this->assertEqual(count($disabled_profile), 1, 'Disabled Profile set');

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');
    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')
      ->getMappers();
    $mapper = $mappers['system.site_information_settings'];

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigSourceStatus($mapper));
    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigTargetStatus($mapper, 'es'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigSourceStatus($mapper));
    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigTargetStatus($mapper, 'es'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigSourceStatus($mapper));
    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigTargetStatus($mapper, 'es'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'request_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigSourceStatus($mapper));
    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigTargetStatus($mapper, 'es'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigSourceStatus($mapper));
    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigTargetStatus($mapper, 'es'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'download:es'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigSourceStatus($mapper));
    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigTargetStatus($mapper, 'es'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'change_profile:automatic'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 1, 'Automatic Profile set');
  }

  /**
   * Tests that the translation profiles can be updated with the bulk actions after
   * disassociating.
   */
  public function testChangeTranslationProfileBulkAfterDisassociating() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'change_profile:automatic'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 1, 'Automatic Profile set');

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'request_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'download'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'disassociate'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'change_profile:disabled'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');
    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')
      ->getMappers();
    $mapper = $mappers['system.site_information_settings'];

    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigSourceStatus($mapper));
    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigTargetStatus($mapper, 'es'));

    $this->drupalGet('admin/config/system/site-information/translate');

    $this->clickLink('Edit');

    $edit = [
      'site_name' => 'llamas are cool',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save configuration'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigSourceStatus($mapper));
    $this->assertIdentical(Lingotek::STATUS_DISABLED, $config_translation_service->getConfigTargetStatus($mapper, 'es'));
  }

}
