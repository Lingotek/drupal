<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests the views integrations for Lingotek.
 *
 * @group lingotek
 */
class LingotekViewsFunctionalTests extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_tasks_block', [
      'region' => 'content',
      'weight' => -10,
    ]);

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
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();

    \Drupal::getContainer()->get('module_installer')->install(['lingotek_views_test'], TRUE);
  }

  public function testNodeWithMetadataView() {
    $this->drupalGet('lingotek/views/node_and_lingotek_metadata');
    $this->assertSession()->elementTextContains('css', 'h1.page-title', 'Node view with metadata relationship');
    $this->assertSession()->elementExists('css', '.view-node-and-lingotek-metadata');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->drupalGet('lingotek/views/node_and_lingotek_metadata');

    $this->assertSession()->elementExists('css', '.view-node-and-lingotek-metadata');
    $this->assertLink('Llamas are cool');
    $this->assertSession()->elementTextContains('css', 'td.views-field-translation-source', 'EN');
    $this->assertSession()->elementTextNotContains('css', 'td.views-field-document-id', 'dummy-document-hash-id');
    $this->assertSession()->elementTextNotContains('css', 'td.views-field-translation-status-value', 'EN');
    $this->assertLink('Manual');

    $this->saveAndKeepPublishedNodeForm(['lingotek_translation_profile' => 'automatic'], 1);

    $this->drupalGet('lingotek/views/node_and_lingotek_metadata');

    $this->assertSession()->elementExists('css', '.view-node-and-lingotek-metadata');
    $this->assertLink('Llamas are cool');
    $this->assertSession()->elementTextContains('css', 'td.views-field-translation-source', 'EN');
    $this->assertSession()->elementTextContains('css', 'td.views-field-document-id', 'dummy-document-hash-id');
    $this->assertSession()->elementTextNotContains('css', 'td.views-field-translation-status-value', 'EN');
    $this->assertSession()->elementTextContains('css', 'td.views-field-translation-status-value', 'ES');
    $this->assertLink('Automatic');
  }

  public function testMetadataView() {
    $this->drupalGet('lingotek/views/lingotek_metadata');
    $this->assertSession()->elementTextContains('css', 'h1.page-title', 'Lingotek Metadata view');
    $this->assertSession()->elementExists('css', '.view-lingotek-metadata');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->drupalGet('lingotek/views/lingotek_metadata');

    $this->assertSession()->elementExists('css', '.view-lingotek-metadata');
    $this->assertSession()->elementTextContains('css', 'td.views-field-translation-source', 'EN');
    $this->assertSession()->elementTextNotContains('css', 'td.views-field-document-id', 'dummy-document-hash-id');
    $this->assertSession()->elementTextNotContains('css', 'td.views-field-translation-status-value', 'EN');
    $this->assertLink('Manual');

    $this->saveAndKeepPublishedNodeForm(['lingotek_translation_profile' => 'automatic'], 1);

    $this->drupalGet('lingotek/views/lingotek_metadata');

    $this->assertSession()->elementExists('css', '.view-lingotek-metadata');
    $this->assertSession()->elementTextContains('css', 'td.views-field-translation-source', 'EN');
    $this->assertSession()->elementTextContains('css', 'td.views-field-document-id', 'dummy-document-hash-id');
    $this->assertSession()->elementTextNotContains('css', 'td.views-field-translation-status-value', 'EN');
    $this->assertSession()->elementTextContains('css', 'td.views-field-translation-status-value', 'ES');
    $this->assertLink('Automatic');
  }

}
