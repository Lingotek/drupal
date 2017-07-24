<?php

namespace Drupal\lingotek\Tests;

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
  public static $modules = ['entity_test'];

  protected function setUp() {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('entity_test_mul', 'entity_test_mul')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('entity_test_mul', 'entity_test_mul', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'entity_test_mul[entity_test_mul][enabled]' => 1,
      'entity_test_mul[entity_test_mul][profiles]' => 'automatic',
      'entity_test_mul[entity_test_mul][fields][name]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

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
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));

    $this->goToContentBulkManagementForm('entity_test_mul');

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/entity_test_mul/1?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->clickLink('EN');
    $this->assertText('Entity_test_mul Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->clickLink('EN');
    $this->assertText('The import for entity_test_mul Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for entity_test_mul Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for entity_test_mul Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->clickLink('ES');
    $this->assertText('The translation of entity_test_mul Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/es_MX');
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/dummy-document-hash-id/es_MX' and @target='_blank']");
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');
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
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));

    $this->goToContentBulkManagementForm('entity_test_mul');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/entity_test_mul/1?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'request_translation:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_translation:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'download:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/de_AT');
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/dummy-document-hash-id/de_AT' and @target='_blank']");
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');
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
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));

    $this->goToContentBulkManagementForm('entity_test_mul');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/entity_test_mul/1?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Assert that I could request translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');

    // Check statuses, that may been requested externally.
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Now Drupal knows that there are translations ready.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');

    // Even if I just add a new language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_DE?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');

    // Ensure locales are handled correctly by setting manual values.
    \Drupal::state()->set('lingotek.document_completion_statuses', ['de-AT' => 50, 'de-DE' => 100, 'es-MX' => 10]);
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Now Drupal knows which translations are ready.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_DE?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/ca_ES?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/it_IT?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');

    \Drupal::state()->set('lingotek.document_completion_statuses', ['it-IT' => 100, 'de-DE' => 50, 'es-MX' => 10]);
    // Check all statuses again.
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // All translations must be updated according exclusively with the
    // information from the TMS.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/de_DE?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/ca_ES?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/it_IT?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');

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
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('/entity_test_mul/add/entity_test_mul', $edit, t('Save'));

    $this->goToContentBulkManagementForm('entity_test_mul');

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/entity_test_mul/1?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');

    // The document has not been imported yet.
    \Drupal::state()->set('lingotek.document_status_completion', FALSE);
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // I can check current status, because it wasn't imported but it's not marked
    // as an error.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');

    // Check again, it succeeds.
    \Drupal::state()->set('lingotek.document_status_completion', TRUE);
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Assert that targets can be requested.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/entity_test_mul');
  }

}
