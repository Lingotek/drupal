<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node using the notification callback.
 *
 * @group lingotek
 */
class LingotekNodeNotificationCallbackTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

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
   * Tests that a node can be translated using the links on the management page.
   */
  public function testAutomatedNotificationNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'complete' => 'false',
      'type' => 'document_uploaded',
      'progress' => '0',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);
    $this->verbose($request);
    $this->assertIdentical(['es'], $response['result']['request_translations'], 'Spanish language has been requested after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    $this->goToContentBulkManagementForm();

    // Simulate the notification of content successfully translated.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'locale_code' => 'es-ES',
      'locale' => 'es_ES',
      'complete' => 'true',
      'type' => 'target',
      'progress' => '100',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);
    $this->verbose($request);
    $this->assertTrue($response['result']['download'], 'Spanish language has been downloaded after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'es'));

    $this->goToContentBulkManagementForm();

  }

  /**
   * Tests that a node reacts to a phase notification using the links on the management page.
   */
  public function testPhaseNotificationNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();

    // Ensure we won't get a completed document because there are phases pending.
    \Drupal::state()->set('lingotek.document_completion', FALSE);

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'locale_code' => 'es-ES',
      'locale' => 'es_ES',
      'complete' => 'true',
      'type' => 'phase',
      'progress' => '100',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);
    $this->assertTrue($response['result']['download'], 'Spanish language has been downloaded after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is intermediate.
    $this->assertIdentical(Lingotek::STATUS_INTERMEDIATE, $content_translation_service->getTargetStatus($node, 'es'));

    // Assert a translation has been downloaded.
    $this->drupalGet('node/1/translations');
    $this->assertLink('Las llamas son chulas');

    // There are no phases pending anymore.
    \Drupal::state()->set('lingotek.document_completion', TRUE);

    $this->goToContentBulkManagementForm();

    // Simulate the notification of content successfully translated.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'locale_code' => 'es-ES',
      'locale' => 'es_ES',
      'complete' => 'true',
      'type' => 'target',
      'progress' => '100',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);
    $this->verbose($request);
    $this->assertTrue($response['result']['download'], 'Spanish language has been downloaded after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'es'));

    $this->goToContentBulkManagementForm();
  }


  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testManualNotificationNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    /** @var NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is edited, but not auto-uploaded.
    $this->assertIdentical(Lingotek::STATUS_EDITED, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();
    // Clicking English must init the upload of content.
    $this->clickLink('EN');

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'complete' => 'false',
      'type' => 'document_uploaded',
      'progress' => '0',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);

    // Translations are not requested.
    $this->assertIdentical([], $response['result']['request_translations'], 'No translations has been requested after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is ready to be requested.
    $this->assertIdentical(Lingotek::STATUS_REQUEST, $content_translation_service->getTargetStatus($node, 'es'));

    // Go to the bulk node management page and request a translation.
    $this->goToContentBulkManagementForm();
    $this->clickLink('ES');

    // Simulate the notification of content successfully translated.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'locale_code' => 'es-ES',
      'locale' => 'es_ES',
      'complete' => 'true',
      'type' => 'target',
      'progress' => '100',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);
    $this->verbose($request);
    $this->assertFalse($response['result']['download'], 'No translations has been downloaded after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));

    // Go to the bulk node management page and download them.
    $this->goToContentBulkManagementForm();
    $this->clickLink('ES');

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is current.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'es'));
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testProfileTargetOverridesNotificationNodeTranslation() {
    $profile = LingotekProfile::create(['id' => 'profile2', 'label' => 'Profile with overrides', 'auto_upload' => TRUE, 'auto_download' => TRUE,
      'language_overrides' => ['es' => ['overrides' => 'custom', 'custom' => ['auto_download' => FALSE]]]]);
    $profile->save();

    ConfigurableLanguage::createFromLangcode('de')->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'profile2';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    /** @var NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'complete' => 'false',
      'type' => 'document_uploaded',
      'progress' => '0',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);
    $this->assertIdentical(['de', 'es'], $response['result']['request_translations'], 'Spanish and German language has been requested after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'de'));

    $this->goToContentBulkManagementForm();

    // Simulate the notification of content successfully translated.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'locale_code' => 'es-ES',
      'locale' => 'es_ES',
      'complete' => 'true',
      'type' => 'target',
      'progress' => '100',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);
    $this->verbose($request);
    $this->assertFalse($response['result']['download'], 'No translations has been downloaded after notification automatically.');

    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'locale_code' => 'de-DE',
      'locale' => 'de_DE',
      'complete' => 'true',
      'type' => 'target',
      'progress' => '100',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);
    $this->verbose($request);
    $this->assertTrue($response['result']['download'], 'German language has been downloaded after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'de'));

    // Go to the bulk node management page and download them.
    $this->goToContentBulkManagementForm();
    $this->clickLink('ES');

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is current.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'de'));
  }

  /**
   * Resets node and metadata storage caches and reloads the node.
   *
   * @return NodeInterface
   *   The node.
   */
  protected function resetStorageCachesAndReloadNode() {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity.manager')->getStorage('node');
    /** @var \Drupal\Core\Entity\EntityStorageInterface; $metadata_storage */
    $metadata_storage = $this->container->get('entity.manager')->getStorage('lingotek_content_metadata');

    // The node and the metadata caches need to be reset before reload.
    $metadata_storage->resetCache(array(1));
    $node_storage->resetCache(array(1));

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load(1);
    return $node;
  }

}
