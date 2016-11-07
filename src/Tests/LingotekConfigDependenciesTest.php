<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;

/**
 * Class LingotekConfigDependenciesTest
 *
 * @package Drupal\lingotek\Tests
 * @group lingotek
 */
class LingotekConfigDependenciesTest extends LingotekTestBase {

  /**
   * {@inheritDoc}
   */
  public static $modules = ['lingotek', 'block', 'node', 'field_ui'];

  public function testExportingConfigDependencies() {

    /** @var LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'highlighted']);
    $this->drupalPlaceBlock('page_title_block', ['region' => 'highlighted']);

    // Create a content type.
    $content_type = $this->drupalCreateContentType(['type' => 'article', 'label' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Configure translatability of nodes.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    // Go to the settings page.
    $this->drupalGet('admin/lingotek/settings');
    $this->assertResponse(200);

    // Set up articles for translation.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'manual',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // Set up node types and node fields for translation.
    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'manual',
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // Go to config translation.
    $this->goToConfigBulkManagementForm('node_type');

    // Upload article content type for translation.
    $this->clickLink('EN');
    $this->assertText('article uploaded successfully');
    $this->assertEqual(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($content_type));

    $field = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');
    // Go to config translation.
    $this->goToConfigBulkManagementForm('node_fields');

    // Upload article body field type for translation.
    $this->clickLink('EN');
    $this->assertText('Body uploaded successfully');
    $this->assertEqual(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($field));


    // Copy all configuration to staging.
    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));

    // Delete the article content type.
    $content_type->delete();

    // Article doesn't exist anymore.
    $type = \Drupal::entityManager()->getStorage('node_type')->load('article');
    $this->assertNull($type, 'Article doesn\'t exist anymore');

    $field = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');
    $this->assertNull($field, 'Article Body doesn\'t exist anymore');

    // Import the config so everything should come back.
    $this->configImporter()->import();

    // Article is back.
    $type = \Drupal::entityManager()->getStorage('node_type')->load('article');
    $this->assertNotNull($type, 'Article is back');
    $this->assertEqual(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($type));


    // The Field is back.
    $field = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');
    $this->assertNotNull($field, 'Article Body is back');
    $this->assertEqual(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($field));
  }

}
