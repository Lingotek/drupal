<?php

namespace Drupal\Tests\lingotek\Functional\Actions;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\system\Entity\Action;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests for Lingotek actions creation.
 *
 * @group lingotek
 */
class LingotekActionsTest extends LingotekTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['action', 'taxonomy', 'node'];

  /**
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->vocabulary = $this->createVocabulary();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $this->vocabulary->id())->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $this->vocabulary->id(), TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();
  }

  /**
   * Tests that a node can be deleted in the management page.
   */
  public function testActionsCreatedWhenEnablingTranslations() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // The default actions are loaded for node, user and roles.
    $actions = Action::loadMultiple();
    $default_actions = [
      0 => 'node_delete_action',
      1 => 'node_make_sticky_action',
      2 => 'node_make_unsticky_action',
      3 => 'node_promote_action',
      4 => 'node_publish_action',
      5 => 'node_save_action',
      6 => 'node_unpromote_action',
      7 => 'node_unpublish_action',
      8 => 'user_block_user_action',
      9 => 'user_cancel_user_action',
      10 => 'user_unblock_user_action',
      11 => 'user_add_role_action.random_role_id',
      12 => 'user_remove_role_action.random_role_id',
      13 => 'taxonomy_term_publish_action',
      14 => 'taxonomy_term_unpublish_action',
    ];
    $expected = 15;
    $this->assertCount($expected, $actions);

    // Enable Lingotek translation for nodes.
    $this->saveLingotekContentTranslationSettingsForNodeTypes();

    $actions = Action::loadMultiple();
    // We expect 15 initial actions, plus 7 that are the entity bulk Lingotek
    // actions for all targets, plus 5 per each target language.
    $this->assertCount($expected + 7 + 5 + 5, $actions);

    $expectedActions = [
      'node_lingotek_upload_action',
      'node_lingotek_check_upload_action',
      'node_lingotek_request_translations_action',
      'node_lingotek_check_translations_action',
      'node_lingotek_download_translations_action',
      'node_lingotek_cancel_action',
      'node_lingotek_delete_translations_action',
    ];
    $expectedActions += [
      'node_es_lingotek_request_translation_action',
      'node_en_lingotek_request_translation_action',
      'node_es_lingotek_check_translation_action',
      'node_en_lingotek_check_translation_action',
      'node_es_lingotek_download_translation_action',
      'node_en_lingotek_download_translation_action',
      'node_es_lingotek_delete_translation_action',
      'node_en_lingotek_delete_translation_action',
      'node_es_lingotek_cancel_translation_action',
      'node_en_lingotek_cancel_translation_action',
    ];
    foreach ($expectedActions as $expectedAction) {
      $this->assertArrayHasKey($expectedAction, $actions, 'There is an action with id: ' . $expectedAction);
    }
    $this->assertEquals('Request content item translation to Lingotek for Spanish', $actions['node_es_lingotek_request_translation_action']->label());
    $this->assertEquals('Check content item translation status to Lingotek for Spanish', $actions['node_es_lingotek_check_translation_action']->label());
    $this->assertEquals('Download content item translation to Lingotek for Spanish', $actions['node_es_lingotek_download_translation_action']->label());
    $this->assertEquals('Delete content item translation for Spanish', $actions['node_es_lingotek_delete_translation_action']->label());
    $this->assertEquals('Cancel content item translation in Lingotek for Spanish', $actions['node_es_lingotek_cancel_translation_action']->label());

    // Create another language
    ConfigurableLanguage::createFromLangcode('it')->save();
    $expectedActions += [
      'node_it_lingotek_request_translation_action',
      'node_it_lingotek_check_translation_action',
      'node_it_lingotek_download_translation_action',
      'node_it_lingotek_delete_translation_action',
      'node_it_lingotek_cancel_translation_action',
    ];
    $actions = Action::loadMultiple();
    $this->assertCount($expected + 7 + 5 + 5 + 5, $actions);
    foreach ($expectedActions as $expectedAction) {
      $this->assertArrayHasKey($expectedAction, $actions, 'There is an action with id: ' . $expectedAction);
    }

    $this->assertEquals('Request content item translation to Lingotek for Italian', $actions['node_it_lingotek_request_translation_action']->label());
    $this->assertEquals('Check content item translation status to Lingotek for Italian', $actions['node_it_lingotek_check_translation_action']->label());
    $this->assertEquals('Download content item translation to Lingotek for Italian', $actions['node_it_lingotek_download_translation_action']->label());
    $this->assertEquals('Delete content item translation for Italian', $actions['node_it_lingotek_delete_translation_action']->label());
    $this->assertEquals('Cancel content item translation in Lingotek for Italian', $actions['node_it_lingotek_cancel_translation_action']->label());

    // Enable for taxonomy terms.
    $this->saveLingotekContentTranslationSettings([
      'taxonomy_term' => [
        $this->vocabulary->id() => [
          'profiles' => 'manual',
          'fields' => [
            'name' => 1,
            'description' => 1,
          ],
        ],
      ],
    ]);

    $expectedActions += [
      'taxonomy_term_lingotek_upload_action',
      'taxonomy_term_lingotek_check_upload_action',
      'taxonomy_term_lingotek_request_translations_action',
      'taxonomy_term_lingotek_check_translations_action',
      'taxonomy_term_lingotek_download_translations_action',
      'taxonomy_term_lingotek_cancel_action',
      'taxonomy_term_lingotek_delete_action',
    ];
    $expectedActions += [
      'taxonomy_term_es_lingotek_request_translation_action',
      'taxonomy_term_en_lingotek_request_translation_action',
      'taxonomy_term_it_lingotek_request_translation_action',
      'taxonomy_term_es_lingotek_check_translation_action',
      'taxonomy_term_en_lingotek_check_translation_action',
      'taxonomy_term_it_lingotek_check_translation_action',
      'taxonomy_term_es_lingotek_download_translation_action',
      'taxonomy_term_en_lingotek_download_translation_action',
      'taxonomy_term_it_lingotek_download_translation_action',
      'taxonomy_term_es_lingotek_delete_translation_action',
      'taxonomy_term_en_lingotek_delete_translation_action',
      'taxonomy_term_it_lingotek_delete_translation_action',
      'taxonomy_term_es_lingotek_cancel_translation_action',
      'taxonomy_term_en_lingotek_cancel_translation_action',
      'taxonomy_term_it_lingotek_cancel_translation_action',
    ];
    $actions = Action::loadMultiple();
    // We add 7 for roundtrip and 15 for the 3 languages per 5 actions.
    $this->assertCount($expected + 7 + 7 + 5 + 5 + 5 + 15, $actions);
    foreach ($expectedActions as $expectedAction) {
      $this->assertArrayHasKey($expectedAction, $actions, 'There is an action with id: ' . $expectedAction);
    }

    $this->assertEquals('Request taxonomy term translation to Lingotek for Italian', $actions['taxonomy_term_it_lingotek_request_translation_action']->label());
    $this->assertEquals('Check taxonomy term translation status to Lingotek for Italian', $actions['taxonomy_term_it_lingotek_check_translation_action']->label());
    $this->assertEquals('Download taxonomy term translation to Lingotek for Italian', $actions['taxonomy_term_it_lingotek_download_translation_action']->label());
    $this->assertEquals('Delete taxonomy term translation for Italian', $actions['taxonomy_term_it_lingotek_delete_translation_action']->label());
    $this->assertEquals('Cancel taxonomy term translation in Lingotek for Italian', $actions['taxonomy_term_it_lingotek_cancel_translation_action']->label());
  }

}
