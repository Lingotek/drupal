<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

/**
 * Tests translating a field using the bulk management form.
 *
 * @group lingotek
 */
class LingotekFieldBodyExistingBulkTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'field_ui'];

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $type = $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));
    node_add_body_field($type);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Create a translation for the config entity.
    $config = \Drupal::languageManager()->getLanguageConfigOverride('es', 'field.field.node.article.body');
    $config->set('label', 'Translated Body')->save();


    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testContentTypeIsUntracked() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    $edit = [
      'filters[wrapper][bundle]' => 'node_fields',
    ];
    $this->drupalPostForm(NULL, $edit, t('Filter'));

    $basepath = \Drupal::request()->getBasePath();

    // Assert the untracked translation is shown.
    $untracked = $this->xpath("//span[contains(@class,'language-icon') and contains(@class, 'target-untracked') and contains(., 'ES')]");
    $this->assertEqual(count($untracked), 1, 'Untracked translation is shown.');

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/field_config/node.article.body?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('English');
    $this->assertText(t('Body uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Assert the untracked translation is shown.
    $untracked = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-untracked')  and contains(text(), 'ES')]");
    $this->assertEqual(count($untracked), 1, 'Untracked translation is shown.');

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/field_config/node.article.body?destination=' . $basepath .'/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('English');
    $this->assertText('Body status checked successfully');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX status checked successfully");

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/field_config/node.article.body/es_MX?destination=' . $basepath .'/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/es_MX');
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/dummy-document-hash-id/es_MX' and @target='_blank']");
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');
  }

}
