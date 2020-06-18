<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests translating a field using the bulk management form.
 *
 * @group lingotek
 */
class LingotekConfigEntityStatusDownloadTargetTest extends LingotekTestBase {

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
  public function testConfigEntityStatusDownloadTarget() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/field_config/node.article.body?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText(t('Body uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/field_config/node.article.body?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText('Body status checked successfully');

    // Request the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_download/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX status checked successfully");

    // Edit the object
    $this->drupalPostForm('/admin/structure/types/manage/article/fields/node.article.body', ['label' => 'Body EDITED'], t('Save settings'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');
    // TODO: once the config is following the correct translation flow then the
    // following tests will work, but for now it's not
    // // Check the status is edited for Spanish.
    // $edited = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'ES')]");
    // $this->assertEqual(count($edited), 1, 'Edited translation is shown.');
    //
    // // Download the Spanish translation.
    // $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    // $this->clickLink('ES');
    // $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    // $this->assertText('Translation to es_MX downloaded successfully');
    //
    // // Check the status is edited for Spanish.
    // $source_untracked = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'source-edited')  and contains(@title, 'Re-upload (content has changed since last upload)')]");
    // $this->assertEqual(count($source_untracked), 1, 'Edited source is shown.');
    // $untracked = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'ES')]");
    // $this->assertEqual(count($untracked), 1, 'Edited translation is shown.');
  }

}
