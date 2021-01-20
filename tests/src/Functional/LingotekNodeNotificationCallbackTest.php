<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\HttpFoundation\Response;

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

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'header', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'header', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
  }

  /**
   * Tests notification callbacks without any arguments, like in a browser.
   */
  public function testNotificationCallbackWithNoArguments() {
    $assert_session = $this->assertSession();
    // Simulate the notification of an empty request.
    $url = Url::fromRoute('lingotek.notify', [], [])
      ->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $this->assertEquals($request->getStatusCode(), Response::HTTP_ACCEPTED);
    $this->assertEquals('It works, but nothing to look here.', (string) $request->getBody());

    $this->drupalGet('lingotek/notify');
    $assert_session->statusCodeEquals(Response::HTTP_ACCEPTED);
    $assert_session->responseContains('It works, but nothing to look here.');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testAutomatedNotificationNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();

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

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    $this->goToContentBulkManagementForm();

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
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();
    $this->clickLink('ES');

    // Ensure we won't get a completed document because there are phases pending.
    \Drupal::state()->set('lingotek.document_completion', 40);

    // Simulate the notification of content ready to download.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'phase',
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
    $response = (string) $request->getBody();
    $this->assertSame(Response::HTTP_ACCEPTED, $request->getStatusCode());
    $this->assertSame('It works, but nothing to look here.', $response);

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is intermediate.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    // Assert a translation has been downloaded.
    $this->drupalGet('node/1/translations');
    $assert_session->linkNotExists('Las llamas son chulas');

    // There are no phases pending anymore.
    \Drupal::state()->set('lingotek.document_completion', TRUE);

    $this->goToContentBulkManagementForm();

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

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'es'));

    $this->goToContentBulkManagementForm();
  }

  /**
   * Tests that a node reacts to incomplete target and phase notifications
   * and does not download interim translations based on the settings.
   */
  public function testIncompletePhaseNotificationWithNoInterimNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();
    $this->clickLink('ES');

    // Ensure we won't get a completed document because there are phases pending.
    \Drupal::state()->set('lingotek.document_completion', 40);

    // Simulate the notification of content ready to download.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'phase',
        'progress' => '50',
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
    $response = (string) $request->getBody();
    $this->assertSame(Response::HTTP_ACCEPTED, $request->getStatusCode());
    $this->assertSame('It works, but nothing to look here.', $response);

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '50',
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
    $this->assertFalse($response['result']['download'], 'Spanish language has not been downloaded after notification automatically, as it is interim.');
    $this->assertEqual($response['messages'][0], 'Interim downloads are disabled, so no download for target es_ES happened in document dummy-document-hash-id.', 'Spanish language has not been downloaded after notification automatically, as it is interim.');

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));
  }

  /**
   * Tests that a node reacts to download_interim_translation notification and does not download interim translations
   * based on the settings.
   */
  public function testDownloadInterimTranslationNotificationWithNoInterimNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();
    $this->clickLink('ES');

    // Ensure we won't get a completed document because there are phases pending.
    \Drupal::state()->set('lingotek.document_completion', 40);

    // Simulate the notification of content ready to download.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'phase',
        'progress' => '50',
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
    $response = (string) $request->getBody();
    $this->assertSame(Response::HTTP_ACCEPTED, $request->getStatusCode());
    $this->assertSame('It works, but nothing to look here.', $response);

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '50',
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
    $this->assertFalse($response['result']['download'], 'Spanish language has not been downloaded after notification automatically, as it is interim.');
    $this->assertEqual($response['messages'][0], 'Interim downloads are disabled, so no download for target es_ES happened in document dummy-document-hash-id.', 'Spanish language has not been downloaded after notification automatically, as it is interim.');

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));
  }

  /**
   * Tests that a node reacts to incomplete target and phase notifications
   * and downloads interim translations based on the settings.
   */
  public function testIncompletePhaseNotificationWithInterimNodeTranslation() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('admin/lingotek/settings');
    $edit = ['enable_download_interim' => TRUE];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();
    $this->clickLink('ES');

    // Ensure we won't get a completed document because there are phases pending.
    \Drupal::state()->set('lingotek.document_completion', 40);

    // Simulate the notification of content ready to download.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'phase',
        'progress' => '50',
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
    $response = (string) $request->getBody();
    $this->assertSame(Response::HTTP_ACCEPTED, $request->getStatusCode());
    $this->assertSame('It works, but nothing to look here.', $response);

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is intermediate.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    // Assert a translation has NOT been downloaded.
    $this->drupalGet('node/1/translations');
    $assert_session->linkNotExists('Las llamas son chulas');

    // There are no phases pending anymore.
    \Drupal::state()->set('lingotek.document_completion', TRUE);

    $this->goToContentBulkManagementForm();

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '50',
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

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'es'));

    $this->goToContentBulkManagementForm();
  }

  /**
   * Tests that a node reacts to download_interim_translation notification and downloads interim translations based on
   * the settings.
   */
  public function testDownloadInterimTranslationNotificationWithInterimNodeTranslation() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('admin/lingotek/settings');
    $edit = ['enable_download_interim' => TRUE];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();
    $this->clickLink('ES');

    // Ensure we won't get a completed document because there are phases pending.
    \Drupal::state()->set('lingotek.document_completion', 40);

    // Simulate the notification of content ready to download.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'download_interim_translation',
        'progress' => '50',
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

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is intermediate.
    $this->assertIdentical(Lingotek::STATUS_INTERMEDIATE, $content_translation_service->getTargetStatus($node, 'es'));

    // Assert a translation has been downloaded.
    $this->drupalGet('node/1/translations');
    $assert_session->linkExists('Las llamas son chulas');

    // There are no phases pending anymore.
    \Drupal::state()->set('lingotek.document_completion', TRUE);

    $this->goToContentBulkManagementForm();

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '50',
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
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is edited, but not auto-uploaded.
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();
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
    $this->assertEmpty($response['result']['download'], 'No translations has been downloaded after notification automatically.');

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
    ConfigurableLanguage::createFromLangcode('de')->save();
    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('ca')->setThirdPartySetting('lingotek', 'locale', 'ca_ES')->save();

    $profile = LingotekProfile::create([
      'id' => 'profile2',
      'label' => 'Profile with overrides',
      'auto_upload' => TRUE,
      'auto_request' => TRUE,
      'auto_download' => TRUE,
      'auto_download_worker' => FALSE,
      'language_overrides' => [
        'es' => [
          'overrides' => 'custom',
          'custom' => [
            'auto_request' => FALSE,
            'auto_download' => FALSE,
          ],
        ],
        'ca' => [
          'overrides' => 'disabled',
        ],
      ],
    ]);
    $profile->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'profile2';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();

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
    $this->assertIdentical(['de', 'it'], $response['result']['request_translations'], 'German and Italian languages has been requested after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_REQUEST, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'de'));
    // We assert for the UI, as the status is not really stored.
    // TODO: This should actually be stored.
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    $this->goToContentBulkManagementForm();
    // Request Spanish manually.
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
    $this->verbose($request);
    $this->assertEmpty($response['result']['download'], 'No translations has been downloaded after notification automatically.');

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'ca-ES',
        'locale' => 'ca_ES',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ],
    ])->setAbsolute()->toString();
    $request = $this->client->post($url, [
      'body' => http_build_query([]),
      'cookies' => $this->cookies,
      'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
      'http_errors' => FALSE,
    ]);
    $response = json_decode($request->getBody(), TRUE);
    $this->assertEmpty($response['result']['download'], 'No translations has been downloaded after notification automatically.');

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
    $this->verbose($request);
    $this->assertTrue($response['result']['download'], 'German language has been downloaded after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'de'));
    // We assert for the UI, as the status is not really stored.
    // TODO: This should actually be stored.
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Go to the bulk node management page and download them.
    $this->goToContentBulkManagementForm();
    $this->clickLink('ES');

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is current.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'de'));
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testProfileRequestTargetOverridesNotificationNodeTranslation() {
    $profile = LingotekProfile::create([
      'id' => 'profile2',
      'label' => 'Profile with overrides',
      'auto_upload' => TRUE,
      'auto_request' => FALSE,
      'auto_download' => TRUE,
      'auto_download_worker' => FALSE,
      'language_overrides' => [
        'es' => [
          'overrides' => 'custom',
          'custom' => [
            'auto_request' => TRUE,
            'auto_download' => FALSE,
          ],
        ],
      ],
    ]);
    $profile->save();

    ConfigurableLanguage::createFromLangcode('de')->save();
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'profile2';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();

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
    $this->assertIdentical(['es'], $response['result']['request_translations'], 'Spanish language has been requested after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertIdentical(Lingotek::STATUS_REQUEST, $content_translation_service->getTargetStatus($node, 'de'));

    $this->goToContentBulkManagementForm();

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
    $this->assertEmpty($response['result']['download'], 'No translations has been downloaded after notification automatically.');

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
    $this->verbose($request);
    $this->assertEmpty($response['result']['download'], 'No translations has been downloaded after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is ready.
    $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'de'));

    // Go to the bulk node management page and download them.
    $this->goToContentBulkManagementForm();
    $this->clickLink('ES');

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is current.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'de'));
  }

  /**
   * Tests that there are no automatic requests for disabled languages.
   */
  public function testDisabledLanguagesAreNotRequested() {
    // Add a language.
    $italian = ConfigurableLanguage::createFromLangcode('it');
    $italian->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();

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
      'body' => http_build_query([]),
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
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool 2';
    $edit['body[0][value]'] = 'Llamas are very cool 2';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

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
      'body' => http_build_query([]),
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
   * Testing handling several notifications in a row.
   */
  public function testNotificationsInARow() {
    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('ca')->save();
    ConfigurableLanguage::createFromLangcode('hu')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);
    $this->goToContentBulkManagementForm();

    // Upload the node.
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request languages.
    $languages = [
      'DE' => 'de_DE',
      'ES' => 'es_ES',
      'HU' => 'hu_HU',
      'IT' => 'it_IT',
      'CA' => 'ca_ES',
    ];
    foreach ($languages as $langcode => $locale) {
      $this->clickLink($langcode);
      $this->assertText(new FormattableMarkup("Locale '@locale' was added as a translation target for node Llamas are cool.", ['@locale' => $locale]));
    }

    /** @var \GuzzleHttp\Promise\PromiseInterface[] $requests */
    $requests = [];
    foreach ($languages as $langcode => $locale) {
      $url = Url::fromRoute('lingotek.notify', [], [
        'query' => [
          'project_id' => 'test_project',
          'document_id' => 'dummy-document-hash-id',
          'locale_code' => str_replace('_', '-', $locale),
          'locale' => $locale,
          'complete' => 'true',
          'type' => 'target',
          'progress' => '100',
        ],
      ])->setAbsolute()->toString();
      $requests[] = \Drupal::httpClient()->postAsync($url);
    }
    $count = 0;
    // We wait for the requests to finish.
    foreach ($requests as $request) {
      try {
        $request->then(function ($response) use ($request) {
          $message = new TranslatableMarkup(
            'FULFILLED. Got a response with status %status and body: %body', [
              '%status' => $response->getStatusCode(),
              '%body' => (string) $response->getBody(TRUE),
            ]);
          $this->verbose($message);
        }, function ($response) use ($request) {
            $message = new TranslatableMarkup(
              'REJECTED. Got a response with status %status and body: %body', [
                '%status' => $response->getStatusCode(),
                '%body' => (string) $response->getBody(TRUE),
              ]);
            $this->verbose($message);
        });
      }
      catch (\Exception $error) {
        $count++;
      }
    }
    foreach ($requests as $request) {
      $request->wait(TRUE);
    }

    // Go to the bulk node management page.
    $this->goToContentBulkManagementForm();

    // All the links are current.
    $current_links = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-current')]");
    $this->assertEqual(count($current_links), count($languages) - $count, new FormattableMarkup('Various languages (@var) are current.', ['@var' => count($languages) - $count]));
    $this->assertTrue(TRUE, new FormattableMarkup('@count target languages failed, but error where given back so the TMS can retry.', ['@count' => $count]));
    $this->assertEqual(5, count($current_links), new FormattableMarkup('All languages (@var) are current.', ['@var' => count($current_links)]));

    $this->clickLink('Llamas are cool');
    $this->clickLink('Translate');
  }

  /**
   * Tests notification callbacks when the documents have been deleted.
   */
  public function testNotificationCallbacksOnMissingDocuments() {
    // Simulate the notification of content successfully uploaded.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'missing-document-id',
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
    $this->assertEquals($request->getStatusCode(), Response::HTTP_NO_CONTENT);

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'missing-document-id',
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
    $this->assertEquals($request->getStatusCode(), Response::HTTP_NO_CONTENT);
  }

  /**
   * Test that a notification with a failure in download responded with an error.
   */
  public function testAutomatedNotificationNodeTranslationWithError() {
    // Add an additional language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

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
    $this->assertEqual('Document downloaded.', $response['messages'][0]);

    // Go to the bulk node management page.
    $this->goToContentBulkManagementForm();

    // All the links are current.
    $current_links = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-current')]");
    $this->assertEqual(count($current_links), 1, 'Translation "es_ES" is current.');

    // We ensure it fails.
    \Drupal::state()->set('lingotek.must_error_in_download', TRUE);

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
    $this->assertFalse(isset($response['result']['download']), 'Italian language has not been downloaded after notification automatically.');
    $this->assertEqual('Download of target it_IT for document dummy-document-hash-id failed', $response['messages'][0]);

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
    $request = \Drupal::httpClient()->postAsync($url);

    try {
      $response = $request->wait();
      $this->fail('The request didn\'t fail as expected.');
    }
    catch (ServerException $exception) {
      if ($exception->getCode() === Response::HTTP_SERVICE_UNAVAILABLE) {
        $this->pass('The request returned a 503 status code.');
      }
      else {
        $this->fail('The request didn\'t fail with the expected status code.');
      }
    }
    $this->verbose(var_export($response, TRUE));

    // Go to the bulk node management page.
    $this->goToContentBulkManagementForm();

    // Download the Italian translation.
    $this->clickLink('IT');
    $this->assertText('The download for node Llamas are cool failed. Please try again.');

    // Check the right class is added.
    $this->assertTargetStatus('IT', Lingotek::STATUS_ERROR);

    // Check that the Target Status is Error
    $node = Node::load(1);
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $this->assertIdentical(Lingotek::STATUS_ERROR, $content_translation_service->getTargetStatus($node, 'it'));
  }

  /**
   * Test that a notification with a target deleted is responded correctly.
   */
  public function testTargetDeleted() {
    // Add an additional language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

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

    // Go to the bulk node management page.
    $this->goToContentBulkManagementForm();

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
    $this->assertEquals('Target it_IT for entity Llamas are cool deleted by user@example.com', $response['messages'][0]);

    // Go to the bulk node management page.
    $this->goToContentBulkManagementForm();
    // Check the right class is added.
    $this->assertTargetStatus('IT', Lingotek::STATUS_UNTRACKED);

    // Check that the Target Status is Untracked
    $node = Node::load(1);
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $content_translation_service->getTargetStatus($node, 'it'));
  }

  /**
   * Test that a notification with a document deleted is responded correctly.
   */
  public function testDocumentDeleted() {
    // Add an additional language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

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

    // Go to the bulk node management page.
    $this->goToContentBulkManagementForm();

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
    $this->assertSame('Document for entity Llamas are cool deleted by user@example.com in the TMS.', $response['messages'][0]);

    // Go to the bulk node management page.
    $this->goToContentBulkManagementForm();
    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);
    $this->assertTargetStatus('IT', Lingotek::STATUS_UNTRACKED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_UNTRACKED);

    // Check that the Target Status is Untracked
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $this->assertEmpty($content_translation_service->getDocumentId($node));
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $content_translation_service->getTargetStatus($node, 'it'));
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertEquals(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($node));
  }

  /**
   * Tests that a node is archived on the right callback.
   */
  public function testArchivedNotificationCallback() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));

    $this->goToContentBulkManagementForm();

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

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    $this->goToContentBulkManagementForm();

    // Simulate the notification of archived document.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'document_archived',
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
    $this->assertNotEmpty($response['messages'], 'Document Llamas are cool was archived in Lingotek.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    $this->assertNull($content_translation_service->getDocumentId($node));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getTargetStatus($node, 'es'));

    $this->goToContentBulkManagementForm();
  }

  /**
   * Tests that an import_failure callback is handled after document upload.
   */
  public function testImportFailureWhileUploading() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical($content_translation_service->getDocumentId($node), 'dummy-document-hash-id');

    $this->goToContentBulkManagementForm();

    // Simulate the notification of failed import document.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'type' => 'import_failure',
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
    $this->assertIdentical($response['messages'][0], 'Document import for entity Llamas are cool failed. Reverting dummy-document-hash-id to previous id (NULL)');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    $this->assertNull($content_translation_service->getDocumentId($node));
    $this->assertIdentical(Lingotek::STATUS_ERROR, $content_translation_service->getSourceStatus($node));
  }

  /**
   * Tests that an import_failure callback is handled after document update.
   */
  public function testImportFailureWhileUpdating() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical($content_translation_service->getDocumentId($node), 'dummy-document-hash-id');

    $this->goToContentBulkManagementForm();

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

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->goToContentBulkManagementForm();
    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));
    // Assert the document id changed.
    $this->assertIdentical($content_translation_service->getDocumentId($node), 'dummy-document-hash-id-1');

    // Simulate the notification of failed import document.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'prev_document_id' => 'dummy-document-hash-id',
        'document_id' => 'dummy-document-hash-id-1',
        'type' => 'import_failure',
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

    $this->assertIdentical($response['messages'][0], 'Document import for entity Llamas are cool EDITED failed. Reverting dummy-document-hash-id-1 to previous id dummy-document-hash-id');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the document id was restored.
    $this->assertEquals($content_translation_service->getDocumentId($node), 'dummy-document-hash-id');
    $this->assertIdentical(Lingotek::STATUS_ERROR, $content_translation_service->getSourceStatus($node));
  }

  /**
   * Tests that a document_updated callback is handled after document update.
   */
  public function testDocumentUpdated() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical($content_translation_service->getDocumentId($node), 'dummy-document-hash-id');

    $this->goToContentBulkManagementForm();

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

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->goToContentBulkManagementForm();
    $node = $this->resetStorageCachesAndReloadNode();

    // Add a new language and ensure is requested.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));
    // Assert the document id changed.
    $this->assertIdentical($content_translation_service->getDocumentId($node), 'dummy-document-hash-id-1');

    // Simulate the notification of content successfully updated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id-1',
        'complete' => 'false',
        'type' => 'document_updated',
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
    $this->assertIdentical(['it'], $response['result']['request_translations'], 'Italian language has been requested after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the document id and the CURRENT status.
    $this->assertEquals($content_translation_service->getDocumentId($node), 'dummy-document-hash-id-1');
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'it'));
  }

  /**
   * Tests that a document_cancelled callback is handled after document upload.
   */
  public function testDocumentCancelledAfterUploading() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical($content_translation_service->getDocumentId($node), 'dummy-document-hash-id');

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

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    // Simulate the notification of document_cancelled document.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'type' => 'document_cancelled',
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
    $this->assertIdentical($response['messages'][0], 'Document Llamas are cool cancelled in TMS.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    $this->assertNull($content_translation_service->getDocumentId($node));
    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $content_translation_service->getTargetStatus($node, 'es'));
  }

  /**
   * Tests that a target_cancelled callback is handled after document upload.
   */
  public function testTargetCancelledAfterUploading() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical($content_translation_service->getDocumentId($node), 'dummy-document-hash-id');

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

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    // Assert the target is pending.
    $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));

    // Simulate the notification of document_cancelled document.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'type' => 'target_cancelled',
        'locale' => 'es_ES',
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
    $this->assertIdentical($response['messages'][0], 'Document Llamas are cool target es_ES cancelled in TMS.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    $this->assertIdentical('dummy-document-hash-id', $content_translation_service->getDocumentId($node));
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $content_translation_service->getTargetStatus($node, 'es'));
  }

}
