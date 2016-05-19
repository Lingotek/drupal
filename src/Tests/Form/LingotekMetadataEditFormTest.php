<?php

namespace Drupal\lingotek\Tests\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\lingotek\Tests\LingotekTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests the Lingotek metadata form.
 *
 * @group lingotek
 */
class LingotekMetadataEditFormTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');
  }

  /**
   * Tests that if debug is not enabled, metadata tab is not available.
   */
  public function testMetadataLocalTaskNotAvailable() {
    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));
    $this->assertUrl('/node/1', [], 'Node has been created.');

    // The metadata local task should not be visible.
    $this->assertNoLink(t('Lingotek Metadata'));
  }

  /**
   * Tests that if debug is not enabled, metadata tab is not available.
   */
  public function testMetadataLocalTaskAvailable() {
    // Enable debug operations.
    $this->drupalPostForm('admin/lingotek/settings', [], 'Enable debug operations');

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));
    $this->assertUrl('/node/1', [], 'Node has been created.');

    // The metadata local task should be visible.
    $this->drupalGet('/node/1');
    $this->assertLink(t('Lingotek Metadata'));
  }

  /**
   * Tests that if debug is not enabled, metadata tab is not available.
   */
  public function testMetadataEditForm() {
    // Enable debug operations.
    $this->drupalPostForm('admin/lingotek/settings', [], 'Enable debug operations');

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    // The metadata local task should be visible.
    $this->drupalGet('/node/1');
    $this->clickLink(t('Lingotek Metadata'));
    $this->assertUrl('/node/1/metadata', [], 'Metadata local task enables the metadata form.');

    // Assert that the values are correct.
    $this->assertFieldById('edit-lingotek-document-id', 'dummy-document-hash-id');
    $this->assertOptionSelected('edit-lingotek-source-status', Lingotek::STATUS_IMPORTING);
    $this->assertOptionSelected('edit-en', Lingotek::STATUS_IMPORTING);
    $this->assertOptionSelected('edit-es', Lingotek::STATUS_REQUEST);

    $edit = [
      'lingotek_document_id' => 'another-id',
      'lingotek_source_status' => Lingotek::STATUS_UNTRACKED,
      'en' => Lingotek::STATUS_UNTRACKED,
      'es' => Lingotek::STATUS_READY,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save metadata');

    // Assert that the values are correct.
    $this->assertFieldById('edit-lingotek-document-id', 'another-id');
    // ToDo: We should avoid that an upload is triggered, even if using automatic profile.
    // $this->assertOptionSelected('edit-lingotek-source-status', Lingotek::STATUS_CURRENT);
    $this->assertOptionSelected('edit-lingotek-source-status', Lingotek::STATUS_UNTRACKED);
    $this->assertOptionSelected('edit-en', Lingotek::STATUS_UNTRACKED);
    $this->assertOptionSelected('edit-es', Lingotek::STATUS_READY);
    
    /** @var LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $node = Node::load(1);
    // Assert that the values are correct in the service.
    $this->assertIdentical('another-id', $content_translation_service->getDocumentId($node));
    // ToDo: We should avoid that an upload is triggered, even if using automatic profile.
    // $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getTargetStatus($node, 'en'));
    $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));
  }

}