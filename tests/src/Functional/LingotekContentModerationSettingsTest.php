<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\taxonomy\Functional\TaxonomyTestTrait;

/**
 * Tests setting up the integration with content moderation.
 *
 * @group lingotek
 */
class LingotekContentModerationSettingsTest extends LingotekTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'taxonomy', 'content_moderation'];

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    $this->vocabulary = $this->createVocabulary();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);

    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $this->vocabulary->id())->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $this->vocabulary->id(), TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $workflow = $this->createEditorialWorkflow();
  }

  /**
   * Tests that the content moderation settings are stored correctly.
   */
  public function testContentModerationSettings() {
    $this->drupalGet('admin/lingotek/settings');

    // We don't have any fields for configuring content moderation until it's
    // enabled.
    $this->assertNoField('node[article][moderation][upload_status]',
      'The field for setting the state when a content should be uploaded does not exist as content moderation is not enabled for this bundle.');
    $this->assertNoField('node[article][moderation][download_transition]',
      'The field for setting the transition that must happen after download does not exist as content moderation is not enabled for this bundle.');

    $this->assertNoField('node[page][moderation][upload_status]',
      'The field for setting the state when a content should be uploaded does not exist as content moderation is not enabled for this bundle.');
    $this->assertNoField('node[page][moderation][download_transition]',
      'The field for setting the transition that must happen after download does not exist as content moderation is not enabled for this bundle.');

    // We show a message and link for enabling it.
    $this->assertText('This entity bundle is not enabled for moderation with content_moderation. You can change its settings here.');
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $this->assertLinkByHref('/admin/config/workflow/workflows', 0);
      $this->assertLinkByHref('/admin/config/workflow/workflows', 1);
    }
    else {
      $this->assertLinkByHref('/admin/structure/types/manage/article/moderation');
      $this->assertLinkByHref('/admin/structure/types/manage/page/moderation');
    }

    // Let's enable it for articles.
    $this->enableModerationThroughUI('article', ['draft', 'needs_review', 'published'], 'draft');

    $this->drupalGet('admin/lingotek/settings');

    // Assert the fields for setting up the integration exist and they have
    // sensible defaults.
    $this->assertField('node[article][moderation][upload_status]',
      'The field for setting the state when a content should be uploaded exists.');
    $this->assertField('node[article][moderation][download_transition]',
      'The field for setting the transition that must happen after download exists.');
    $this->assertOptionSelected('edit-node-article-moderation-upload-status', 'published',
      'The default value is a published one.');
    $this->assertOptionSelected('edit-node-article-moderation-download-transition', 'publish',
      'The default transition is from published to published.');

    // But not for the other content types. There is still a message for configuring.
    $this->assertNoField('node[page][moderation][upload_status]',
      'The field for setting the state when a content should be uploaded does not exist as content moderation is not enabled for this bundle.');
    $this->assertNoField('node[page][moderation][download_transition]',
      'The field for setting the transition that must happen after download does not exist as content moderation is not enabled for this bundle.');
    $this->assertText('This entity bundle is not enabled for moderation with content_moderation. You can change its settings here.');

    if (floatval(\Drupal::VERSION) >= 8.4) {
      $this->assertLinkByHref('/admin/config/workflow/workflows', 0);
    }
    else {
      $this->assertNoLinkByHref('/admin/structure/types/manage/article/moderation');
      $this->assertLinkByHref('/admin/structure/types/manage/page/moderation');
    }

    // Let's save the settings for articles.
    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
          ],
          'moderation' => [
            'upload_status' => 'draft',
            'download_transition' => 'archive',
          ],
        ],
      ],
    ]);

    // Assert the values are saved.
    $this->assertOptionSelected('edit-node-article-moderation-upload-status', 'draft',
      'The desired status for upload is stored correctly.');
    $this->assertOptionSelected('edit-node-article-moderation-download-transition', 'archive',
      'The desired transition after download is stored correctly.');

    // It never existed for taxonomies.
    $this->assertNoField("taxonomy_term[{$this->vocabulary->id()}][moderation][upload_status]",
      'The field for setting the state when a content should be uploaded does not exist as content moderation is not available for this entity type.');
    $this->assertNoField("taxonomy_term[{$this->vocabulary->id()}][moderation][download_transition]",
      'The field for setting the transition that must happen after download does not exist as content moderation is not available for this entity type.');
    $this->assertNoLinkByHref("/admin/structure/taxonomy/manage/{$this->vocabulary->id()}/moderation", 'There is no link to moderation settings in taxonomies as they cannot be moderated.');

    $header = $this->xpath("//details[@id='edit-entity-node']//th[text()='Content moderation']");
    $this->assertEqual(count($header), 1, 'There is a content moderation column for content.');
    $header = $this->xpath("//details[@id='edit-entity-taxonomy-term']//th[text()='Content moderation']");
    $this->assertEqual(count($header), 0, 'There is no content moderation column for taxonomies.');

  }

  /**
   * Enable moderation for a specified content type, using the UI.
   *
   * @param string $content_type_id
   *   Machine name.
   */
  protected function enableModerationThroughUI($content_type_id) {
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $this->drupalGet('/admin/config/workflow/workflows/manage/editorial/type/node');
      $this->assertFieldByName("bundles[$content_type_id]");
      $edit["bundles[$content_type_id]"] = TRUE;
      $this->drupalPostForm(NULL, $edit, t('Save'));
    }
    else {
      $this->drupalGet('admin/structure/types/manage/' . $content_type_id . '/moderation');
      $this->assertFieldByName('workflow');
      $edit['workflow'] = 'editorial';
      $this->drupalPostForm(NULL, $edit, t('Save'));
    }
  }

}
