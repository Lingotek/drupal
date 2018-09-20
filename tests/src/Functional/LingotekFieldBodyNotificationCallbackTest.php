<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests translating a content type using the notification callback.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekFieldBodyNotificationCallbackTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'field_ui', 'dblog'];

  /**
   * {@inheritdoc}
   */
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
    $this->saveLingotekConfigTranslationSettings([
      'node_fields' => 'automatic',
    ]);

    // Create Article node types.
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    node_add_body_field($type);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $entity = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($entity));

    // Simulate the notification of content successfully uploaded.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'complete' => 'false',
        'type' => 'document_uploaded',
        'progress' => '0',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->verbose($request);
    $this->assertIdentical(['es'], $response['result']['request_translations'], 'Spanish language has been requested after notification automatically.');

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $field_storage */
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
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
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
    $this->saveLingotekConfigTranslationSettings([
      'node_fields' => 'manual',
    ]);

    // Create Article node types.
    // We cannot use drupalCreateContentType(), as it asserts that the last entity
    // created returns SAVED_NEW, but it will return SAVED_UPDATED as we will
    // save the third party settings.
    $type = entity_create('node_type', ['type' => 'article', 'name' => 'Article']);
    $type->save();
    node_add_body_field($type);

    \Drupal::service('router.builder')->rebuild();

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $entity = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');

    // Assert the content is edited, but not auto-uploaded.
    $this->assertIdentical(Lingotek::STATUS_EDITED, $config_translation_service->getSourceStatus($entity));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // Clicking English must init the upload of content.
    $this->clickLink('EN');

    // Simulate the notification of content successfully uploaded.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'complete' => 'false',
        'type' => 'document_uploaded',
        'progress' => '0',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);

    // Translations are not requested.
    $this->assertIdentical([], $response['result']['request_translations'], 'No translations has been requested after notification automatically.');

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $field_storage */
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
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
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
    $profile = LingotekProfile::create([
      'id' => 'profile2',
      'label' => 'Profile with overrides',
      'auto_upload' => TRUE,
      'auto_download' => TRUE,
      'auto_download_worker' => FALSE,
      'language_overrides' => [
        'es' => [
          'overrides' => 'custom',
          'custom' => [
            'auto_download' => FALSE,
          ],
        ],
      ],
    ]);
    $profile->save();

    ConfigurableLanguage::createFromLangcode('de')->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $this->saveLingotekConfigTranslationSettings([
      'node_fields' => 'profile2',
    ]);

    // Create Article node types.
    // We cannot use drupalCreateContentType(), as it asserts that the last entity
    // created returns SAVED_NEW, but it will return SAVED_UPDATED as we will
    // save the third party settings.
    $type = entity_create('node_type', ['type' => 'article', 'name' => 'Article']);
    $type->save();
    node_add_body_field($type);

    \Drupal::service('router.builder')->rebuild();

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $field_storage */
    $field_storage = $this->container->get('entity.manager')->getStorage('field_config');

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $entity = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($entity));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // Simulate the notification of content successfully uploaded.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'complete' => 'false',
        'type' => 'document_uploaded',
        'progress' => '0',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
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
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertFalse($response['result']['download'], 'No translations has been downloaded after notification automatically.');

    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'de-DE',
        'locale' => 'de_DE',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
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
    $this->saveLingotekConfigTranslationSettings([
      'node_fields' => 'automatic',
    ]);

    $type1 = entity_create('node_type', ['type' => 'article', 'name' => 'Article']);
    $type1->save();
    node_add_body_field($type1);

    $type2 = entity_create('node_type', ['type' => 'page', 'name' => 'Page']);
    $type2->save();
    node_add_body_field($type2);

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    // Assert the content is importing.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $field_storage */
    $field_storage = $this->container->get('entity.manager')->getStorage('field_config');
    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $config_translation_service->getSourceStatus($entity));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // Simulate the notification of content successfully uploaded.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'complete' => 'false',
        'type' => 'document_uploaded',
        'progress' => '0',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertIdentical(['it', 'es'], $response['result']['request_translations'], 'Spanish and Italian languages have been requested after notification automatically.');

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $lingotek_config->disableLanguage($italian);

    // Test with another content.
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $field_storage */
    $field_storage = $this->container->get('entity.manager')->getStorage('field_config');
    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.page.body');

    // Simulate the notification of content successfully uploaded.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id-1',
        'complete' => 'false',
        'type' => 'document_uploaded',
        'progress' => '0',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertIdentical(['es'], $response['result']['request_translations'], 'Italian language has not been requested after notification automatically because it is disabled.');
  }

  /**
   * Test that a notification with a target deleted is responded correctly.
   */
  public function testTargetDeleted() {
    // Add an additional language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $this->saveLingotekConfigTranslationSettings([
      'node_fields' => 'automatic',
    ]);

    // Create Article node types.
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    node_add_body_field($type);

    // Simulate the notification of content successfully uploaded.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'complete' => 'false',
        'type' => 'document_uploaded',
        'progress' => '0',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->verbose($request);
    $this->assertTrue($response['result']['download'], 'Spanish language has been downloaded after notification automatically.');
    $this->assertEquals('Document downloaded.', $response['messages'][0]);

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'it-IT',
        'locale' => 'it_IT',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->verbose($request);
    $this->assertTrue($response['result']['download'], 'Italian language has been downloaded after notification automatically.');
    $this->assertEquals('Document downloaded.', $response['messages'][0]);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // All the links are current.
    $current_links = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-current')]");
    $this->assertEqual(count($current_links), 2, 'Translation "es_ES" and "it_IT" are current.');

    // Simulate the notification of target deleted.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'it-IT',
        'locale' => 'it_IT',
        'deleted_by_user_login' => 'user@example.com',
        'complete' => 'true',
        'status' => 'COMPLETE',
        'type' => 'target_deleted',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $this->assertEquals(Response::HTTP_OK, $request->getStatusCode());
    $response = json_decode($request->getBody(), TRUE);
    $this->assertEquals('Target it_IT for entity Body deleted by user@example.com', $response['messages'][0]);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // Check the right class is added.
    $this->assertTargetStatus('IT', Lingotek::STATUS_UNTRACKED);

    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $field_storage */
    $field_storage = $this->container->get('entity.manager')->getStorage('field_config');
    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');
    $translation_service = \Drupal::service('lingotek.config_translation');
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $translation_service->getTargetStatus($entity, 'it'));
  }

  /**
   * Test that a notification with a document deleted is responded correctly.
   */
  public function testDocumentDeleted() {
    // Add an additional language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    $this->saveLingotekConfigTranslationSettings([
      'node_fields' => 'automatic',
    ]);

    // Create Article node types.
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    node_add_body_field($type);

    // Simulate the notification of content successfully uploaded.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'complete' => 'false',
        'type' => 'document_uploaded',
        'progress' => '0',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->verbose($request);
    $this->assertTrue($response['result']['download'], 'Spanish language has been downloaded after notification automatically.');
    $this->assertEquals('Document downloaded.', $response['messages'][0]);

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'it-IT',
        'locale' => 'it_IT',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->verbose($request);
    $this->assertTrue($response['result']['download'], 'Italian language has been downloaded after notification automatically.');
    $this->assertEquals('Document downloaded.', $response['messages'][0]);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // All the links are current.
    $current_links = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-current')]");
    $this->assertEquals(count($current_links), 2, 'Translation "es_ES" and "it_IT" are current.');

    // Simulate the notification of target deleted.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'deleted_by_user_login' => 'user@example.com',
        'complete' => 'true',
        'type' => 'document_deleted',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $this->assertEquals(Response::HTTP_OK, $request->getStatusCode());
    $response = json_decode($request->getBody(), TRUE);
    $this->assertSame('Document for entity Body deleted by user@example.com in the TMS.', $response['messages'][0]);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);
    $this->assertTargetStatus('IT', Lingotek::STATUS_UNTRACKED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_UNTRACKED);

    // Check that the Target Status is Untracked
    /** @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $field_storage */
    $field_storage = $this->container->get('entity.manager')->getStorage('field_config');
    // The node cache needs to be reset before reload.
    $field_storage->resetCache();
    $entity = $field_storage->load('node.article.body');

    $translation_service = \Drupal::service('lingotek.config_translation');
    $this->assertEmpty($translation_service->getDocumentId($entity));
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $translation_service->getTargetStatus($entity, 'it'));
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $translation_service->getTargetStatus($entity, 'es'));
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $translation_service->getSourceStatus($entity));
  }

}
