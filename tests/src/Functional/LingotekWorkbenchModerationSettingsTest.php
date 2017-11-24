<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\taxonomy\Functional\TaxonomyTestTrait;
use Drupal\workbench_moderation\Entity\ModerationState;

/**
 * Tests setting up the integration with workbench moderation.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekWorkbenchModerationSettingsTest extends LingotekTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'taxonomy', 'workbench_moderation'];

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
  }

  /**
   * Tests that the workbench moderation settings are stored correctly.
   */
  public function testWorkbenchModerationSettings() {
    $this->drupalGet('admin/lingotek/settings');

    // We don't have any fields for configuring workbench moderation until it's
    // enabled.
    $this->assertNoField('node[article][moderation][upload_status]',
      'The field for setting the state when a content should be uploaded does not exist as workbench moderation is not enabled for this bundle.');
    $this->assertNoField('node[article][moderation][download_transition]',
      'The field for setting the transition that must happen after download does not exist as workbench moderation is not enabled for this bundle.');

    $this->assertNoField('node[page][moderation][upload_status]',
      'The field for setting the state when a content should be uploaded does not exist as workbench moderation is not enabled for this bundle.');
    $this->assertNoField('node[page][moderation][download_transition]',
      'The field for setting the transition that must happen after download does not exist as workbench moderation is not enabled for this bundle.');

    // We show a message and link for enabling it.
    $this->assertText('This entity bundle is not enabled for moderation with workbench_moderation. You can change its settings here.');
    $this->assertLinkByHref('/admin/structure/types/manage/article/moderation');
    $this->assertLinkByHref('/admin/structure/types/manage/page/moderation');

    // Let's enable it for articles.
    $this->enableModerationThroughUI('article',
      ['draft', 'needs_review', 'published'], 'draft');

    $this->drupalGet('admin/lingotek/settings');

    // Assert the fields for setting up the integration exist and they have
    // sensible defaults.
    $this->assertField('node[article][moderation][upload_status]',
      'The field for setting the state when a content should be uploaded exists.');
    $this->assertField('node[article][moderation][download_transition]',
      'The field for setting the transition that must happen after download exists.');
    $this->assertOptionSelected('edit-node-article-moderation-upload-status', 'published',
      'The default value is a published one.');
    $this->assertOptionSelected('edit-node-article-moderation-download-transition', 'published_published',
      'The default transition is from published to published.');

    // The content types without moderation enabled should show a link instead
    // for configuring them.
    $this->assertNoField('node[page][moderation][upload_status]',
      'The field for setting the state when a content should be uploaded does not exist as workbench moderation is not enabled for this bundle.');
    $this->assertNoField('node[page][moderation][download_transition]',
      'The field for setting the transition that must happen after download does not exist as workbench moderation is not enabled for this bundle.');
    $this->assertText('This entity bundle is not enabled for moderation with workbench_moderation. You can change its settings here.');
    $this->assertNoLinkByHref('/admin/structure/types/manage/article/moderation');
    $this->assertLinkByHref('/admin/structure/types/manage/page/moderation');

    // Let's save the settings for articles.
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][moderation][upload_status]' => 'draft',
      'node[article][moderation][download_transition]' => 'draft_needs_review',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // Assert the values are saved.
    $this->assertOptionSelected('edit-node-article-moderation-upload-status', 'draft',
      'The desired status for upload is stored correctly.');
    $this->assertOptionSelected('edit-node-article-moderation-download-transition', 'draft_needs_review',
      'The desired transition after download is stored correctly.');

    // It never existed for taxonomies.
    $this->assertNoField("taxonomy_term[{$this->vocabulary->id()}][moderation][upload_status]",
      'The field for setting the state when a content should be uploaded does not exist as workbench moderation is not available for this entity type.');
    $this->assertNoField("taxonomy_term[{$this->vocabulary->id()}][moderation][download_transition]",
      'The field for setting the transition that must happen after download does not exist as workbench moderation is not available for this entity type.');
    $this->assertNoLinkByHref("/admin/structure/taxonomy/manage/{$this->vocabulary->id()}/moderation", 'There is no link to moderation settings in taxonomies as they cannot be moderated.');

    $header = $this->xpath("//details[@id='edit-entity-node']//th[text()='Workbench Moderation']");
    $this->assertEqual(count($header), 1, 'There is a Workbench Moderation column for content.');
    $header = $this->xpath("//details[@id='edit-entity-taxonomy-term']//th[text()='Workbench Moderation']");
    $this->assertEqual(count($header), 0, 'There is no Workbench Moderation column for taxonomies.');

  }

  /**
   * Enable moderation for a specified content type, using the UI.
   *
   * @param string $content_type_id
   *   Machine name.
   * @param string[] $allowed_states
   *   Array of allowed state IDs.
   * @param string $default_state
   *   Default state.
   */
  protected function enableModerationThroughUI($content_type_id, array $allowed_states, $default_state) {
    $this->drupalGet('admin/structure/types/manage/' . $content_type_id . '/moderation');
    $this->assertFieldByName('enable_moderation_state');
    $this->assertNoFieldChecked('edit-enable-moderation-state');

    $edit['enable_moderation_state'] = 1;

    /** @var \Drupal\workbench_moderation\Entity\ModerationState $state */
    foreach (ModerationState::loadMultiple() as $id => $state) {
      $key = $state->isPublishedState() ? 'allowed_moderation_states_published[' . $state->id() . ']' : 'allowed_moderation_states_unpublished[' . $state->id() . ']';
      $edit[$key] = (int) in_array($id, $allowed_states);
    }

    $edit['default_moderation_state'] = $default_state;

    $this->drupalPostForm(NULL, $edit, t('Save'));
  }

}
