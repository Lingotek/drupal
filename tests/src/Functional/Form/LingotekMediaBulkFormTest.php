<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests the bulk management form integration with Media.
 *
 * @group lingotek
 */
class LingotekMediaBulkFormTest extends LingotekTestBase {

  use MediaTypeCreationTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'node',
    'image',
    'media',
    'media_test_source',
  ];

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

    $this->createMediaType('image', ['id' => 'image', 'label' => 'Image']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('media', 'image')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('media', 'image', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();

    // We need to rebuild routes.
    $this->rebuildAll();

    $this->saveLingotekContentTranslationSettings([
      'media' => [
        'image' => [
          'fields' => [
            'name' => 1,
            'field_media_image' => ['title', 'alt'],
          ],
        ],
      ],
    ]);
  }

  public function testThumbnailsShownOnListing() {
    $test_image = current($this->getTestFiles('image'));

    // Create a media item.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['files[field_media_image_0]'] = \Drupal::service('file_system')
      ->realpath($test_image->uri);

    $this->drupalPostForm('media/add/image', $edit, 'field_media_image_0_upload_button');

    unset($edit['files[field_media_image_0]']);
    $edit['field_media_image[0][alt]'] = 'Llamas are cool';
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->goToContentBulkManagementForm('media');

    $this->assertText('Thumbnail');
    $elements = $this->xpath("//img[@alt='Llamas are cool']");
    $this->assertEqual(1, count($elements), "Found thumbnail.");
  }

}
