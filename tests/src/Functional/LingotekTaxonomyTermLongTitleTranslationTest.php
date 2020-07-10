<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\taxonomy\Entity\Term;
use Drupal\lingotek\Lingotek;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests translating a taxonomy term with a very long title that doesn't fit.
 *
 * @group lingotek
 */
class LingotekTaxonomyTermLongTitleTranslationTest extends LingotekTestBase {

  use TaxonomyTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'taxonomy', 'dblog'];

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * The term that should be translated.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $term;

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $this->vocabulary = $this->createVocabulary();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $this->vocabulary->id())->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $this->vocabulary->id(), TRUE);

    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $bundle = $this->vocabulary->id();
    $this->saveLingotekContentTranslationSettings([
      'taxonomy_term' => [
        $bundle => [
          'profiles' => 'automatic',
          'fields' => [
            'name' => 1,
            'description' => 1,
          ],
        ],
      ],
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'taxonomy_term_long_title');
  }

  /**
   * Tests that a term can be translated.
   */
  public function testTermTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);
    $bundle = $this->vocabulary->id();

    // Create a term.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));

    $this->term = Term::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['name'][0]['value']));
    $this->assertEqual(1, count($data['description'][0]));
    $this->assertTrue(isset($data['description'][0]['value']));

    // Check that the translate tab is in the taxonomy term.
    $this->drupalGet('taxonomy/term/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation. It must fail with a useful error message.
    $this->clickLink('Download completed translation');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name.');

    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name."]);
    $this->assert($status, 'A watchdog message was logged for the length of the field.');

    // Check the right class is added.
    $this->goToContentBulkManagementForm('taxonomy_term');
    $this->assertTargetStatus('ES', 'error');

    // Check that the Target Status is Error
    $this->term = Term::load(1);
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $this->assertIdentical(Lingotek::STATUS_ERROR, $content_translation_service->getTargetStatus($this->term, 'es'));

    // Check that the link works
    $this->clickLink('ES');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name.');

    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name."]);
    $this->assert($status, 'A watchdog message was logged for the length of the field.');
  }

  /**
   * Tests that a term can be translated when created via API with automated upload.
   */
  public function testTermTranslationViaAPIWithAutomatedUpload() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a term.
    $this->term = Term::create([
      'name' => 'Llamas are cool',
      'description' => 'Llamas are very cool',
      'langcode' => 'en',
      'vid' => $this->vocabulary->id(),
    ]);
    $this->term->save();

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['name'][0]['value']));
    $this->assertEqual(1, count($data['description'][0]));
    $this->assertTrue(isset($data['description'][0]['value']));

    // Check that the translate tab is in the taxonomy term.
    $this->drupalGet('taxonomy/term/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation. It must fail with a useful error message.
    $this->clickLink('Download completed translation');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name.');

    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name."]);
    $this->assert($status, 'A watchdog message was logged for the length of the field.');

    // Check the right class is added.
    $this->goToContentBulkManagementForm('taxonomy_term');
    $this->assertTargetStatus('ES', 'error');

    // Check that the Target Status is Error
    $this->term = Term::load(1);
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $this->assertIdentical(Lingotek::STATUS_ERROR, $content_translation_service->getTargetStatus($this->term, 'es'));

    // Check that the link works
    $this->clickLink('ES');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name.');

    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name."]);
    $this->assert($status, 'A watchdog message was logged for the length of the field.');
  }

  /**
   * Tests that a term can be translated when created via API with automated upload.
   */
  public function testTermTranslationViaAPIWithManualUpload() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $bundle = $this->vocabulary->id();
    $this->saveLingotekContentTranslationSettings([
      'taxonomy_term' => [
        $bundle => [
          'profiles' => 'manual',
          'fields' => [
            'name' => 1,
            'description' => 1,
          ],
        ],
      ],
    ]);

    // Create a term.
    $this->term = Term::create([
      'name' => 'Llamas are cool',
      'description' => 'Llamas are very cool',
      'langcode' => 'en',
      'vid' => $this->vocabulary->id(),
    ]);
    $this->term->save();

    // Check that the translate tab is in the taxonomy term.
    $this->drupalGet('taxonomy/term/1');
    $this->clickLink('Translate');

    // The document should not have been automatically uploaded, so let's upload it.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['name'][0]['value']));
    $this->assertEqual(1, count($data['description'][0]));
    $this->assertTrue(isset($data['description'][0]['value']));

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation. It must fail with a useful error message.
    $this->clickLink('Download completed translation');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name.');

    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name."]);
    $this->assert($status, 'A watchdog message was logged for the length of the field.');

    // Check the right class is added.
    $this->goToContentBulkManagementForm('taxonomy_term');
    $this->assertTargetStatus('ES', 'error');

    // Check that the Target Status is Error
    $this->term = Term::load(1);
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $this->assertIdentical(Lingotek::STATUS_ERROR, $content_translation_service->getTargetStatus($this->term, 'es'));

    // Check that the link works
    $this->clickLink('ES');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name.');

    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name."]);
    $this->assert($status, 'A watchdog message was logged for the length of the field.');
  }

  /**
   * Tests that a taxonomy term can be translated using the links on the management page.
   */
  public function testBulkTermTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $bundle = $this->vocabulary->id();
    $this->saveLingotekContentTranslationSettings([
      'taxonomy_term' => [
        $bundle => [
          'profiles' => 'manual',
          'fields' => [
            'name' => 1,
            'description' => 1,
          ],
        ],
      ],
    ]);

    // Create a term.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));

    $this->goToContentBulkManagementForm('taxonomy_term');

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink(1, 'taxonomy_term');
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_ES', 'dummy-document-hash-id', 'taxonomy_term');
    $this->clickLink('EN');
    $this->assertText('Taxonomy_term Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id', 'taxonomy_term');
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_ES', 'dummy-document-hash-id', 'taxonomy_term');
    $this->clickLink('EN');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_ES', 'dummy-document-hash-id', 'taxonomy_term');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");
    $this->assertIdentical('es_ES', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_ES', 'dummy-document-hash-id', 'taxonomy_term');
    $this->clickLink('ES');
    $this->assertIdentical('es_ES', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation. It must fail with a useful error message.
    $this->assertLingotekDownloadTargetLink('es_ES', 'dummy-document-hash-id', 'taxonomy_term');
    $this->clickLink('ES');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name.');

    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name."]);
    $this->assert($status, 'A watchdog message was logged for the length of the field.');

    // Check the right class is added.
    $this->goToContentBulkManagementForm('taxonomy_term');
    $this->assertTargetStatus('ES', 'error');
    // Check that the Target Status is Error
    $this->term = Term::load(1);
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $this->assertIdentical(Lingotek::STATUS_ERROR, $content_translation_service->getTargetStatus($this->term, 'es'));

    // Check that the link works
    $this->clickLink('ES');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name.');

    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "The download for taxonomy_term Llamas are cool failed because of the length of one field translation (es_ES) value: name."]);
    $this->assert($status, 'A watchdog message was logged for the length of the field.');
  }

  /**
   * Tests that a taxonomy_term can be translated using the actions on the management page.
   */
  public function testBulkTermTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    $bundle = $this->vocabulary->id();
    $this->saveLingotekContentTranslationSettings([
      'taxonomy_term' => [
        $bundle => [
          'profiles' => 'manual',
          'fields' => [
            'name' => 1,
            'description' => 1,
          ],
        ],
      ],
    ]);

    // Create a term.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));

    $this->goToContentBulkManagementForm('taxonomy_term');

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'taxonomy_term');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('taxonomy_term'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id', 'taxonomy_term');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('taxonomy_term'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the German (AT) translation.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id', 'taxonomy_term');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('de', 'taxonomy_term'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the German (AT) translation.
    $this->assertLingotekCheckTargetStatusLink('de_AT', 'dummy-document-hash-id', 'taxonomy_term');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => 'check_translation:de',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the German (AT) translation.
    $this->assertLingotekDownloadTargetLink('de_AT', 'dummy-document-hash-id', 'taxonomy_term');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => 'download:de',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Download translation. It must fail with a useful error message.
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation (de_AT) value: name.');
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "The download for taxonomy_term Llamas are cool failed because of the length of one field translation (de_AT) value: name."]);
    $this->assert($status, 'A watchdog message was logged for the length of the field.');

    // Check the right class is added.
    $this->goToContentBulkManagementForm('taxonomy_term');
    $this->assertTargetStatus('DE', Lingotek::STATUS_ERROR);

    // Check that the Target Status is Error
    $this->term = Term::load(1);
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $this->assertIdentical(Lingotek::STATUS_ERROR, $content_translation_service->getTargetStatus($this->term, 'de'));

    // Check that the link works
    $this->clickLink('DE');
    $this->assertText('The download for taxonomy_term Llamas are cool failed because of the length of one field translation (de_AT) value: name.');

    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "The download for taxonomy_term Llamas are cool failed because of the length of one field translation (de_AT) value: name."]);
    $this->assert($status, 'A watchdog message was logged for the length of the field.');
  }

}
