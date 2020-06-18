<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating config using the bulk management form.
 *
 * @group lingotek
 */
class LingotekSystemSiteBulkCancelTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  protected function setUp(): void {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();
  }

  /**
   * Tests that a config entity can be cancelled using the bulk operations on the management page.
   */
  public function testSystemSiteCancellation() {
    $assert_session = $this->assertSession();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndTranslateSystemSiteWithLinks();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // Mark the first for cancelling.
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'cancel',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    // Assert that The document has been cancelled remotely.
    $cancelled_docs = \Drupal::state()->get('lingotek.cancelled_docs', []);
    $this->assertEqual(1, count($cancelled_docs), 'The document has been cancelled remotely.');

    // Assert that no document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(0, count($deleted_docs), 'No document has been deleted remotely.');

    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')->getMappers();
    $mapper = $mappers['system.site_information_settings'];

    $this->assertNull($config_translation_service->getConfigDocumentId($mapper));

    $this->assertSourceStatus('EN', Lingotek::STATUS_CANCELLED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);

    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $config_translation_service->getConfigSourceStatus($mapper));
    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $config_translation_service->getConfigTargetStatus($mapper, 'es'));

    // We can request again.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->createAndTranslateSystemSiteWithLinks();
  }

  /**
   * Tests that a config target can be cancelled using the bulk operations on the management page.
   */
  public function testSystemSiteCancelTarget() {
    $assert_session = $this->assertSession();
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndTranslateSystemSiteWithLinks();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // Mark the first for cancelling.
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => 'cancel:es',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')->getMappers();
    $mapper = $mappers['system.site_information_settings'];

    // Assert that the document target has been cancelled remotely.
    $cancelled_locales = \Drupal::state()->get('lingotek.cancelled_locales', []);
    $this->assertTrue(isset($cancelled_locales['dummy-document-hash-id']) && in_array('es_ES', $cancelled_locales['dummy-document-hash-id']),
      'The document target has been cancelled remotely.');

    $this->assertEquals('dummy-document-hash-id', $config_translation_service->getConfigDocumentId($mapper));

    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);

    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getConfigSourceStatus($mapper));
    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $config_translation_service->getConfigTargetStatus($mapper, 'es'));

    // We cannot request again.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
  }

  protected function createAndTranslateSystemSiteWithLinks() {
    $assert_session = $this->assertSession();
    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Clicking English must init the upload of content.
    $this->clickLink('EN', 1);
    $this->assertText(t('System information uploaded successfully'));

    // There is a link for checking status.
    $this->clickLink('EN', 1);
    $this->assertText('System information status checked successfully');

    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

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
