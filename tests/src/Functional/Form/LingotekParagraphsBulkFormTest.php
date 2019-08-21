<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the bulk management form integration with Paragraphs.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekParagraphsBulkFormTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'node',
    'image',
    'paragraphs',
    'paragraphs_demo',
  ];

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

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'paragraphed_content_demo')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'paragraphed_content_demo', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();

    // We need to rebuild routes.
    $this->rebuildAll();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'paragraphed_content_demo' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'field_paragraphs_demo' => 1,
          ],
        ],
      ],
    ]);
    $this->saveLingotekContentTranslationSettings([
      'paragraph' => [
        'image_text' => [
          'fields' => [
            'field_image_demo' => ['title', 'alt'],
            'field_text_demo' => 1,
          ],
        ],
      ],
    ]);
  }

  public function testParagraphsParentShownOnListing() {
    $edit = ['contrib[paragraphs][enable_bulk_management]' => 1];
    $this->drupalPostForm(NULL, $edit, 'Save settings', [], 'lingoteksettings-integrations-form');
    $this->assertText('The configuration options have been saved.');

    $this->goToContentBulkManagementForm('paragraph');

    $this->assertText('Parent');
    $this->assertLink('Welcome to the Paragraphs Demo module!');
    $this->assertLink('Library item');
  }

}
