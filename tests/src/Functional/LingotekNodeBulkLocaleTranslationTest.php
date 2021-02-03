<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating a node into locales using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkLocaleTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'comment'];

  /**
   * The node.
   *
   * @var \Drupal\node\Entity\NodeInterface
   */
  protected $node;

  protected function setUp(): void {
    parent::setUp();

    // Create a locale outside of Lingotek dashboard.
    ConfigurableLanguage::create(['id' => 'de-at', 'name' => 'German (AT)'])->save();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add locales.
    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();
    ConfigurableLanguage::createFromLangcode('es-es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();

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
   * Tests that a node can be translated using the links on the management page.
   */
  public function testNodeTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_ES');
    $this->assertNoLingotekRequestTranslationLink('es_AR');
    $this->clickLink('EN');

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_ES');
    $this->assertLingotekRequestTranslationLink('es_AR');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the German (AT) translation.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id');
    $this->clickLink('DE-AT');
    $this->assertText("Locale 'de_AT' was added as a translation target for node Llamas are cool.");
    // Check that the requested locale is the right one.
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    \Drupal::state()->resetCache();

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_ES');
    $this->assertLingotekRequestTranslationLink('es_AR');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");
    // Check that the requested locale is the right one.
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_AR');
    $this->clickLink('ES');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('es_AR');
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'ES');

    // Check that the order of target languages is always alphabetical.
    $target_links = $this->xpath("//a[contains(@class,'language-icon')]");
    $this->assertEqual(count($target_links), 3, 'The three languages appear as targets');
    $this->assertEqual('DE-AT', $target_links[0]->getHtml(), 'DE-AT is the first language');
    $this->assertEqual('ES', $target_links[1]->getHtml(), 'ES is the second language');
    $this->assertEqual('ES-ES', $target_links[2]->getHtml(), 'ES-ES is the third language');
  }

  /**
   * Tests that source is updated after requesting translation.
   */
  public function testSourceUpdatedAfterRequestingTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id');

    // Request the German (AT) translation.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id');
    $this->clickLink('DE-AT');
    $this->assertText("Locale 'de_AT' was added as a translation target for node Llamas are cool.");

    // Check that the source status has been updated.
    $this->assertNoLingotekCheckSourceStatusLink();
  }

  /**
   * Tests that download uses one batch for downloading all translations.
   */
  public function testDownloadAllWithoutSplitDownload() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id');

    // Request the German (AT) translation.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id');
    $this->clickLink('ES-ES');
    $this->assertText("Locale 'es_ES' was added as a translation target for node Llamas are cool.");
    $this->clickLink('DE-AT');
    $this->assertText("Locale 'de_AT' was added as a translation target for node Llamas are cool.");

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertTargetStatus('DE-AT', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ES-ES', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
  }

  /**
   * Tests that download uses one batch for downloading all translations.
   */
  public function testDownloadAllWithSplitDownload() {
    $this->drupalGet('admin/lingotek/settings');
    $edit = ['split_download_all' => TRUE];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id');

    // Request the German (AT) translation.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id');
    $this->clickLink('ES-ES');
    $this->assertText("Locale 'es_ES' was added as a translation target for node Llamas are cool.");
    $this->clickLink('DE-AT');
    $this->assertText("Locale 'de_AT' was added as a translation target for node Llamas are cool.");

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertTargetStatus('DE-AT', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ES-ES', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
  }

}
