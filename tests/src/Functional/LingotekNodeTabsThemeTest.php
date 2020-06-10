<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests the theme used in the Lingotek tabs for nodes.
 *
 * @group lingotek
 */
class LingotekNodeTabsThemeTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install themes theme.
    $theme_handler = $this->container->get('theme_installer');
    $theme_handler->install(['seven', 'bartik']);
    $this->config('system.theme')
      ->set('default', 'bartik')
      ->save();

    $edit = [];
    $edit['admin_theme'] = 'seven';
    $edit['use_admin_theme'] = TRUE;
    $this->drupalPostForm('admin/appearance', $edit, t('Save configuration'));

    // Place the blocks.
    foreach (['bartik', 'seven'] as $theme) {
      $this->drupalPlaceBlock('page_title_block', [
        'region' => 'content',
        'weight' => -5,
        'theme' => $theme,
      ]);
      $this->drupalPlaceBlock('local_tasks_block', [
        'region' => 'content',
        'weight' => -10,
        'theme' => $theme,
      ]);
      $this->drupalPlaceBlock('current_theme_block', [
        'region' => 'content',
        'weight' => -15,
        'theme' => $theme,
      ]);
    }

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

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
  }

  /**
   * Test the theme used in the translate tab.
   */
  public function testThemeTranslateTab() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);
    $this->assertText('Current theme: bartik');

    $this->clickLink('Edit');
    $this->assertText('Current theme: seven');

    $this->clickLink('Manage Translations');
    $this->assertText('Current theme: seven');

    $edit = ['use_admin_theme' => FALSE];
    $this->drupalPostForm('admin/appearance', $edit, t('Save configuration'));

    $this->drupalGet('node/1');
    $this->assertText('Current theme: bartik');

    $this->clickLink('Edit');
    $this->assertText('Current theme: bartik');

    $this->clickLink('Manage Translations');
    $this->assertText('Current theme: bartik');
  }

  /**
   * Test the theme used in the Lingotek metadata tab.
   */
  public function testThemeLingotekMetadataTab() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    // Enable debug operations.
    $this->drupalPostForm('admin/lingotek/settings', [], 'Enable debug operations');

    $this->saveAndPublishNodeForm($edit);
    $this->assertText('Current theme: bartik');

    $this->clickLink('Edit');
    $this->assertText('Current theme: seven');

    $this->clickLink('Lingotek Metadata');
    $this->assertText('Current theme: seven');

    $edit = ['use_admin_theme' => FALSE];
    $this->drupalPostForm('admin/appearance', $edit, t('Save configuration'));

    $this->drupalGet('node/1');
    $this->assertText('Current theme: bartik');

    $this->clickLink('Edit');
    $this->assertText('Current theme: bartik');

    $this->clickLink('Lingotek Metadata');
    $this->assertText('Current theme: bartik');
  }

}
