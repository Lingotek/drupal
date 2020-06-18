<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests that the module can be uninstalled when there is Lingotek metadata.
 *
 * @group lingotek
 */
class LingotekModuleUninstallationWithDataTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
  }

  /**
   * Tests that the module can be uninstalled.
   */
  public function testUninstallModule() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndDownloadANodeTranslation();

    // Navigate to the Extend page.
    $this->drupalGet('/admin/modules');

    // Ensure the module is not enabled yet.
    $this->assertSession()->checkboxChecked('edit-modules-lingotek-enable');

    $this->clickLink('Uninstall');

    $this->assertSession()->fieldDisabled('edit-uninstall-lingotek');
    // Plural reasons.
    $this->assertText('The following reasons prevent Lingotek Translation from being uninstalled:');

    // Post the form uninstalling the lingotek_test module.
    $edit = ['uninstall[lingotek_test]' => '1'];
    $this->drupalPostForm(NULL, $edit, 'Uninstall');
    $this->drupalPostForm(NULL, [], 'Uninstall');
    $this->assertText('The selected modules have been uninstalled.');

    // Singular reason.
    $this->assertText('The following reason prevents Lingotek Translation from being uninstalled:');
    $this->assertText('There is content for the entity type: Lingotek Content Metadata');
    $assert_session->linkExists('Remove lingotek content metadata entities');

    $this->assertSession()->fieldDisabled('edit-uninstall-lingotek');

    $this->clickLink('Remove lingotek content metadata entities');
    $this->assertText('Are you sure you want to delete all lingotek content metadata entities?');
    $this->assertText('This will delete 1 lingotek content metadata.');
    $this->drupalPostForm(NULL, [], 'Delete all lingotek content metadata entities');

    $this->assertFalse($this->getSession()->getPage()->findField('edit-uninstall-lingotek')->hasAttribute('disabled'));

    // Post the form uninstalling the lingotek module.
    $edit = ['uninstall[lingotek]' => '1'];
    $this->drupalPostForm(NULL, $edit, 'Uninstall');

    // We get an advice and we can confirm.
    $this->assertText('The following modules will be completely uninstalled from your site, and all data from these modules will be lost!');
    $this->assertText('The listed configuration will be deleted.');
    $this->assertText('Lingotek Profile');

    $this->drupalPostForm(NULL, [], 'Uninstall');

    $this->assertText('The selected modules have been uninstalled.');
  }

  /**
   * Helper method for creating and downloading a translation.
   */
  protected function createAndDownloadANodeTranslation() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $this->clickLink('Translate');
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The manual profile was used.');

    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    $this->clickLink('Check translation status');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));
  }

}
