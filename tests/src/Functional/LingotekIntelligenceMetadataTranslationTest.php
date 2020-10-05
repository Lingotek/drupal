<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\Tests\lingotek\Functional\Form\IntelligenceMetadataFormTestTrait;

/**
 * Tests if intelligence metadata is used when uploading and updating content.
 *
 * @group lingotek
 */
class LingotekIntelligenceMetadataTranslationTest extends LingotekTestBase {

  use IntelligenceMetadataFormTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

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

    // Create Article node types.
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

    $this->setupResources();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
            'uid' => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that a node can be translated.
   */
  public function testUploadNodeWithNoSettings() {
    $this->disableIntelligenceMetadata();

    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['uid']));
    $this->assertFalse(isset($data['uid'][0]['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_type_id'], 'user');
    $this->assertNull($data['uid'][0]['_lingotek_metadata']['_entity_revision']);
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->assertEqual(4, count($data['_lingotek_metadata']));
    $this->assertIdentical($data['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_revision'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_type_id'], 'node');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_document_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_type'], 'node - article');
  }

  public function testUploadNodeWithDefaultSettings() {
    $domain = \Drupal::request()->getSchemeAndHttpHost();

    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['uid']));
    $this->assertFalse(isset($data['uid'][0]['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_type_id'], 'user');
    $this->assertNull($data['uid'][0]['_lingotek_metadata']['_entity_revision']);
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->assertEqual(4, count($data['_lingotek_metadata']));
    $this->assertIdentical($data['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_revision'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_type_id'], 'node');

    $this->assertEqual(17, count($data['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_document_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_type'], 'node - article');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['domain'], $domain);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['reference_url'], $this->node->toUrl()->setAbsolute(TRUE)->toString());
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_name'], 'admin');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_email'], 'admin@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_unit'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_division'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_id'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_rating'], 0);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['channel'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_name'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_email'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_description'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_style_id'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['purchase_order'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['region'], NULL);
  }

  /**
   * Tests that a node can be translated.
   */
  public function testUploadNodeWithGeneralSettings() {
    $domain = \Drupal::request()->getSchemeAndHttpHost();

    $this->setupGeneralIntelligenceSettings();
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['uid']));
    $this->assertFalse(isset($data['uid'][0]['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_type_id'], 'user');
    $this->assertNull($data['uid'][0]['_lingotek_metadata']['_entity_revision']);
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->assertEqual(4, count($data['_lingotek_metadata']));
    $this->assertIdentical($data['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_revision'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_type_id'], 'node');

    $this->assertEqual(17, count($data['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_document_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_type'], 'node - article');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['domain'], $domain);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['reference_url'], $this->node->toUrl()->setAbsolute(TRUE)->toString());
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_name'], 'admin');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_email'], 'admin@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_unit'], 'General Business Unit');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_division'], 'General Business Division');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_id'], 'General Campaign ID');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_rating'], 3);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['channel'], 'General Channel Test');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_name'], 'General Test Contact Name');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_email'], 'general@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_description'], 'General Content description');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_style_id'], 'general-my-style-id');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['purchase_order'], 'General PO32');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['region'], 'region2');
  }

  public function testUploadNodeWithContactEmailAsAuthorSetting() {
    $domain = \Drupal::request()->getSchemeAndHttpHost();

    $this->setupGeneralIntelligenceSettings();
    $this->setupContactEmailForAuthorIntelligenceSettings();
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['uid']));
    $this->assertFalse(isset($data['uid'][0]['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_type_id'], 'user');
    $this->assertNull($data['uid'][0]['_lingotek_metadata']['_entity_revision']);
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->assertEqual(4, count($data['_lingotek_metadata']));
    $this->assertIdentical($data['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_revision'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_type_id'], 'node');

    $this->assertEqual(17, count($data['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_document_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_type'], 'node - article');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['domain'], $domain);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['reference_url'], $this->node->toUrl()->setAbsolute(TRUE)->toString());
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_name'], 'admin');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_email'], 'general@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_unit'], 'General Business Unit');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_division'], 'General Business Division');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_id'], 'General Campaign ID');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_rating'], 3);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['channel'], 'General Channel Test');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_name'], 'General Test Contact Name');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_email'], 'general@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_description'], 'General Content description');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_style_id'], 'general-my-style-id');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['purchase_order'], 'General PO32');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['region'], 'region2');
  }

  public function testUploadNodeWithProfileOverride() {
    $domain = \Drupal::request()->getSchemeAndHttpHost();

    $this->setupIntelligenceProfileSettings();
    $this->setupGeneralIntelligenceSettings();
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'intelligent_profile';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['uid']));
    $this->assertFalse(isset($data['uid'][0]['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_type_id'], 'user');
    $this->assertNull($data['uid'][0]['_lingotek_metadata']['_entity_revision']);
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('intelligent_profile', $used_profile, 'The Intelligent Profile profile was used.');

    $this->assertEqual(4, count($data['_lingotek_metadata']));
    $this->assertIdentical($data['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_revision'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_type_id'], 'node');

    $this->assertEqual(17, count($data['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_document_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_type'], 'node - article');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['domain'], $domain);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['reference_url'], $this->node->toUrl()->setAbsolute(TRUE)->toString());
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_name'], 'admin');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_email'], 'admin@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_unit'], 'Profile Business Unit');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_division'], 'Profile Business Division');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_id'], 'Profile Campaign ID');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_rating'], 4);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['channel'], 'Profile Channel Test');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_name'], 'Profile Test Contact Name');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_email'], 'profile@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_description'], 'Profile Content description');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_style_id'], 'profile-my-style-id');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['purchase_order'], 'Profile PO42');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['region'], 'region2');
  }

  public function testUpdateNodeWithNoSettings() {
    $this->disableIntelligenceMetadata();

    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $edit['body[0][value]'] = 'Llamas are still very cool';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['uid']));
    $this->assertFalse(isset($data['uid'][0]['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_type_id'], 'user');
    $this->assertNull($data['uid'][0]['_lingotek_metadata']['_entity_revision']);
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->assertEqual(4, count($data['_lingotek_metadata']));
    $this->assertIdentical($data['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_revision'], '2');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_type_id'], 'node');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_document_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_type'], 'node - article');
  }

  public function testUpdateNodeWithDefaultSettings() {
    $domain = \Drupal::request()->getSchemeAndHttpHost();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $this->saveAndPublishNodeForm($edit);

    $edit['body[0][value]'] = 'Llamas are still very cool';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['uid']));
    $this->assertFalse(isset($data['uid'][0]['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_type_id'], 'user');
    $this->assertNull($data['uid'][0]['_lingotek_metadata']['_entity_revision']);
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->assertEqual(4, count($data['_lingotek_metadata']));
    $this->assertIdentical($data['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_revision'], '2');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_type_id'], 'node');

    $this->assertEqual(17, count($data['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_document_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_type'], 'node - article');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['domain'], $domain);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['reference_url'], $this->node->toUrl()->setAbsolute(TRUE)->toString());
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_name'], 'admin');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_email'], 'admin@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_unit'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_division'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_id'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_rating'], 0);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['channel'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_name'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_email'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_description'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_style_id'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['purchase_order'], NULL);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['region'], NULL);
  }

  /**
   * Tests that a node can be translated.
   */
  public function testUpdateNodeWithGeneralSettings() {
    $domain = \Drupal::request()->getSchemeAndHttpHost();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $this->setupGeneralIntelligenceSettings();
    $this->drupalLogin($this->rootUser);

    $edit['body[0][value]'] = 'Llamas are still very cool';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['uid']));
    $this->assertFalse(isset($data['uid'][0]['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_type_id'], 'user');
    $this->assertNull($data['uid'][0]['_lingotek_metadata']['_entity_revision']);
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->assertEqual(4, count($data['_lingotek_metadata']));
    $this->assertIdentical($data['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_revision'], '2');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_type_id'], 'node');

    $this->assertEqual(17, count($data['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_document_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_type'], 'node - article');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['domain'], $domain);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['reference_url'], $this->node->toUrl()->setAbsolute(TRUE)->toString());
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_name'], 'admin');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_email'], 'admin@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_unit'], 'General Business Unit');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_division'], 'General Business Division');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_id'], 'General Campaign ID');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_rating'], 3);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['channel'], 'General Channel Test');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_name'], 'General Test Contact Name');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_email'], 'general@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_description'], 'General Content description');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_style_id'], 'general-my-style-id');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['purchase_order'], 'General PO32');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['region'], 'region2');
  }

  public function testUpdateNodeWithContactEmailAsAuthorSetting() {
    $domain = \Drupal::request()->getSchemeAndHttpHost();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $this->saveAndPublishNodeForm($edit);

    $this->setupGeneralIntelligenceSettings();
    $this->setupContactEmailForAuthorIntelligenceSettings();

    $edit['body[0][value]'] = 'Llamas are still very cool';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['uid']));
    $this->assertFalse(isset($data['uid'][0]['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_type_id'], 'user');
    $this->assertNull($data['uid'][0]['_lingotek_metadata']['_entity_revision']);
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    $this->assertEqual(4, count($data['_lingotek_metadata']));
    $this->assertIdentical($data['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_revision'], '2');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_type_id'], 'node');

    $this->assertEqual(17, count($data['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_document_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_type'], 'node - article');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['domain'], $domain);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['reference_url'], $this->node->toUrl()->setAbsolute(TRUE)->toString());
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_name'], 'admin');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_email'], 'general@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_unit'], 'General Business Unit');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_division'], 'General Business Division');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_id'], 'General Campaign ID');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_rating'], 3);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['channel'], 'General Channel Test');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_name'], 'General Test Contact Name');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_email'], 'general@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_description'], 'General Content description');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_style_id'], 'general-my-style-id');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['purchase_order'], 'General PO32');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['region'], 'region2');
  }

  public function testUpdateNodeWithProfileOverride() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';

    $this->saveAndPublishNodeForm($edit);

    $domain = \Drupal::request()->getSchemeAndHttpHost();

    $this->setupIntelligenceProfileSettings();
    $this->setupGeneralIntelligenceSettings();
    $this->drupalLogin($this->rootUser);

    $edit['body[0][value]'] = 'Llamas are still very cool';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'intelligent_profile';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['uid']));
    $this->assertFalse(isset($data['uid'][0]['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['uid'][0]['_lingotek_metadata']['_entity_type_id'], 'user');
    $this->assertNull($data['uid'][0]['_lingotek_metadata']['_entity_revision']);
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('intelligent_profile', $used_profile, 'The Intelligent Profile profile was used.');

    $this->assertEqual(4, count($data['_lingotek_metadata']));
    $this->assertIdentical($data['_lingotek_metadata']['_entity_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_revision'], '2');
    $this->assertIdentical($data['_lingotek_metadata']['_entity_type_id'], 'node');

    $this->assertEqual(17, count($data['_lingotek_metadata']['_intelligence']));
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_document_id'], '1');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_type'], 'node - article');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['domain'], $domain);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['reference_url'], $this->node->toUrl()->setAbsolute(TRUE)->toString());
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_name'], 'admin');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['author_email'], 'admin@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_unit'], 'Profile Business Unit');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['business_division'], 'Profile Business Division');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_id'], 'Profile Campaign ID');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['campaign_rating'], 4);
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['channel'], 'Profile Channel Test');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_name'], 'Profile Test Contact Name');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['contact_email'], 'profile@example.com');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['content_description'], 'Profile Content description');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['external_style_id'], 'profile-my-style-id');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['purchase_order'], 'Profile PO42');
    $this->assertIdentical($data['_lingotek_metadata']['_intelligence']['region'], 'region2');
  }

  protected function disableIntelligenceMetadata() {
    // Check we can store the values.
    $edit = [
      'intelligence_metadata[use_author]' => FALSE,
      'intelligence_metadata[use_author_email]' => FALSE,
      'intelligence_metadata[use_contact_email_for_author]' => FALSE,
      'intelligence_metadata[use_business_unit]' => FALSE,
      'intelligence_metadata[use_business_division]' => FALSE,
      'intelligence_metadata[use_campaign_id]' => FALSE,
      'intelligence_metadata[use_campaign_rating]' => FALSE,
      'intelligence_metadata[use_channel]' => FALSE,
      'intelligence_metadata[use_contact_name]' => FALSE,
      'intelligence_metadata[use_contact_email]' => FALSE,
      'intelligence_metadata[use_content_description]' => FALSE,
      'intelligence_metadata[use_external_style_id]' => FALSE,
      'intelligence_metadata[use_purchase_order]' => FALSE,
      'intelligence_metadata[use_region]' => FALSE,
      'intelligence_metadata[use_base_domain]' => FALSE,
      'intelligence_metadata[use_reference_url]' => FALSE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save Lingotek Intelligence Metadata', [], 'lingotekintelligence-metadata-form');
  }

  protected function setupIntelligenceProfileSettings() {
    $this->drupalGet('admin/lingotek/settings');
    $this->clickLink(t('Add new Translation Profile'));

    $edit = [
      'id' => 'intelligent_profile',
      'label' => 'Intelligent Profile',
      'auto_upload' => 1,
      'auto_download' => 1,
      'intelligence_metadata_overrides[override]' => 1,
      'intelligence_metadata[use_author]' => 1,
      'intelligence_metadata[use_author_email]' => 1,
      'intelligence_metadata[use_contact_email_for_author]' => FALSE,
      'intelligence_metadata[use_business_unit]' => 1,
      'intelligence_metadata[use_business_division]' => 1,
      'intelligence_metadata[use_campaign_id]' => 1,
      'intelligence_metadata[use_campaign_rating]' => 1,
      'intelligence_metadata[use_channel]' => 1,
      'intelligence_metadata[use_contact_name]' => 1,
      'intelligence_metadata[use_contact_email]' => 1,
      'intelligence_metadata[use_content_description]' => 1,
      'intelligence_metadata[use_external_style_id]' => 1,
      'intelligence_metadata[use_purchase_order]' => 1,
      'intelligence_metadata[use_region]' => 1,
      'intelligence_metadata[use_base_domain]' => 1,
      'intelligence_metadata[use_reference_url]' => 1,
      'intelligence_metadata[default_author_email]' => 'test@example.com',
      'intelligence_metadata[business_unit]' => 'Profile Business Unit',
      'intelligence_metadata[business_division]' => 'Profile Business Division',
      'intelligence_metadata[campaign_id]' => 'Profile Campaign ID',
      'intelligence_metadata[campaign_rating]' => 4,
      'intelligence_metadata[channel]' => 'Profile Channel Test',
      'intelligence_metadata[contact_name]' => 'Profile Test Contact Name',
      'intelligence_metadata[contact_email]' => 'profile@example.com',
      'intelligence_metadata[content_description]' => 'Profile Content description',
      'intelligence_metadata[external_style_id]' => 'profile-my-style-id',
      'intelligence_metadata[purchase_order]' => 'Profile PO42',
      'intelligence_metadata[region]' => 'region2',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

  }

  protected function setupGeneralIntelligenceSettings() {
    $this->drupalGet('admin/lingotek/settings');
    $edit = [
      'intelligence_metadata[use_author]' => TRUE,
      'intelligence_metadata[use_author_email]' => TRUE,
      'intelligence_metadata[use_contact_email_for_author]' => FALSE,
      'intelligence_metadata[use_business_unit]' => 1,
      'intelligence_metadata[use_business_division]' => 1,
      'intelligence_metadata[use_campaign_id]' => 1,
      'intelligence_metadata[use_campaign_rating]' => 1,
      'intelligence_metadata[use_channel]' => 1,
      'intelligence_metadata[use_contact_name]' => 1,
      'intelligence_metadata[use_contact_email]' => 1,
      'intelligence_metadata[use_content_description]' => 1,
      'intelligence_metadata[use_external_style_id]' => 1,
      'intelligence_metadata[use_purchase_order]' => 1,
      'intelligence_metadata[use_region]' => 1,
      'intelligence_metadata[use_base_domain]' => 1,
      'intelligence_metadata[use_reference_url]' => 1,
      'intelligence_metadata[default_author_email]' => 'test@example.com',
      'intelligence_metadata[business_unit]' => 'General Business Unit',
      'intelligence_metadata[business_division]' => 'General Business Division',
      'intelligence_metadata[campaign_id]' => 'General Campaign ID',
      'intelligence_metadata[campaign_rating]' => 3,
      'intelligence_metadata[channel]' => 'General Channel Test',
      'intelligence_metadata[contact_name]' => 'General Test Contact Name',
      'intelligence_metadata[contact_email]' => 'general@example.com',
      'intelligence_metadata[content_description]' => 'General Content description',
      'intelligence_metadata[external_style_id]' => 'general-my-style-id',
      'intelligence_metadata[purchase_order]' => 'General PO32',
      'intelligence_metadata[region]' => 'region2',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save Lingotek Intelligence Metadata', [], 'lingotekintelligence-metadata-form');
  }

  protected function setupContactEmailForAuthorIntelligenceSettings() {
    $edit = [
      'intelligence_metadata[use_contact_email_for_author]' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save Lingotek Intelligence Metadata', [], 'lingotekintelligence-metadata-form');
  }

  /**
   * Setup test resources for the test.
   */
  protected function setupResources() {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('account.resources.community', [
      'test_community' => 'Test community',
      'test_community2' => 'Test community 2',
    ]);
    $config->set('account.resources.project', [
      'test_project' => 'Test project',
      'test_project2' => 'Test project 2',
    ]);
    $config->set('account.resources.vault', [
      'test_vault' => 'Test vault',
      'test_vault2' => 'Test vault 2',
    ]);
    $config->set('account.resources.workflow', [
      'test_workflow' => 'Test workflow',
      'test_workflow2' => 'Test workflow 2',
    ]);
    $config->set('account.resources.filter', [
      'test_filter' => 'Test filter',
      'test_filter2' => 'Test filter 2',
      'test_filter3' => 'Test filter 3',
    ]);
    $config->save();
  }

}
