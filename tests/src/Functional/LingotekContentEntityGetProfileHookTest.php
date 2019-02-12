<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\comment\Entity\CommentType;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekProfile;

/**
 * Tests the hooks for getting content entity associated profile.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekContentEntityGetProfileHookTest extends LingotekTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'comment', 'node'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    $comment_type = CommentType::create([
      'id' => 'comment',
      'label' => 'Comment',
      'description' => '',
      'target_entity_type_id' => 'node',
    ]);
    $comment_type->save();
    $this->addDefaultCommentField('node', 'article');

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    ContentLanguageSettings::loadByEntityTypeBundle('comment', 'comment')->setLanguageAlterable(FALSE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('comment', 'comment', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'manual',
          'fields' => [
            'title' => 1,
            'body' => 1,
          ],
        ],
      ],
      'comment' => [
        'comment' => [
          'profiles' => 'automatic',
          'fields' => [
            'comment_body' => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that a profile can be overridden before uploading.
   */
  public function testProfileOverrideOnUploadTranslation() {
    $profile1 = LingotekProfile::create([
      'id' => 'group_1',
      'label' => 'Group 1',
      'auto_upload' => TRUE,
      'intelligence_metadata' => [
        'override' => TRUE,
        'use_business_division' => TRUE,
        'business_division' => 'Group 1',
      ],
    ]);
    $profile1->save();

    $profile2 = LingotekProfile::create([
      'id' => 'group_2',
      'label' => 'Group 2',
      'auto_upload' => TRUE,
      'intelligence_metadata' => [
        'override' => TRUE,
        'use_business_division' => TRUE,
        'business_division' => 'Group 2',
      ],
    ]);
    $profile2->save();

    $this->drupalGet("/admin/lingotek/settings/profile/group_1/edit");

    // Create a node with group 1 profile.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'group_1';
    $this->saveAndPublishNodeForm($edit);

    // Save a comment for the group_1 node.
    $edit = [];
    $edit['subject[0][value]'] = 'Group 1 test';
    $edit['comment_body[0][value]'] = 'Group 1 test body';
    $this->drupalPostForm(NULL, $edit, 'Save');

    // Check that the configured fields have been uploaded, but also the one
    // added via the hook.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 1);
    $this->assertTrue(isset($data['comment_body'][0]['value']));
    $this->assertEquals('Group 1', $data['_lingotek_metadata']['_intelligence']['business_division']);

    // Create a node with group 1 profile.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool 2';
    $edit['body[0][value]'] = 'Llamas are very cool 2';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'group_2';
    $this->saveAndPublishNodeForm($edit);

    // Save a comment for the group_1 node.
    $edit = [];
    $edit['subject[0][value]'] = 'Group 2 test';
    $edit['comment_body[0][value]'] = 'Group 2 test body';
    $this->drupalPostForm(NULL, $edit, 'Save');

    // Check that the configured fields have been uploaded, but also the one
    // added via the hook.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 1);
    $this->assertTrue(isset($data['comment_body'][0]['value']));
    $this->assertEquals('Group 2', $data['_lingotek_metadata']['_intelligence']['business_division']);
  }

}
