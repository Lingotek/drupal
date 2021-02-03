<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests translating a field using the bulk management form.
 *
 * @group lingotek
 */
class LingotekFieldBodyExistingBulkTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'field_ui'];

  /**
   * {@inheritdoc}
   */
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

    // Create a translation for the config entity.
    $config = \Drupal::languageManager()->getLanguageConfigOverride('es', 'field.field.node.article.body');
    $config->set('label', 'Translated Body')->save();

    $this->saveLingotekConfigTranslationSettings([
      'node_fields' => 'automatic',
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'body');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testBodyFieldIsUntracked() {
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    $basepath = \Drupal::request()->getBasePath();

    // Assert the untracked translation is shown.
    $this->assertTargetStatus('ES', 'untracked');

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/field_config/node.article.body?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN');
    $this->assertText(t('Body uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Assert the untracked translation is shown.
    $this->assertTargetStatus('ES', 'untracked');

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

    // Download the Spanish translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/download/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');
  }

}
