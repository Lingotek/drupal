<?php

namespace Drupal\lingotek\Tests;

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

  protected function setUp() {
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
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

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
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

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
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

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
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'profile2';
    $this->saveAndPublishNodeForm($edit);

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
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

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
    $this->assertIdentical(['it', 'es'], $response['result']['request_translations'], 'Spanish and Italian languages have been requested after notification automatically.');

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $lingotek_config->disableLanguage($italian);

    // Test with another content.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool 2';
    $edit['body[0][value]'] = 'Llamas are very cool 2';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    // Simulate the notification of content successfully uploaded.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id-1',
      'complete' => 'false',
      'type' => 'document_uploaded',
      'progress' => '0',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);
    $this->assertIdentical(['es'], $response['result']['request_translations'], 'Italian language has not been requested after notification automatically because it is disabled.');
  }

  /**
   * Testing handling several notifications in a row.
   */
  public function testNotificationsInARow() {
    $this->pass('Test not implemented yet.');
    return;

    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('ca')->save();
    ConfigurableLanguage::createFromLangcode('hu')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

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
        ]
      ])->setAbsolute()->toString();
      $requests[] = \Drupal::httpClient()->postAsync($url);
    }
    $count = 0;
    // We wait for the requests to finish.
    foreach ($requests as $request) {
      try {
        $request->then(function ($response) use ($request) {
          $message = new TranslatableMarkup(
            'FULFILLED. Got a response with status %status and body: %body',
            ['%status' => $response->getStatusCode(),
              '%body' => (string) $response->getBody(TRUE)]
          );
          $this->verbose($message); },
          function ($response) use ($request) {
            $message = new TranslatableMarkup(
              'REJECTED. Got a response with status %status and body: %body',
              ['%status' => $response->getStatusCode(),
                '%body' => (string) $response->getBody(TRUE)]
            );
            $this->verbose($message); });
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
    $this->assertEqual(count($current_links), count($languages) - $count, new FormattableMarkup('Various languages (%var) are current.', ['%var' => count($languages) - $count]));
    $this->assertTrue(TRUE, new FormattableMarkup('%count target languages failed, but error where given back so the TMS can retry.', ['%count' => $count]));

    $this->clickLink('Llamas are cool');
    $this->clickLink('Translate');
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
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

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
    $this->assertEqual('Document downloaded.', $response['messages'][0]);

    // Go to the bulk node management page.
    $this->goToContentBulkManagementForm();

    // All the links are current.
    $current_links = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-current')]");
    $this->assertEqual(count($current_links), 1, 'Translation "es_ES" is current.');

    // We ensure it fails.
    \Drupal::state()->set('lingotek.must_error_in_download', TRUE);

    // Simulate the notification of content successfully translated.
    $request = $this->drupalPost(Url::fromRoute('lingotek.notify', [], ['query' => [
      'project_id' => 'test_project',
      'document_id' => 'dummy-document-hash-id',
      'locale_code' => 'it-IT',
      'locale' => 'it_IT',
      'complete' => 'true',
      'type' => 'target',
      'progress' => '100',
    ]]), 'application/json', []);
    $response = json_decode($request, TRUE);
    $this->verbose($request);
    $this->assertFalse($response['result']['download'], 'Italian language has been downloaded after notification automatically.');
    $this->assertEqual('No download for target it_IT happened in document dummy-document-hash-id.', $response['messages'][0]);

    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'it-IT',
        'locale' => 'it_IT',
        'complete' => 'true',
        'type' => 'target',
        'progress' => '100',
      ]
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
    $basepath = \Drupal::request()->getBasePath();
    $this->clickLink('IT');
    $this->assertText('The translation of node Llamas are cool into it_IT failed to download.');

    // Check the right class is added.
    $target_error = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-error')  and contains(text(), 'IT')]");
    $this->assertEqual(count($target_error), 1, 'The target node has been marked as error.');

    // Check that the Target Status is Error
    $node = Node::load(1);
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $this->assertIdentical(Lingotek::STATUS_ERROR, $content_translation_service->getTargetStatus($node, 'it'));
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
    $metadata_storage = $this->container->get('entity.manager')
      ->getStorage('lingotek_content_metadata');

    // The node and the metadata caches need to be reset before reload.
    $metadata_storage->resetCache([1]);
    $node_storage->resetCache([1]);

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load(1);
    return $node;
  }

}
