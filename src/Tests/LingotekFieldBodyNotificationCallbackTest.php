<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\node\Entity\NodeType;

/**
 * Tests translating a content type using the notification callback.
 *
 * @group lingotek
 */
class LingotekFieldBodyNotificationCallbackTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'field_ui', 'dblog'];

  protected function setUp() {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'body');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testAutomatedNotificationFieldTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // Create Article node types.
    $type = $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));
    node_add_body_field($type);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    /** @var LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $entity = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($entity));

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
    $this->goToConfigBulkManagementForm();

    /** @var ConfigEntityStorageInterface $field_storage */
    $field_storage = $this->container->get('entity.manager')->getStorage('field_config');
    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getSourceStatus($entity));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $config_translation_service->getTargetStatus($entity, 'es'));

    $this->goToConfigBulkManagementForm();

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
    $this->goToConfigBulkManagementForm();

    // The field cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'es'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();
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
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'manual',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // Create Article node types.
    // We cannot use drupalCreateContentType(), as it asserts that the last entity
    // created returns SAVED_NEW, but it will return SAVED_UPDATED as we will
    // save the third party settings.
    $type = entity_create('node_type', ['type' => 'article', 'name' => 'Article']);
    $status = $type->save();
    node_add_body_field($type);

    \Drupal::service('router.builder')->rebuild();

    /** @var LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $entity = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');

    // Assert the content is edited, but not auto-uploaded.
    $this->assertIdentical(Lingotek::STATUS_EDITED, $config_translation_service->getSourceStatus($entity));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

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
    $response = json_decode($request, true);

    // Translations are not requested.
    $this->assertIdentical([], $response['result']['request_translations'], 'No translations has been requested after notification automatically.');

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    /** @var ConfigEntityStorageInterface $field_storage */
    $field_storage = $this->container->get('entity.manager')->getStorage('field_config');
    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getSourceStatus($entity));
    // Assert the target is ready to be requested.
    $this->assertIdentical(Lingotek::STATUS_REQUEST, $config_translation_service->getTargetStatus($entity, 'es'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

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
    $this->goToConfigBulkManagementForm();

    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_READY, $config_translation_service->getTargetStatus($entity, 'es'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // Download the translation.
    $this->clickLink('ES');

    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');

    // Assert the target is current.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'es'));
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
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'profile2',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');


    // Create Article node types.
    // We cannot use drupalCreateContentType(), as it asserts that the last entity
    // created returns SAVED_NEW, but it will return SAVED_UPDATED as we will
    // save the third party settings.
    $type = entity_create('node_type', ['type' => 'article', 'name' => 'Article']);
    $status = $type->save();
    node_add_body_field($type);

    \Drupal::service('router.builder')->rebuild();

    /** @var ConfigEntityStorageInterface $field_storage */
    $field_storage = $this->container->get('entity.manager')->getStorage('field_config');

    /** @var LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $entity = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($entity));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

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
    $this->goToConfigBulkManagementForm();

    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getSourceStatus($entity));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $config_translation_service->getTargetStatus($entity, 'es'));
    $this->assertIdentical(Lingotek::STATUS_PENDING, $config_translation_service->getTargetStatus($entity, 'de'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

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
    $this->goToConfigBulkManagementForm();

    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_READY, $config_translation_service->getTargetStatus($entity, 'es'));
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'de'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $this->clickLink('ES');

    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');
    // Assert the target is current.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'es'));
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getTargetStatus($entity, 'de'));
  }

  /**
   * Tests that there are no automatic requests for disabled languages.
   */
  public function testDisabledLanguagesAreNotRequested() {
    // Add a language.
    $italian = ConfigurableLanguage::createFromLangcode('it');
    $italian->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    $type1 = entity_create('node_type', ['type' => 'article', 'name' => 'Article']);
    $status = $type1->save();
    node_add_body_field($type1);
    $type2 = entity_create('node_type', ['type' => 'page', 'name' => 'Page']);
    $status = $type2->save();
    node_add_body_field($type2);

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    /** @var LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    // Assert the content is importing.
    /** @var ConfigEntityStorageInterface $field_storage */
    $field_storage = $this->container->get('entity.manager')->getStorage('field_config');
    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($entity));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'complete' => 'false',
      'type' => 'document_uploaded',
      'progress' => '0',
    ]]), 'application/json', []);
    $response = json_decode($request, true);
    $this->assertIdentical(['it', 'es'], $response['result']['request_translations'], 'Spanish and Italian languages have been requested after notification automatically.');

    /** @var LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $lingotek_config->disableLanguage($italian);

    // Test with another content.
    /** @var ConfigEntityStorageInterface $field_storage */
    $field_storage = $this->container->get('entity.manager')->getStorage('field_config');
    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.page.body');

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id-1',
      'complete' => 'false',
      'type' => 'document_uploaded',
      'progress' => '0',
    ]]), 'application/json', []);
    $response = json_decode($request, true);
    $this->assertIdentical(['es'], $response['result']['request_translations'], 'Italian language has not been requested after notification automatically because it is disabled.');
  }

}
