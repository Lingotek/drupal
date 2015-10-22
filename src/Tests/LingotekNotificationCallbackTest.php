<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests translating a node using the notification callback.
 *
 * @group lingotek
 */
class LingotekNotificationCallbackTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  /**
   * @var NodeInterface
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
  public function testNodeTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    /** @var NodeInterface $node */
    $node = Node::load(1);
    /** @var LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    // Go to the bulk node management page.
    $this->drupalGet('admin/lingotek/manage/node');

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'complete' => 'false',
      'type' => 'document_uploaded',
      'progress' => '0',
    ]]), 'application/json', []);
    $response = json_decode($request, true);
    $this->assertIdentical(['es'], $response['result']['request_translations'], 'Spanish language has been requested after notification automatically.');

    // Go to the bulk node management page.
    $this->drupalGet('admin/lingotek/manage/node');

    $node_storage = $this->container->get('entity.manager')->getStorage('node');
    // The node cache needs to be reset before reload.
    $node_storage->resetCache(array(1));
    $node = $node_storage->load(1);

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    // Go to the bulk node management page.
    $this->drupalGet('admin/lingotek/manage/node');

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
    $response = json_decode($request, true);
    $this->verbose($request);

    // Go to the bulk node management page.
    $this->drupalGet('admin/lingotek/manage/node');

    $node_storage = $this->container->get('entity.manager')->getStorage('node');
    // The node cache needs to be reset before reload.
    $node_storage->resetCache(array(1));
    $node = $node_storage->load(1);

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'es'));

    // Go to the bulk node management page.
    $this->drupalGet('admin/lingotek/manage/node');

  }

}
