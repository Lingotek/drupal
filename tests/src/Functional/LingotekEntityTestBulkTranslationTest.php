<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating the entity test using the bulk management form.
 *
 * @group lingotek
 */
class LingotekEntityTestBulkTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_tasks_block', [
      'region' => 'content',
      'weight' => -10,
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('entity_test_mul', 'entity_test_mul')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('entity_test_mul', 'entity_test_mul', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettings([
      'entity_test_mul' => [
        'entity_test_mul' => [
          'profiles' => 'automatic',
          'fields' => [
            'name' => 1,
          ],
        ],
      ],
    ]);
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'entity_test_mul');
  }

  /**
   * Tests that a translatable entity can be translated using the links on the management page.
   */
  public function testEntityTestTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a entity_test_mul.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['field_test_text[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));

    $this->goToContentBulkManagementForm('entity_test_mul');

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink(1, 'entity_test_mul');
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('EN');
    $this->assertText('Entity_test_mul Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id', 'entity_test_mul');
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('EN');
    $this->assertText('The import for entity_test_mul Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for entity_test_mul Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for entity_test_mul Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('ES');
    $this->assertText('The translation of entity_test_mul Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');
  }

  /**
   * Tests that a entity_test_mul can be translated using the actions on the management page.
   */
  public function testEntityTestTranslationUsingActions() {
    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Create a entity_test_mul.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['field_test_text[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));

    $this->goToContentBulkManagementForm('entity_test_mul');

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id', 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the German (AT) translation.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id', 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('de', 'entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the German (AT) translation.
    $this->assertLingotekCheckTargetStatusLink('de_AT', 'dummy-document-hash-id', 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => 'check_translation:de',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the German (AT) translation.
    $this->assertLingotekDownloadTargetLink('de_AT', 'dummy-document-hash-id', 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => 'download:de',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('de_AT', 'dummy-document-hash-id', 'DE');
  }

  /**
   * Tests that all the statuses are set when using the Check Translations action.
   */
  public function testCheckTranslationsAction() {
    // Add a couple of languages.
    ConfigurableLanguage::create(['id' => 'de_AT', 'label' => 'German (Austria)'])->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();
    ConfigurableLanguage::createFromLangcode('ca')->setThirdPartySetting('lingotek', 'locale', 'ca_ES')->save();
    ConfigurableLanguage::createFromLangcode('it')->setThirdPartySetting('lingotek', 'locale', 'it_IT')->save();

    // Create a entity_test_mul.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['field_test_text[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));

    $this->goToContentBulkManagementForm('entity_test_mul');

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id', 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Assert that I could request translations.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id', 'entity_test_mul');

    // Check statuses, that may been requested externally.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Now Drupal knows that there are translations ready.
    $this->assertLingotekDownloadTargetLink('de_AT', 'dummy-document-hash-id', 'entity_test_mul');
    $this->assertLingotekDownloadTargetLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');

    // Even if I just add a new language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertLingotekDownloadTargetLink('de_DE', 'dummy-document-hash-id', 'entity_test_mul');

    // Ensure locales are handled correctly by setting manual values.
    \Drupal::state()->set('lingotek.document_completion_statuses', ['de-AT' => 50, 'de-DE' => 100, 'es-MX' => 10]);
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Now Drupal knows which translations are ready.
    $this->assertNoLingotekDownloadTargetLink('de_AT', 'dummy-document-hash-id', 'entity_test_mul');
    $this->assertLingotekDownloadTargetLink('de_DE', 'dummy-document-hash-id', 'entity_test_mul');
    $this->assertNoLingotekDownloadTargetLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->assertLingotekRequestTranslationLink('ca_ES', 'dummy-document-hash-id', 'entity_test_mul');
    $this->assertLingotekRequestTranslationLink('it_IT', 'dummy-document-hash-id', 'entity_test_mul');

    \Drupal::state()->set('lingotek.document_completion_statuses', ['it-IT' => 100, 'de-DE' => 50, 'es-MX' => 10]);
    // Check all statuses again.
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // All translations must be updated according exclusively with the
    // information from the TMS.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id', 'entity_test_mul');
    $this->assertLingotekCheckTargetStatusLink('de_DE', 'dummy-document-hash-id', 'entity_test_mul');
    $this->assertLingotekCheckTargetStatusLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->assertLingotekRequestTranslationLink('ca_ES', 'dummy-document-hash-id', 'entity_test_mul');
    $this->assertLingotekDownloadTargetLink('it_IT', 'dummy-document-hash-id', 'entity_test_mul');

    // Source status must be kept too.
    $this->assertSourceStatusStateCount(Lingotek::STATUS_CURRENT, 'EN', 1);
  }

  /**
   * Tests that all the statuses are set when using the Check Translations action.
   */
  public function testCheckSourceStatusNotCompleted() {
    // Create a entity_test_mul.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['field_test_text[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));

    $this->goToContentBulkManagementForm('entity_test_mul');

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id', 'entity_test_mul');

    // The document has not been imported yet.
    \Drupal::state()->set('lingotek.document_status_completion', FALSE);
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // I can check current status, because it wasn't imported but it's not marked
    // as an error.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id', 'entity_test_mul');

    // Check again, it succeeds.
    \Drupal::state()->set('lingotek.document_status_completion', TRUE);
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Assert that targets can be requested.
    $this->assertLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
  }

  /**
   * Tests that a entity without owner gets uploaded correctly.
   */
  public function testUploadingWithoutAuthor() {
    $entity = EntityTestMul::create(['type' => 'entity_test_mul', 'name' => 'Test article']);
    $entity->setOwnerId(NULL);
    $entity->save();
    $this->assertNull($entity->getOwner());
    $this->drupalGet('/entity_test_mul/manage/1');
  }

  /**
   * Tests that unrequested locales are not marked as error when downloading all.
   */
  public function testTranslationDownloadWithUnrequestedLocales() {
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    ConfigurableLanguage::createFromLangcode('it')->setThirdPartySetting('lingotek', 'locale', 'it_IT')->save();

    // Create a entity_test_mul.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['field_test_text[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));

    $this->goToContentBulkManagementForm('entity_test_mul');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id', 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for entity_test_mul Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for entity_test_mul Llamas are cool is ready for download.');

    // Download all the translations.
    $this->assertLingotekDownloadTargetLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // The translations not requested shouldn't change its status.
    $this->assertLingotekRequestTranslationLink('de_DE', 'dummy-document-hash-id', 'entity_test_mul');
    $this->assertLingotekRequestTranslationLink('it_IT', 'dummy-document-hash-id', 'entity_test_mul');

    // They aren't marked as error.
    $this->assertNoTargetError('Llamas are cool', 'DE', 'de_DE');
    $this->assertNoTargetError('Llamas are cool', 'IT', 'it_IT');
  }

  /**
   * Tests that current locales are not cleared when checking statuses.
   */
  public function testCheckTranslationsWithDownloadedLocales() {
    ConfigurableLanguage::createFromLangcode('de')
      ->setThirdPartySetting('lingotek', 'locale', 'de_DE')
      ->save();
    ConfigurableLanguage::createFromLangcode('it')
      ->setThirdPartySetting('lingotek', 'locale', 'it_IT')
      ->save();

    // Create a entity_test_mul.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['field_test_text[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));

    $this->goToContentBulkManagementForm('entity_test_mul');

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id', 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for entity_test_mul Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    \Drupal::state()->resetCache();

    // Request italian.
    $this->assertLingotekRequestTranslationLink('it_IT', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('IT');
    $this->assertText("Locale 'it_IT' was added as a translation target for entity_test_mul Llamas are cool.");
    $this->assertIdentical('it_IT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for entity_test_mul Llamas are cool is ready for download.');

    \Drupal::state()->resetCache();

    // Check status of the Italian translation.
    $this->assertLingotekCheckTargetStatusLink('it_IT', 'dummy-document-hash-id', 'entity_test_mul');
    $this->clickLink('IT');
    $this->assertIdentical('it_IT', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The it_IT translation for entity_test_mul Llamas are cool is ready for download.');

    // Download all the translations.
    $this->assertLingotekDownloadTargetLink('es_MX', 'dummy-document-hash-id', 'entity_test_mul');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // They are marked with the right status.
    $this->assertTargetStatus('ES', 'current');
    $this->assertTargetStatus('IT', 'current');
    $this->assertTargetStatus('DE', 'request');

    // We check all translations.
    \Drupal::state()->set('lingotek.document_completion_statuses', ['es-ES' => 100, 'it-IT' => 100]);
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('entity_test_mul'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // And statuses should remain the same.
    $this->assertTargetStatus('ES', 'current');
    $this->assertTargetStatus('IT', 'current');
    $this->assertTargetStatus('DE', 'request');
  }

  /**
   * Test that when a node is created we cannot assign a profile if using a restricted user.
   */
  public function testCannotAssignProfileToContentWithoutRightPermission() {
    $editor = $this->drupalCreateUser(['administer entity_test content', 'view test entity']);
    // Login as editor.
    $this->drupalLogin($editor);
    // Get the node form.
    $this->drupalGet('entity_test_mul/add/entity_test_mul');
    // Assert translation profile cannot be assigned.
    $this->assertNoField('lingotek_translation_management[lingotek_translation_profile]');

    $translation_manager = $this->drupalCreateUser([
      'administer entity_test content',
      'view test entity',
      'assign lingotek translation profiles',
    ]);
    // Login as translation manager.
    $this->drupalLogin($translation_manager);
    // Get the node form.
    $this->drupalGet('entity_test_mul/add/entity_test_mul');
    // Assert translation profile can be assigned.
    $this->assertField('lingotek_translation_management[lingotek_translation_profile]');

    // Create a entity_test_mul.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['field_test_text[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));
    $this->clickLink('Edit');

    $this->assertFieldById('edit-lingotek-translation-management-lingotek-translation-profile', 'manual');
  }

}
