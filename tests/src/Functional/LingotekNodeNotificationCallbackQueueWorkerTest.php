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
 * Tests translating a node using the notification callback with a queue worker.
 *
 * @group lingotek
 */
class LingotekNodeNotificationCallbackQueueWorkerTest extends LingotekTestBase {

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

    $profile = LingotekProfile::create([
      'id' => 'automatic_worker',
      'label' => 'Custom profile',
      'auto_upload' => TRUE,
      'auto_request' => TRUE,
      'auto_download' => TRUE,
      'auto_download_worker' => TRUE,
    ]);
    $profile->save();
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
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic_worker';
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
    $this->assertTrue($response['result']['download_queued'], 'Spanish language has been queued after notification automatically.');

    $this->goToContentBulkManagementForm();

    $node = $this->resetStorageCachesAndReloadNode();

    // Assert the target is ready, but was not downloaded.
    $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));

    $this->goToContentBulkManagementForm();
    $this->assertTargetStatus('ES', Lingotek::STATUS_READY);

    // Run cron.
    $this->container->get('cron')->run();
    $this->goToContentBulkManagementForm();
    $this->assertTargetStatus('ES', Lingotek::STATUS_CURRENT);
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
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic_worker';
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

    // All the links are ready.
    $current_links = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-ready')]");
    $this->assertEqual(count($current_links), count($languages) - $count, new FormattableMarkup('Various languages (@var) are ready.', ['@var' => count($languages) - $count]));
    $this->assertTrue(TRUE, new FormattableMarkup('@count target languages failed, but error where given back so the TMS can retry.', ['@count' => $count]));
    $this->assertEqual(5, count($current_links), new FormattableMarkup('All languages (@var) are ready.', ['@var' => count($current_links)]));

    // Run cron.
    $this->container->get('cron')->run();
    $this->goToContentBulkManagementForm();

    $current_links = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-current')]");
    $this->assertEqual(count($current_links), count($languages) - $count, new FormattableMarkup('Various languages (@var) are current.', ['@var' => count($languages) - $count]));
    $this->assertTrue(TRUE, new FormattableMarkup('@count target languages failed, but error where given back so the TMS can retry.', ['@count' => $count]));
    $this->assertEqual(5, count($current_links), new FormattableMarkup('All languages (@var) are current.', ['@var' => count($current_links)]));
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
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic_worker';
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
    $this->assertTrue($response['result']['download_queued'], 'Spanish language has been queued after notification automatically.');
    $this->assertEqual('Download for target es_ES in document dummy-document-hash-id has been queued.', $response['messages'][0]);

    // Go to the bulk node management page.
    $this->goToContentBulkManagementForm();

    // All the links are pending until cron runs.
    $this->assertTargetStatus('ES', Lingotek::STATUS_READY);

    // Run cron.
    $this->container->get('cron')->run();
    $this->goToContentBulkManagementForm();
    $this->assertTargetStatus('ES', Lingotek::STATUS_CURRENT);

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
    $this->assertTrue($response['result']['download_queued'], 'Italian language has been queued after notification automatically.');
    $this->assertEqual('Download for target it_IT in document dummy-document-hash-id has been queued.', $response['messages'][0]);

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
    }
    catch (ServerException $exception) {
      if ($exception->getCode() === Response::HTTP_SERVICE_UNAVAILABLE) {
        $this->fail('The request returned a 503 status code.');
      }
      else {
        $this->fail('The request fail with an unexpected status code.');
      }
    }
    $this->verbose(var_export($response, TRUE));

    // Run cron.
    $this->container->get('cron')->run();
    $this->goToContentBulkManagementForm();

    // Check the right class is added.
    $this->assertTargetStatus('IT', Lingotek::STATUS_ERROR);

    // Try to re-download the Italian translation.
    $this->clickLink('IT');
    $this->assertText('The download for node Llamas are cool failed. Please try again.');

    // Check that the Target Status is Error
    $node = Node::load(1);
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $this->assertIdentical(Lingotek::STATUS_ERROR, $content_translation_service->getTargetStatus($node, 'it'));
  }

}
