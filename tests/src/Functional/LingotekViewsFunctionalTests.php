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
   *
   * Use 'classy' here, as we depend on views classesa added there.
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();

    \Drupal::getContainer()->get('module_installer')->install(['lingotek_views_test'], TRUE);
  }

  public function testNodeWithMetadataView() {
    $assert_session = $this->assertSession();
    $this->drupalGet('lingotek/views/node_and_lingotek_metadata');
    $assert_session->elementTextContains('css', 'h1.page-title', 'Node view with metadata relationship');
    $assert_session->elementExists('css', '.view-node-and-lingotek-metadata');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->drupalGet('lingotek/views/node_and_lingotek_metadata');

    $assert_session->elementExists('css', '.view-node-and-lingotek-metadata');
    $assert_session->linkExists('Llamas are cool');
    $assert_session->elementTextContains('css', 'td.views-field-translation-source', 'EN');
    $assert_session->elementTextNotContains('css', 'td.views-field-document-id', 'dummy-document-hash-id');
    $assert_session->elementTextNotContains('css', 'td.views-field-translation-status-value', 'EN');
    $assert_session->linkExists('Manual');

    $this->saveAndKeepPublishedNodeForm(['lingotek_translation_management[lingotek_translation_profile]' => 'automatic'], 1);

    $this->drupalGet('lingotek/views/node_and_lingotek_metadata');

    $assert_session->elementExists('css', '.view-node-and-lingotek-metadata');
    $assert_session->linkExists('Llamas are cool');
    $assert_session->elementTextContains('css', 'td.views-field-translation-source', 'EN');
    $assert_session->elementTextContains('css', 'td.views-field-document-id', 'dummy-document-hash-id');
    $assert_session->elementTextNotContains('css', 'td.views-field-translation-status-value', 'EN');
    $assert_session->elementTextContains('css', 'td.views-field-translation-status-value', 'ES');
    $assert_session->linkExists('Automatic');
  }

  public function testMetadataView() {
    $assert_session = $this->assertSession();
    $this->drupalGet('lingotek/views/lingotek_metadata');
    $assert_session->elementTextContains('css', 'h1.page-title', 'Lingotek Metadata view');
    $assert_session->elementExists('css', '.view-lingotek-metadata');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->drupalGet('lingotek/views/lingotek_metadata');

    $assert_session->elementExists('css', '.view-lingotek-metadata');
    $assert_session->elementTextContains('css', 'td.views-field-translation-source', 'EN');
    $assert_session->elementTextNotContains('css', 'td.views-field-document-id', 'dummy-document-hash-id');
    $assert_session->elementTextNotContains('css', 'td.views-field-translation-status-value', 'EN');
    $assert_session->linkExists('Manual');

    $this->saveAndKeepPublishedNodeForm(['lingotek_translation_management[lingotek_translation_profile]' => 'automatic'], 1);

    $this->drupalGet('lingotek/views/lingotek_metadata');

    $assert_session->elementExists('css', '.view-lingotek-metadata');
    $assert_session->elementTextContains('css', 'td.views-field-translation-source', 'EN');
    $assert_session->elementTextContains('css', 'td.views-field-document-id', 'dummy-document-hash-id');
    $assert_session->elementTextNotContains('css', 'td.views-field-translation-status-value', 'EN');
    $assert_session->elementTextContains('css', 'td.views-field-translation-status-value', 'ES');
    $assert_session->linkExists('Automatic');
  }

}
