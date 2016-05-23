<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;

/**
 * Tests translating a content type using the notification callback.
 *
 * @group lingotek
 */
class LingotekContentTypeNotificationCallbackTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  protected function setUp() {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testAutomatedNotificationContentTypeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // Create Article node types. We use the form at least once to ensure that
    // we don't break anything. E.g. see https://www.drupal.org/node/2645202.
    $this->drupalPostForm('/admin/structure/types/add', ['type' => 'article', 'name' => 'Article'], 'Save content type');

    /** @var LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $entity = \Drupal::entityManager()->getStorage('node_type')->load('article');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($entity));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');
    $edit = [
      'filters[wrapper][bundle]' => 'node_type',  // Content types.
    ];
    $this->drupalPostForm(NULL, $edit, t('Filter'));

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'complete' => 'false',
      'type' => 'document_uploaded',
      'progress' => '0',
    ]]), 'application/json', []);
    $response = json_decode($request, true);
    $this->verbose($request);
    $this->assertIdentical(['es'], $response['result']['request_translations'], 'Spanish language has been requested after notification automatically.');

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    /** @var ConfigEntityStorageInterface $node_storage */
    $node_storage = $this->container->get('entity.manager')->getStorage('node_type');
    // The node cache needs to be reset before reload.
    $node_storage->resetCache();
    $entity = $node_storage->load('article');

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getSourceStatus($entity));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $config_translation_service->getTargetStatus($entity, 'es'));

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
    $this->assertTrue($response['result']['download'], 'Spanish language has been downloaded after notification automatically.');

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    // The node cache needs to be reset before reload.
    $node_storage->resetCache();
    $entity = $node_storage->load('article');

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'es'));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testManualNotificationContentTypeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'manual',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // Create Article node types.
    // We cannot use drupalCreateContentType(), as it asserts that the last entity
    // created returns SAVED_NEW, but it will return SAVED_UPDATED as we will
    // save the third party settings.
    $type = entity_create('node_type', ['type' => 'article', 'name' => 'Article']);
    $status = $type->save();
    \Drupal::service('router.builder')->rebuild();

    /** @var LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $entity = \Drupal::entityManager()->getStorage('node_type')->load('article');

    // Assert the content is edited, but not auto-uploaded.
    $this->assertIdentical(Lingotek::STATUS_EDITED, $config_translation_service->getSourceStatus($entity));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');
    $edit = [
      'filters[wrapper][bundle]' => 'node_type',  // Content types.
    ];
    $this->drupalPostForm(NULL, $edit, t('Filter'));

    // Clicking English must init the upload of content.
    $this->clickLink('English');

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'complete' => 'false',
      'type' => 'document_uploaded',
      'progress' => '0',
    ]]), 'application/json', []);
    $response = json_decode($request, true);

    // Translations are not requested.
    $this->assertIdentical([], $response['result']['request_translations'], 'No translations has been requested after notification automatically.');

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    /** @var ConfigEntityStorageInterface $node_storage */
    $node_storage = $this->container->get('entity.manager')->getStorage('node_type');
    // The node cache needs to be reset before reload.
    $node_storage->resetCache();
    $entity = $node_storage->load('article');

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getSourceStatus($entity));
    // Assert the target is ready to be requested.
    $this->assertIdentical(Lingotek::STATUS_REQUEST, $config_translation_service->getTargetStatus($entity, 'es'));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');
    // Request a translation.
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
    $response = json_decode($request, true);
    $this->verbose($request);
    $this->assertFalse($response['result']['download'], 'No translations has been downloaded after notification automatically.');

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    // The node cache needs to be reset before reload.
    $node_storage->resetCache();
    $entity = $node_storage->load('article');

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_READY, $config_translation_service->getTargetStatus($entity, 'es'));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');
    // Download the translation.
    $this->clickLink('ES');

    // The node cache needs to be reset before reload.
    $node_storage->resetCache();
    $entity = $node_storage->load('article');

    // Assert the target is current.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'es'));
  }

  /**
   * Tests that a node type reacts to a phase notification using the links on the management page.
   */
  public function testPhaseNotificationContentTypeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // Create Article node types. We use the form at least once to ensure that
    // we don't break anything. E.g. see https://www.drupal.org/node/2645202.
    $this->drupalPostForm('/admin/structure/types/add', ['type' => 'article', 'name' => 'Article'], 'Save content type');

    /** @var LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $entity = \Drupal::entityManager()->getStorage('node_type')->load('article');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($entity));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');
    $edit = [
      'filters[wrapper][bundle]' => 'node_type',  // Content types.
    ];
    $this->drupalPostForm(NULL, $edit, t('Filter'));

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
    $response = json_decode($request, true);
    $this->assertTrue($response['result']['download'], 'Spanish language has been downloaded after notification automatically.');

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    /** @var ConfigEntityStorageInterface $node_storage */
    $node_storage = $this->container->get('entity.manager')->getStorage('node_type');
    // The node cache needs to be reset before reload.
    $node_storage->resetCache();
    $entity = $node_storage->load('article');

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getSourceStatus($entity));
    // Assert the target is intermediate.
    $this->assertIdentical(Lingotek::STATUS_INTERMEDIATE, $config_translation_service->getTargetStatus($entity, 'es'));

    // Assert a translation has been downloaded.
    $this->drupalGet('admin/structure/types/manage/article/translate');
    $this->assertLinkByHref('admin/structure/types/manage/article/translate/es/edit');

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    // There are no phases pending anymore.
    \Drupal::state()->set('lingotek.document_completion', TRUE);

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
    $this->assertTrue($response['result']['download'], 'Spanish language has been downloaded after notification automatically.');

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    /** @var ConfigEntityStorageInterface $node_storage */
    $node_storage = $this->container->get('entity.manager')->getStorage('node_type');
    // The node cache needs to be reset before reload.
    $node_storage->resetCache();
    $entity = $node_storage->load('article');

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'es'));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');
  }


  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testProfileTargetOverridesNotificationContentTypeTranslation() {
    $profile = LingotekProfile::create(['id' => 'profile2', 'label' => 'Profile with overrides', 'auto_upload' => TRUE,'auto_download' => TRUE,
      'language_overrides' => ['es' => ['overrides' => 'custom', 'custom' => ['auto_download' => FALSE]]]]);
    $profile->save();

    ConfigurableLanguage::createFromLangcode('de')->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'profile2',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');


    // Create Article node types.
    // We cannot use drupalCreateContentType(), as it asserts that the last entity
    // created returns SAVED_NEW, but it will return SAVED_UPDATED as we will
    // save the third party settings.
    $type = entity_create('node_type', ['type' => 'article', 'name' => 'Article']);
    $status = $type->save();
    \Drupal::service('router.builder')->rebuild();

    /** @var ConfigEntityStorageInterface $node_storage */
    $node_storage = $this->container->get('entity.manager')->getStorage('node_type');

    /** @var LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $entity = \Drupal::entityManager()->getStorage('node_type')->load('article');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($entity));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');
    $edit = [
      'filters[wrapper][bundle]' => 'node_type',  // Content types.
    ];
    $this->drupalPostForm(NULL, $edit, t('Filter'));

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'complete' => 'false',
      'type' => 'document_uploaded',
      'progress' => '0',
    ]]), 'application/json', []);
    $response = json_decode($request, true);
    $this->assertIdentical(['de', 'es'], $response['result']['request_translations'], 'Spanish and German language has been requested after notification automatically.');

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    // The node cache needs to be reset before reload.
    $node_storage->resetCache();
    $entity = $node_storage->load('article');

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getSourceStatus($entity));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $config_translation_service->getTargetStatus($entity, 'es'));
    $this->assertIdentical(Lingotek::STATUS_PENDING, $config_translation_service->getTargetStatus($entity, 'de'));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

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
    $response = json_decode($request, true);
    $this->verbose($request);
    $this->assertTrue($response['result']['download'], 'German language has been downloaded after notification automatically.');

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');

    // The node cache needs to be reset before reload.
    $node_storage->resetCache();
    $entity = $node_storage->load('article');

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_READY, $config_translation_service->getTargetStatus($entity, 'es'));
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'de'));

    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');
    $this->clickLink('ES');

    // The node cache needs to be reset before reload.
    $node_storage->resetCache();
    $entity = $node_storage->load('article');
    // Assert the target is current.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'es'));
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'de'));
  }

}
