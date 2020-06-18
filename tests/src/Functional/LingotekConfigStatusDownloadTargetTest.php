<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests translating a field using the bulk management form.
 *
 * @group lingotek
 */
class LingotekConfigStatusDownloadTargetTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'field_ui'];

  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    node_add_body_field($type);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    $this->saveLingotekConfigTranslationSettings([
      'node_fields' => 'automatic',
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'body');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testConfigStatusDownloadTarget() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('config');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('config'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('config'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Request the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX requested successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('Translation to es_MX checked successfully');

    // Edit the object
    $config = \Drupal::service('config.factory')->getEditable('system.site');
    $original_site_name = $config->get('name');
    $config->set('name', $original_site_name . '_edited')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('config');

    // Check the status is edited for Spanish.
    // TODO: should be testing for STATUS_EDITED, but since config is not
    // following the correct translation flow it is STATUS_READY
    $this->assertTargetStatus('ES', 'ready');

    // Download the Spanish translation.
    $edit = [
       'table[system.site_information_settings]' => TRUE,
       $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('config'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check the status is edited for Spanish.
    $source_edited = $this->xpath("//span[contains(@class,'language-icon') and contains(@class, 'source-edited')  and contains(@title, 'Re-upload (content has changed since last upload)')]");
    $this->assertEqual(count($source_edited), 1, 'Edited source is shown.');
    $this->assertTargetStatus('ES', 'edited');
  }

}
