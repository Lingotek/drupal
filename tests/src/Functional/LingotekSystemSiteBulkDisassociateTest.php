<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating config using the bulk management form.
 *
 * @group lingotek
 */
class LingotekSystemSiteBulkDisassociateTest extends LingotekTestBase {

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
   * Tests that a config entity can be disassociated using the bulk operations on the management page.
   */
  public function testSystemSiteDisassociate() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndTranslateSystemSiteWithLinks();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // Mark the first two for disassociation.
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'disassociate',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')->getMappers();
    $mapper = $mappers['system.site_information_settings'];

    // Assert that no document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(0, count($deleted_docs), 'No document has been deleted remotely because the module is not configured to perform the operation.');

    $this->assertNull($config_translation_service->getConfigDocumentId($mapper));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getConfigSourceStatus($mapper));

    // We can request again.
    $this->createAndTranslateSystemSiteWithLinks();
  }

  /**
   * Tests that a config entity can be disassociated using the bulk operations on the management page.
   */
  public function testSystemSiteDisassociateWithRemovalOfRemoteDocument() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Enable remote delete while disassociating.
    $this->drupalGet('admin/lingotek/settings');
    $edit = [
      'delete_tms_documents_upon_disassociation' => TRUE,
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    $this->createAndTranslateSystemSiteWithLinks();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // Mark the first two for disassociation.
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'disassociate',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')->getMappers();
    $mapper = $mappers['system.site_information_settings'];

    // Assert that the document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(1, count($deleted_docs), 'The document has been deleted remotely.');

    $this->assertNull($config_translation_service->getConfigDocumentId($mapper));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getConfigSourceStatus($mapper));

    // We can request again.
    $this->createAndTranslateSystemSiteWithLinks();
  }

  protected function createAndTranslateSystemSiteWithLinks() {
    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));

    // There is a link for checking status.
    $this->clickLink('EN', 1);
    $this->assertText('System information status checked successfully');

    // Request the Spanish translation.
    $this->clickLink('ES');
    $this->assertText("Translation to es_ES requested successfully");

    // Check status of the Spanish translation.
    $this->clickLink('ES');
    $this->assertText("Translation to es_ES checked successfully");

    // Download the Spanish translation.
    $this->clickLink('ES');
    $this->assertText('Translation to es_ES downloaded successfully');
  }

}
