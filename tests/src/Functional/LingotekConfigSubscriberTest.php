<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests config overrides in settings.php are possible .
 *
 * @group lingotek
 */
class LingotekConfigSubscriberTest extends LingotekTestBase {

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
    $this->drupalPlaceBlock('page_title_block', ['id' => 'block_1', 'label' => 'Title block', 'region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'block_2', 'label' => 'Local tasks block', 'region' => 'content', 'weight' => -10]);

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'block.title');
  }

  public function testDeletingTranslatedConfig() {
    $this->goToConfigBulkManagementForm('block');
    $this->clickLink('EN');

    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertText('Title block uploaded successfully');

    $this->clickLink('ES');
    $this->clickLink('ES');
    $this->clickLink('ES');

    $this->assertText('Translation to es_MX downloaded successfully');

    // Navigate to the Extend page.
    $this->drupalGet('/admin/modules');
    $this->assertSession()->checkboxChecked('modules[block][enable]');

    $this->clickLink('Uninstall');

    // Post the form uninstalling the lingotek module.
    $edit = ['uninstall[block]' => '1'];
    $this->drupalPostForm(NULL, $edit, 'Uninstall');

    // We get an advice and we can confirm.
    $this->assertText('The following modules will be completely uninstalled from your site, and all data from these modules will be lost!');
    $this->assertSession()->responseContains('Block');
    $this->assertSession()->responseContains('The listed configuration will be deleted.');
    $this->assertSession()->responseContains('Lingotek Config Metadata');
    $this->assertSession()->responseContains('block.block_1');

    $this->drupalPostForm(NULL, [], 'Uninstall');

    $this->assertSession()->responseContains('The selected modules have been uninstalled.');
  }

}
