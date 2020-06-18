<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the Lingotek integrations settings form with paragraphs.
 *
 * @group lingotek
 */
class LingotekSettingsTabParagraphsIntegrationFormTest extends LingotekTestBase {

  use TaxonomyTestTrait;

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'node',
    'image',
    'paragraphs',
    'lingotek_paragraphs_test',
    'taxonomy',
  ];

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

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    $this->vocabulary = $this->createVocabulary();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $this->vocabulary->id())
      ->setLanguageAlterable(TRUE)
      ->save();
    ContentLanguageSettings::loadByEntityTypeBundle('paragraph', 'image_text')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);
    \Drupal::service('content_translation.manager')
      ->setEnabled('paragraph', 'image_text', TRUE);
    \Drupal::service('content_translation.manager')
      ->setEnabled('taxonomy_term', $this->vocabulary->id(), TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
          ],
        ],
      ],
      'paragraph' => [
        'image_text' => [
          'fields' => [
            'field_image_demo' => ['title', 'alt'],
            'field_text_demo' => 1,
          ],
        ],
      ],
    ]);

    // Login as admin.
    $this->drupalLogin($this->rootUser);
  }

  /**
   * Test that if there are no integration settings, there is no tab at all.
   */
  public function testTabShownIfThereAreSettings() {
    $this->drupalGet('admin/lingotek/settings');
    $this->assertText('Integrations Settings');
    $this->assertText('Paragraphs');
    $this->assertText('Enable paragraphs to be managed individually instead of embedded in their parent entity.');
  }

  /**
   * Test that the bulk management form doesn't have a tab for paragraphs.
   */
  public function testBulkTabNotShownIfNotActive() {
    $assert_session = $this->assertSession();

    $this->goToContentBulkManagementForm();
    $assert_session->linkNotExists('Paragraph');
  }

  /**
   * Test that we can enable the tab for paragraphs in bulk management form.
   */
  public function testBulkTabCanBeActivated() {
    $assert_session = $this->assertSession();

    // Activate the settings tab.
    $this->drupalGet('admin/lingotek/settings');
    $edit = ['contrib[paragraphs][enable_bulk_management]' => 1];
    $this->drupalPostForm(NULL, $edit, 'Save settings', [], 'lingoteksettings-integrations-form');
    $this->assertText('The configuration options have been saved.');

    // Now the tab is active.
    $this->goToContentBulkManagementForm();
    $assert_session->linkExists('Paragraph');

    $this->clickLink('Paragraph');
    $this->assertText('Manage Translations');
    $this->assertText('No content available');
  }

  /**
   * Test that we can disable the tab for paragraphs in bulk management form.
   */
  public function testBulkTabCanBeDeactivated() {
    $assert_session = $this->assertSession();

    // Activate the settings tab.
    $this->testBulkTabCanBeActivated();

    // Disable the settings tab.
    $this->drupalGet('admin/lingotek/settings');
    $edit = ['contrib[paragraphs][enable_bulk_management]' => FALSE];
    $this->drupalPostForm(NULL, $edit, 'Save settings', [], 'lingoteksettings-integrations-form');
    $this->assertText('The configuration options have been saved.');

    // Now the tab is not shown.
    $this->goToContentBulkManagementForm();
    $assert_session->linkNotExists('Paragraph');
  }

  /**
   * Test that all tabs are shown.
   */
  public function testOtherBulkTabsAreShownAfterDeactivating() {
    $assert_session = $this->assertSession();

    $this->testBulkTabCanBeDeactivated();
    $bundle = $this->vocabulary->id();
    $this->saveLingotekContentTranslationSettings([
      'taxonomy_term' => [
        $bundle => [
          'profiles' => 'automatic',
          'fields' => [
            'name' => 1,
            'description' => 1,
          ],
        ],
      ],
    ]);

    // Now the taxonomy tab should be shown.
    $this->goToContentBulkManagementForm();
    $assert_session->statusCodeEquals(200);
    $assert_session->linkExists('Content');
    $assert_session->linkNotExists('Paragraph');
    $assert_session->linkExists('Taxonomy term');

    $this->goToContentBulkManagementForm('taxonomy_term');
    $assert_session->statusCodeEquals(200);
    $assert_session->linkExists('Content');
    $assert_session->linkNotExists('Paragraph');
    $assert_session->linkExists('Taxonomy term');
  }

  public function testParagraphsProfileIsNotSelectableUnlessExplicit() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');

    $this->assertNoFieldByName('paragraph[image_text][profiles]', NULL, 'The profile is not selectable for paragraphs by default.');

    $edit = ['contrib[paragraphs][enable_bulk_management]' => 1];
    $this->drupalPostForm(NULL, $edit, 'Save settings', [], 'lingoteksettings-integrations-form');
    $this->assertText('The configuration options have been saved.');

    $this->assertFieldByName('paragraph[image_text][profiles]', NULL, 'The profile can be assigned to a paragraph if they are managed individually.');
    $this->assertFieldByName('paragraph[image_text][profiles]', Lingotek::PROFILE_DISABLED, 'The default profile is disabled for paragraphs if they are managed individually.');
  }

}
