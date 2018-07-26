<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests translating a field using the bulk management form.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekConfigTranslationEditedToPendingTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    node_add_body_field($type);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

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
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('config');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'check_upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX requested successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('Translation to es_MX checked successfully');

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/system.site_information_settings/system.site_information_settings/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('Translation to es_MX downloaded successfully');

    // Edit the object
    $config2 = \Drupal::service('config.factory')->getEditable('system.site');
    $original_site_name = $config2->get('name');
    $config2->set('name', $original_site_name . '_edited')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('config');

    // Check the status is edited for Spanish.
    $edited = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'ES')]");
    $this->assertEqual(count($edited), 1, 'Edited translation is shown.');

    // Check the status is marked REQUEST for German
    $de_request = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-request')  and contains(text(), 'DE')]");
    $this->assertEqual(count($de_request), 1, 'German is marked as request.');

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/update/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/system.site_information_settings/system.site_information_settings?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[system.site_information_settings]' => TRUE,
      'operation' => 'check_upload',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    // Check the status is edited for Spanish.
    $es_pending = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-pending')  and contains(text(), 'ES')]");
    $this->assertEqual(count($es_pending), 1, 'Pending Spanish translation is shown.');
    // Check the status is still request for German.
    $de_request = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-request') and contains(text(), 'DE')]");
    $this->assertEqual(count($de_request), 1, 'German is still marked as request.');
  }

}
