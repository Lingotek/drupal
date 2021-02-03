<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating the user interface using the Lingotek form.
 *
 * @group lingotek
 */
class LingotekInterfaceTranslationTest extends LingotekTestBase {

  use LingotekInterfaceTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'potx', 'lingotek_interface_translation_test', 'frozenintime'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    \Drupal::state()->set('lingotek.uploaded_content_type', 'interface-translation');

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();
  }

  /**
   * Tests when potx is not present.
   */
  public function testInterfaceTranslationWithoutPotx() {
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('/admin/modules');

    // Ensure the module is not enabled yet.
    $this->assertSession()->checkboxChecked('edit-modules-potx-enable');

    $this->clickLink('Uninstall');

    // Post the form uninstalling the lingotek module.
    $edit = ['uninstall[potx]' => '1'];
    $this->drupalPostForm(NULL, $edit, 'Uninstall');

    // We get an advice and we can confirm.
    $assert_session->responseContains('The following modules will be completely uninstalled from your site, and <em>all data from these modules will be lost</em>!');
    $assert_session->responseContains('Translation template extractor');

    $this->drupalPostForm(NULL, [], 'Uninstall');

    $this->goToInterfaceTranslationManagementForm();

    $assert_session->responseContains('The <a href="https://www.drupal.org/project/potx">potx</a> module is required for interface translation with Lingotek');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testInterfaceTranslationUsingLinks() {
    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();

    $assert_session->responseContains('lingotek');
    $assert_session->responseContains('lingotek_test');
    $assert_session->responseContains('lingotek_interface_translation_test');
    $assert_session->responseContains('stark');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink('core/profiles/testing');
    $this->assertLingotekInterfaceTranslationUploadLink('core/themes/stark');
    $this->assertLingotekInterfaceTranslationUploadLink($component);
    // And we cannot request yet a translation.
    $this->assertNoLingotekInterfaceTranslationRequestTranslationLink($component, 'es_MX');

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('<em class="placeholder">' . $component . '</em> uploaded successfully');

    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $singularStrings = [
      'Test title for interface translation',
    ];
    $pluralStrings = [
      "This is a singular example<PLURAL>This is a plural @count example<CONTEXT>" => [
        "This is a singular example" => "This is a singular example",
        "This is a plural @count example" => "This is a plural @count example",
        "_context" => '',
      ],
    ];
    $contextStrings = [
      "This is test of context<CONTEXT>" => [
        "This is test of context" => "This is test of context",
        "_context" => '',
      ],
      "This is test of context<CONTEXT>multiple p" => [
        "This is test of context" => "This is test of context",
        "_context" => 'multiple p',
      ],
      "This is test of context<CONTEXT>multiple t" => [
        "This is test of context" => "This is test of context",
        "_context" => 'multiple t',
      ],
    ];
    foreach ($singularStrings as $singularString) {
      $singularStringWithContext = $singularString . '<CONTEXT>';
      $this->assertTrue(isset($data[$singularStringWithContext]));
      $this->assertSame($singularString, $data[$singularStringWithContext][$singularString]);
      $this->assertSame('', $data[$singularStringWithContext]['_context']);
    }
    foreach ($pluralStrings as $key => $pluralData) {
      $this->assertTrue(isset($data[$key]));
      $this->assertSame('', $data[$key]['_context']);
      foreach ($pluralData as $pluralString => $pluralTranslatedString) {
        if ($pluralString !== '_context') {
          $this->assertTrue(isset($data[$key][$pluralString]));
          $this->assertSame($pluralTranslatedString, $data[$key][$pluralString]);
        }
      }
    }
    foreach ($contextStrings as $key => $contextData) {
      $this->assertTrue(isset($data[$key]));
      foreach ($contextData as $contextString => $contextTranslatedString) {
        $this->assertSame($contextData['_context'], $data[$key]['_context']);
        if ($contextString !== '_context') {
          $this->assertTrue(isset($data[$key][$contextString]));
          $this->assertSame($contextTranslatedString, $data[$key][$contextString]);
        }
      }
    }

    $this->assertSame('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLingotekInterfaceTranslationCheckSourceStatusLink($component);
    // And we can already request a translation.
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component, 'es_MX');

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('The import for <em class="placeholder">' . $component . '</em> is complete.');

    // Request the Spanish translation.
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component, 'es_MX');
    $this->clickLink('ES');
    $assert_session->responseContains('Locale \'es_MX\' was added as a translation target for <em class="placeholder">' . $component . '</em>.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekInterfaceTranslationCheckTargetStatusLink($component, 'es_MX');
    $this->clickLink('ES');
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $assert_session->responseContains('The es_MX translation for <em class="placeholder">' . $component . '</em> is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekInterfaceTranslationDownloadLink($component, 'es_MX');
    $this->clickLink('ES');
    $assert_session->responseContains('The translation of <em class="placeholder">' . $component . '</em> into es_MX has been downloaded.');
    $this->assertSame('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');

    $this->drupalGet('es/lingotek-interface-translation-test');
    $assert_session->responseContains('Título de Prueba para Traducción de Interfaz');
    $assert_session->responseContains('Este es un ejemplo en singular');
    $assert_session->responseNotContains('This is test of context');
    $assert_session->responseContains('Esto es una prueba de contexto');
    $assert_session->responseContains('Esto es una pppprueba de contexto');
    $assert_session->responseContains('Estttto es una prueba de conttttextttto');

    $this->drupalGet('es/lingotek-interface-translation-test', ['query' => ['count' => 10]]);
    $assert_session->responseContains('Título de Prueba para Traducción de Interfaz');
    $assert_session->responseContains('Este es un ejemplo en plural: 10');
    $assert_session->responseNotContains('This is test of context');
    $assert_session->responseContains('Esto es una prueba de contexto');
    $assert_session->responseContains('Esto es una pppprueba de contexto');
    $assert_session->responseContains('Estttto es una prueba de conttttextttto');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testClearInterfaceTranslationMetadata() {
    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path1 = drupal_get_path('module', 'lingotek_interface_translation_test');
    $path2 = drupal_get_path('module', 'lingotek_test');
    $component1 = $path1;
    $component2 = $path2;
    $indexOfModuleLink1 = 2;
    $indexOfModuleLink2 = 3;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();

    $assert_session->responseContains('lingotek_test');
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component1);
    $this->assertLingotekInterfaceTranslationUploadLink($component2);
    // And we cannot request yet a translation.
    $this->assertNoLingotekInterfaceTranslationRequestTranslationLink($component1, 'es_MX');
    $this->assertNoLingotekInterfaceTranslationRequestTranslationLink($component2, 'es_MX');

    $this->clickLink('EN', $indexOfModuleLink1);
    $assert_session->responseContains('<em class="placeholder">' . $component1 . '</em> uploaded successfully');

    $this->clickLink('EN', $indexOfModuleLink2);
    $assert_session->responseContains('<em class="placeholder">' . $component2 . '</em> uploaded successfully');

    // There is a link for checking status.
    $this->assertLingotekInterfaceTranslationCheckSourceStatusLink($component1);
    $this->assertLingotekInterfaceTranslationCheckSourceStatusLink($component2);
    // And we can already request a translation.
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component1, 'es_MX');
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component2, 'es_MX');

    $this->clickLink('EN', $indexOfModuleLink1);
    $assert_session->responseContains('The import for <em class="placeholder">' . $component1 . '</em> is complete.');

    // Request the Spanish translation.
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component1, 'es_MX');
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component2, 'es_MX');
    $this->clickLink('ES');
    $assert_session->responseContains('Locale \'es_MX\' was added as a translation target for <em class="placeholder">' . $component1 . '</em>.');

    $this->drupalPostForm(NULL, [], 'Clear Lingotek interface translation metadata');
    $assert_session->responseContains('This will remove the metadata stored about your Lingotek interface translations, so you will need to re-upload those in case you want to translate them.');

    $this->drupalPostForm(NULL, [], 'Clear metadata');
    $assert_session->responseContains('You have cleared the Lingotek metadata for interface translations.');

    // Download the Spanish translation.
    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component1);
    $this->assertLingotekInterfaceTranslationUploadLink($component2);
    // And we cannot request yet a translation.
    $this->assertNoLingotekInterfaceTranslationRequestTranslationLink($component1, 'es_MX');
    $this->assertNoLingotekInterfaceTranslationRequestTranslationLink($component2, 'es_MX');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testAddingLanguageAllowsRequesting() {
    $assert_session = $this->assertSession();

    // We need translations first.
    $this->testInterfaceTranslationUsingLinks();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('ca')->save();

    $this->goToInterfaceTranslationManagementForm();

    // There is a link for requesting the Catalan translation.
    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;

    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component, 'ca_ES');
    $this->clickLink('CA');
    $assert_session->responseContains('Locale \'ca_ES\' was added as a translation target for <em class="placeholder">' . $component . '</em>.');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testFormWorksAfterRemovingLanguageWithStatuses() {
    $assert_session = $this->assertSession();

    // We need a language added and requested.
    $this->testAddingLanguageAllowsRequesting();

    // Delete a language.
    ConfigurableLanguage::load('es')->delete();

    $this->goToInterfaceTranslationManagementForm();

    // There is no link for the Spanish translation.
    $assert_session->linkNotExists('ES');
    $assert_session->linkExists('CA');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);

    // Upload the document, which must fail.
    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('The upload for <em class="placeholder">' . $component . '</em> failed. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    /** @var \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.interface_translation');
    $source_status = $translation_service->getSourceStatus($component);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The source upload has been marked as error.');
    $this->assertEmpty($translation_service->getLastUploaded($component));

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('<em class="placeholder">' . $component . '</em> uploaded successfully');
    drupal_flush_all_caches();
    $expected_time = \Drupal::time()->getRequestTime();
    $this->assertEquals($expected_time, $translation_service->getLastUploaded($component));
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);

    // Upload the document, which must fail.
    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    /** @var \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.interface_translation');
    $source_status = $translation_service->getSourceStatus($component);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The source upload has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', FALSE);
    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('<em class="placeholder">' . $component . '</em> uploaded successfully');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_request_translation', TRUE);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('<em class="placeholder">' . $component . '</em> uploaded successfully');

    // I can check current status.
    $this->assertLingotekInterfaceTranslationCheckSourceStatusLink($component);

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('The import for <em class="placeholder">' . $component . '</em> is complete.');

    // Request the Spanish translation.
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component, 'es_MX');
    $this->clickLink('ES');

    // We failed at requesting a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $assert_session->responseContains('Requesting \'es_MX\' translation for <em class="placeholder">' . $component . '</em> failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_request_translation', TRUE);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('<em class="placeholder">' . $component . '</em> uploaded successfully');

    // I can check current status.
    $this->assertLingotekInterfaceTranslationCheckSourceStatusLink($component);

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('The import for <em class="placeholder">' . $component . '</em> is complete.');

    // Request the Spanish translation.
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component, 'es_MX');
    $this->clickLink('ES');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $assert_session->responseContains('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithADocumentArchivedError() {
    \Drupal::state()->set('lingotek.must_document_archived_error_in_request_translation', TRUE);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('<em class="placeholder">' . $component . '</em> uploaded successfully');

    // I can check current status.
    $this->assertLingotekInterfaceTranslationCheckSourceStatusLink($component);

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('The import for <em class="placeholder">' . $component . '</em> is complete.');

    // Request the Spanish translation.
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component, 'es_MX');
    $this->clickLink('ES');

    // We cannot use ::assertSourceStatus, there are lots of untracked docs, but
    // checking the upload link should suffice.
    // $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);
    $this->assertLingotekInterfaceTranslationUploadLink($component);
    $this->assertNoLingotekInterfaceTranslationRequestTranslationLink($component, 'es_MX');
    $assert_session->responseContains('Document <em class="placeholder">' . $component . '</em> has been archived. Please upload again.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithADocumentLockedError() {
    \Drupal::state()->set('lingotek.must_document_locked_error_in_request_translation', TRUE);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('<em class="placeholder">' . $component . '</em> uploaded successfully');

    // Request the Spanish translation.
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component, 'es_MX');
    $this->clickLink('ES');

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $assert_session->responseContains('Document <em class="placeholder">' . $component . '</em> has a new version. The document id has been updated for all future interactions. Please try again.');
  }

  /**
   * Tests that we manage errors when using the check translation status link.
   */
  public function testCheckTranslationStatusWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_check_target_status', TRUE);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('<em class="placeholder">' . $component . '</em> uploaded successfully');

    // Request the Spanish translation.
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component, 'es_MX');
    $this->clickLink('ES');
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);

    // Check the status of the translation.
    $this->clickLink('ES');

    // We failed at checking a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);
    $assert_session->responseContains('The request for <em class="placeholder">' . $component . '</em> \'es_MX\' translation status failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the download translation link.
   */
  public function testDownloadTranslationWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_download', TRUE);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);

    $this->clickLink('EN', $indexOfModuleLink);
    $assert_session->responseContains('<em class="placeholder">' . $component . '</em> uploaded successfully');

    // Request the Spanish translation.
    $this->assertLingotekInterfaceTranslationRequestTranslationLink($component, 'es_MX');
    $this->clickLink('ES');
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);

    // Check the status of the translation.
    $this->clickLink('ES');
    $this->assertTargetStatus('ES', Lingotek::STATUS_READY);

    // Download translation.
    $this->clickLink('ES');

    // We failed at downloading a translation. Mark as error.
    $this->assertTargetStatus('ES', Lingotek::STATUS_ERROR);
    $assert_session->responseContains('The \'es_MX\' translation download for <em class="placeholder">' . $component . '</em> failed. Please try again.');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testAutomatedNotificationInterfaceTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);
    $this->clickLink('EN', $indexOfModuleLink);

    /** @var \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.interface_translation');
    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $translation_service->getSourceStatus($component));

    $this->goToInterfaceTranslationManagementForm();

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
    $this->assertIdentical([], $response['result']['request_translations'], 'No translations have been requested after notification automatically.');

    $this->goToInterfaceTranslationManagementForm();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $translation_service->getSourceStatus($component));
    // Assert the target is ready for requesting.
    $this->assertIdentical(Lingotek::STATUS_REQUEST, $translation_service->getTargetStatus($component, 'es'));

    // Request Spanish manually.
    $this->clickLink('ES');
    // Assert the target is pending.
    $this->goToInterfaceTranslationManagementForm();
    $this->assertIdentical(Lingotek::STATUS_PENDING, $translation_service->getTargetStatus($component, 'es'));

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
    $this->assertFalse($response['result']['download'], 'No targets have been downloaded after notification automatically.');

    $this->goToInterfaceTranslationManagementForm();
    $this->assertIdentical(Lingotek::STATUS_READY, $translation_service->getTargetStatus($component, 'es'));

    // Download Spanish manually.
    $this->clickLink('ES');

    // Assert the target is downloaded.
    $this->goToInterfaceTranslationManagementForm();
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $translation_service->getTargetStatus($component, 'es'));
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testAutomatedArchivedNotificationInterfaceTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);
    $this->clickLink('EN', $indexOfModuleLink);

    /** @var \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.interface_translation');
    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $translation_service->getSourceStatus($component));

    $this->goToInterfaceTranslationManagementForm();

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
    $this->assertIdentical([], $response['result']['request_translations'], 'No translations have been requested after notification automatically.');

    $this->goToInterfaceTranslationManagementForm();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $translation_service->getSourceStatus($component));
    // Assert the target is ready for requesting.
    $this->assertIdentical(Lingotek::STATUS_REQUEST, $translation_service->getTargetStatus($component, 'es'));

    // Request Spanish manually.
    $this->clickLink('ES');
    // Assert the target is pending.
    $this->goToInterfaceTranslationManagementForm();
    $this->assertIdentical(Lingotek::STATUS_PENDING, $translation_service->getTargetStatus($component, 'es'));

    // Simulate the notification of content successfully translated.
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
    $this->assertEquals($response['messages'][0], "Document $path was archived in Lingotek.");

    $this->goToInterfaceTranslationManagementForm();
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $translation_service->getTargetStatus($component, 'es'));
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testAutomatedCancelledNotificationInterfaceTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // In Drupal.org CI the module will be at modules/contrib/lingotek.
    // In my local that's modules/lingotek. We need to generate the path and not
    // hardcode it.
    $path = drupal_get_path('module', 'lingotek_interface_translation_test');
    $component = $path;
    $indexOfModuleLink = 2;
    $assert_session = $this->assertSession();
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->goToInterfaceTranslationManagementForm();
    $assert_session->responseContains('lingotek_interface_translation_test');

    // Clicking English must init the upload of content.
    $this->assertLingotekInterfaceTranslationUploadLink($component);
    $this->clickLink('EN', $indexOfModuleLink);

    /** @var \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.interface_translation');
    // Assert the content is importing.
    $this->assertIdentical(Lingotek::STATUS_IMPORTING, $translation_service->getSourceStatus($component));

    $this->goToInterfaceTranslationManagementForm();

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
    $this->assertIdentical([], $response['result']['request_translations'], 'No translations have been requested after notification automatically.');

    $this->goToInterfaceTranslationManagementForm();

    // Assert the content is imported.
    $this->assertIdentical(Lingotek::STATUS_CURRENT, $translation_service->getSourceStatus($component));
    // Assert the target is ready for requesting.
    $this->assertIdentical(Lingotek::STATUS_REQUEST, $translation_service->getTargetStatus($component, 'es'));

    // Request Spanish manually.
    $this->clickLink('ES');
    // Assert the target is pending.
    $this->goToInterfaceTranslationManagementForm();
    $this->assertIdentical(Lingotek::STATUS_PENDING, $translation_service->getTargetStatus($component, 'es'));

    // Simulate the notification of content successfully translated.
    $url = Url::fromRoute('lingotek.notify', [], [
      'query' => [
        'project_id' => 'test_project',
        'document_id' => 'dummy-document-hash-id',
        'locale_code' => 'es-ES',
        'locale' => 'es_ES',
        'complete' => 'true',
        'type' => 'document_cancelled',
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
    $this->assertEquals($response['messages'][0], "Document $path cancelled in TMS.");

    $this->goToInterfaceTranslationManagementForm();
    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $translation_service->getTargetStatus($component, 'es'));
  }

}
