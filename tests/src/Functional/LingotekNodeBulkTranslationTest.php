<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node'];

  /**
   * A node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

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

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX');

    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_MX');

    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('es_MX');
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');
  }

  /**
   * Tests that a node cannot be translated if not configured, and will provide user-friendly messages.
   */
  public function testNodeTranslationMessageWhenBundleNotConfiguredWithLinks() {
    $assert_session = $this->assertSession();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Pages are cool';
    $edit['body[0][value]'] = 'Pages are very cool';
    $edit['langcode[0][value]'] = 'en';
    $this->drupalPostForm('node/add/page', $edit, t('Save'));

    $this->goToContentBulkManagementForm();

    $assert_session->pageTextContains('Not enabled');
    $this->clickLink('EN');
    $assert_session->pageTextContains('Cannot upload Page Pages are cool. That Content type is not enabled for Lingotek translation.');
  }

  /**
   * Tests that a node cannot be translated if not configured, and will provide user-friendly messages.
   */
  public function testNodeTranslationMessageWhenBundleNotConfiguredWithActions() {
    $assert_session = $this->assertSession();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Pages are cool';
    $edit['body[0][value]'] = 'Pages are very cool';
    $edit['langcode[0][value]'] = 'en';
    $this->drupalPostForm('node/add/page', $edit, t('Save'));

    $this->goToContentBulkManagementForm();

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertIdentical(NULL, \Drupal::state()
      ->get('lingotek.uploaded_locale'));
    $assert_session->pageTextContains('Cannot upload Page Pages are cool. That Content type is not enabled for Lingotek translation.');
  }

  /**
   * Tests that a node can be translated using the actions on the management page.
   */
  public function testNodeTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')
      ->setThirdPartySetting('lingotek', 'locale', 'de_AT')
      ->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the German (AT) translation.
    $this->assertLingotekRequestTranslationLink('de_AT');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    // Check status of the German (AT) translation.
    $this->assertLingotekCheckTargetStatusLink('de_AT');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()
      ->get('lingotek.checked_target_locale'));

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('de_AT');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('de_AT', 'dummy-document-hash-id', 'DE');
  }

  /**
   * Tests that a node can be translated using the actions on the management page for multiple locales.
   */
  public function testNodeTranslationUsingActionsForMultipleLocales() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add two languages.
    ConfigurableLanguage::createFromLangcode('de')
      ->setThirdPartySetting('lingotek', 'locale', 'de_AT')
      ->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request all translations.
    $this->assertLingotekRequestTranslationLink('de_AT');
    $this->assertLingotekRequestTranslationLink('es_MX');

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    // Check all statuses.
    $this->assertLingotekCheckTargetStatusLink('de_AT');
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Download all translations.
    $this->assertLingotekDownloadTargetLink('de_AT');
    $this->assertLingotekDownloadTargetLink('es_MX');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');
    $this->assertLingotekWorkbenchLink('de_AT', 'dummy-document-hash-id', 'DE');
  }

  /**
   * Tests that the EDITED status is not assigned if the content is not uploaded.
   */
  public function testNodeNotMarkedAsEditedIfNotUploaded() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add two languages.
    ConfigurableLanguage::createFromLangcode('de')
      ->setThirdPartySetting('lingotek', 'locale', 'de_AT')
      ->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->goToContentBulkManagementForm();

    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);
  }

  /**
   * Tests that a node can be translated using the actions on the management page for multiple locales after editing it.
   */
  public function testNodeTranslationUsingActionsForMultipleLocalesAfterEditing() {
    $this->testNodeTranslationUsingActionsForMultipleLocales();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1);

    $this->goToContentBulkManagementForm();

    // Let's upload the edited content so it's updated and downloadable.
    $this->clickLink('EN');
    // Check the source status is current.
    $this->clickLink('EN');

    // Check all statuses, after being edited and the source re-uploaded
    // Should be in STATUS_PENDING
    $this->assertLingotekCheckTargetStatusLink('de_AT', 'dummy-document-hash-id-1');
    $this->assertLingotekCheckTargetStatusLink('es_MX', 'dummy-document-hash-id-1');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Download all translations.
    $this->assertLingotekDownloadTargetLink('de_AT', 'dummy-document-hash-id-1');
    $this->assertLingotekDownloadTargetLink('es_MX', 'dummy-document-hash-id-1');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id-1', 'ES');
    $this->assertLingotekWorkbenchLink('de_AT', 'dummy-document-hash-id-1', 'DE');
  }

  public function testNodeTranslationUsingActionsForMultipleLocalesAfterEditingWithPendingPhases() {
    $this->testNodeTranslationUsingActionsForMultipleLocales();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1);

    $this->goToContentBulkManagementForm();

    // Let's upload the edited content so it's updated and downloadable.
    $this->clickLink('EN');
    // Check the source status is current.
    $this->clickLink('EN');

    // Ensure we won't get a completed document because there are phases pending.
    \Drupal::state()->set('lingotek.document_completion', FALSE);

    // Check all statuses, after being edited and the source re-uploaded
    // Should be in STATUS_PENDING
    $this->assertLingotekCheckTargetStatusLink('de_AT', 'dummy-document-hash-id-1');
    $this->assertLingotekCheckTargetStatusLink('es_MX', 'dummy-document-hash-id-1');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Ensure that the statuses are set to PENDING since the source has been
    // reuploaded and the targets are being translated. It is possible that
    // some of the translation is finished and could be downloaded, but that
    // should be marked as STATUS_READY_INTERIM but that has not been
    // implemented yet.
    // TODO: update test to check that status is STATUS_READY_INTERIM and then
    // they can be downloaded and then the status is set to STATUS_INTERMEDIATE
    // see ticket: https://www.drupal.org/node/2850548
    // Check the status is PENDING for Spanish and German.
    $this->assertTargetStatus('DE', 'pending');
    $this->assertTargetStatus('ES', 'pending');
  }

  /**
   * Tests that a node can be translated using the actions on the management page.
   */
  public function testNodeMultipleLanguageTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')
      ->setThirdPartySetting('lingotek', 'locale', 'de_AT')
      ->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Create another node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool 2';
    $edit['body[0][value]'] = 'Llamas are very cool 2';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);

    $key1 = $this->getBulkSelectionKey('en', 1);
    $key2 = $this->getBulkSelectionKey('en', 2);

    $edit = [
      $key1 => TRUE,
      $key2 => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id');
    $this->assertLingotekCheckSourceStatusLink('dummy-document-hash-id-1');
    $edit = [
      $key1 => TRUE,
      $key2 => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request all the translations.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id');
    $this->assertLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id');
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id-1');
    $this->assertLingotekRequestTranslationLink('es_MX', 'dummy-document-hash-id-1');
    $edit = [
      $key1 => TRUE,
      $key2 => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check status of all the translations.
    $this->assertLingotekCheckTargetStatusLink('de_AT', 'dummy-document-hash-id');
    $this->assertLingotekCheckTargetStatusLink('es_MX', 'dummy-document-hash-id');
    $this->assertLingotekCheckTargetStatusLink('de_AT', 'dummy-document-hash-id-1');
    $this->assertLingotekCheckTargetStatusLink('es_MX', 'dummy-document-hash-id-1');
    $edit = [
      $key1 => TRUE,
      $key2 => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Download all the translations.
    $this->assertLingotekDownloadTargetLink('de_AT', 'dummy-document-hash-id');
    $this->assertLingotekDownloadTargetLink('es_MX', 'dummy-document-hash-id');
    $this->assertLingotekDownloadTargetLink('de_AT', 'dummy-document-hash-id-1');
    $this->assertLingotekDownloadTargetLink('es_MX', 'dummy-document-hash-id-1');

    $edit = [
      $key1 => TRUE,
      $key2 => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
  }

  public function testAddContentLinkPresent() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_actions_block');

    $this->goToContentBulkManagementForm();

    // There should be a link for adding content.
    $this->clickLink('Add content');

    // And we should have been redirected to the article form.
    $this->assertUrl(Url::fromRoute('node.add', ['node_type' => 'article']));
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testEditedNodeTranslationUsingLinks() {
    // We need a node with translations first.
    $this->testNodeTranslationUsingLinks();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')
      ->setThirdPartySetting('lingotek', 'locale', 'eu_ES')
      ->save();

    // Add a language so we can check that it's not marked as for requesting if
    // it was already requested.
    ConfigurableLanguage::createFromLangcode('ko')
      ->setThirdPartySetting('lingotek', 'locale', 'ko_KR')
      ->save();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $edit['body[0][value]'] = 'Llamas are very cool EDITED';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->goToContentBulkManagementForm();

    // Check the source status is edited.
    $this->assertSourceStatus('EN', Lingotek::STATUS_EDITED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $this->assertNoTargetStatus('EU', Lingotek::STATUS_EDITED);
    $this->assertTargetStatus('EU', Lingotek::STATUS_REQUEST);

    // Request korean, with outdated content available.
    $this->clickLink('KO');
    $this->assertText("Locale 'ko_KR' was added as a translation target for node Llamas are cool EDITED.");

    // Reupload the content.
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool EDITED has been updated.');

    // Korean should be marked as requested, so we can check target.
    $this->assertTargetStatus('KO', 'pending');

    // Recheck status.
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool EDITED is complete.');

    // Korean should still be marked as requested, so we can check target.
    $this->assertTargetStatus('KO', 'pending');

    // Check the translation after having been edited.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertTargetStatus('ES', 'ready');

    // Download the translation.
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool EDITED into es_MX has been downloaded.');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testEditedNodeTranslationUsingLinksInAutomaticUploadsMode() {
    // We need a node with translations first.
    $this->testNodeTranslationUsingLinks();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')
      ->setThirdPartySetting('lingotek', 'locale', 'eu_ES')
      ->save();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $edit['body[0][value]'] = 'Llamas are very cool EDITED';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->goToContentBulkManagementForm();

    // Check the source status is importing.
    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $this->assertNoTargetStatus('EU', Lingotek::STATUS_EDITED);
    $this->assertTargetStatus('EU', Lingotek::STATUS_REQUEST);

    // Recheck status.
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool EDITED is complete.');

    // Check the translation after having been edited.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertTargetStatus('ES', 'ready');

    // Download the translation.
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool EDITED into es_MX has been downloaded.');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testAddingLanguageAllowsRequesting() {
    // We need a node with translations first.
    $this->testNodeTranslationUsingLinks();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('ca')->save();

    $this->goToContentBulkManagementForm();

    // There is a link for requesting the Catalan translation.
    $this->assertLingotekRequestTranslationLink('ca_ES');
    $this->clickLink('CA');
    $this->assertText("Locale 'ca_ES' was added as a translation target for node Llamas are cool.");
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testEditedTranslationIsMarkedAsTargetEditedAndNotTheSource() {
    // We need a node with translations first.
    $this->testNodeTranslationUsingLinks();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Edit the node translation.
    $edit = [];
    $edit['title[0][value]'] = 'Las llamas son chulas EDITED';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1, 'es');

    $this->assertText('Las llamas son chulas EDITED');

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->goToContentBulkManagementForm();

    // Check the source status is edited.
    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ES', Lingotek::STATUS_EDITED);

    // Recheck status.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertTargetStatus('ES', Lingotek::STATUS_READY);

    // Download the translation.
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertTargetStatus('ES', Lingotek::STATUS_CURRENT);

    $this->drupalGet('es/node/1');
    $this->assertNoText('Las llamas son chulas EDITED');
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

    $this->goToContentBulkManagementForm();

    // There is no link for the Spanish translation.
    $assert_session->linkNotExists('ES');
    $assert_session->linkExists('CA');
  }

  /**
   * Test that when a node is uploaded in a different locale that locale is used.
   */
  public function testAddingContentInDifferentLocale() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool es-MX';
    $edit['body[0][value]'] = 'Llamas are very cool es-MX';
    $edit['langcode[0][value]'] = 'es';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm('node', 'es');

    // Clicking Spanish must init the upload of content.
    $this->assertLingotekUploadLink(1, 'node', 'es');
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('en_US', 'dummy-document-hash-id', 'node', 'es');
    $this->clickLink('ES');
    $this->assertText('Node Llamas are cool es-MX has been uploaded.');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.uploaded_locale'));
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('The upload for node Llamas are cool failed. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', 'error');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnError() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must succeed.
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToContentBulkManagementForm();

    // Update the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('The update for node Llamas are cool EDITED failed. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool EDITED has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAPaymentRequiredError() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must succeed.
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    $this->goToContentBulkManagementForm();

    // Update the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool EDITED has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentArchivedError() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must succeed.
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    $this->goToContentBulkManagementForm();

    // Update the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Document node Llamas are cool EDITED has been archived. Please upload again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', FALSE);
    // We cannot click, as for views there won't be a link.
    // $this->clickLink('EN');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    // We cannot click, as for views there won't be a link.
    // $this->assertText('Node Llamas are cool EDITED has been uploaded.');
    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentLockedError() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must succeed.
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    $this->goToContentBulkManagementForm();

    // Update the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('Document node Llamas are cool EDITED has a new version. The document id has been updated for all future interactions. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_EDITED);

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_EDITED, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool EDITED has been updated.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnErrorUsingActions() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must fail.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('The upload for node Llamas are cool failed. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', 'error');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAPaymentRequiredErrorUsingActions() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must fail.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnErrorUsingActions() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must succeed.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertSourceStatus('EN', 'importing');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node.
    $edit = ['title[0][value]' => 'Llamas are cool EDITED'];
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToContentBulkManagementForm();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('The update for node Llamas are cool EDITED failed. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', 'error');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool EDITED has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentArchivedErrorUsingActions() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must succeed.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);

    // Check upload.
    $this->clickLink('EN');

    // Edit the node.
    $edit = ['title[0][value]' => 'Llamas are cool EDITED'];
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', TRUE);

    $this->goToContentBulkManagementForm();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Document node Llamas are cool EDITED has been archived. Please upload again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_UNTRACKED, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_archived_error_in_update', FALSE);
    // We cannot click, as for views there won't be a link.
    // $this->clickLink('EN');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    // We cannot click, as for views there won't be a link.
    // $this->assertText('Node Llamas are cool EDITED has been uploaded.');
    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithADocumentLockedErrorUsingActions() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must succeed.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);

    // Check upload.
    $this->clickLink('EN');

    // Edit the node.
    $edit = ['title[0][value]' => 'Llamas are cool EDITED'];
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', TRUE);

    $this->goToContentBulkManagementForm();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Document node Llamas are cool EDITED has a new version. The document id has been updated for all future interactions. Please try again.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_EDITED);

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_EDITED, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_document_locked_error_in_update', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool EDITED has been updated.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAPaymentRequiredErrorUsingActions() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must succeed.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);

    // Check upload.
    $this->clickLink('EN');

    // Edit the node.
    $edit = ['title[0][value]' => 'Llamas are cool EDITED'];
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', TRUE);

    $this->goToContentBulkManagementForm();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');

    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_payment_required_error_in_update', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool EDITED has been updated.');
  }

  /**
   * Tests that all the statuses are set when using the Check Translations action.
   */
  public function testCheckTranslationsAction() {
    // Add a couple of languages.
    ConfigurableLanguage::create([
      'id' => 'de_AT',
      'label' => 'German (Austria)',
    ])->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();
    ConfigurableLanguage::createFromLangcode('ca')
      ->setThirdPartySetting('lingotek', 'locale', 'ca_ES')
      ->save();
    ConfigurableLanguage::createFromLangcode('it')
      ->setThirdPartySetting('lingotek', 'locale', 'it_IT')
      ->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Assert that I could request translations.
    $this->assertLingotekRequestTranslationLink('de_AT', 'dummy-document-hash-id');

    // Check statuses, that may been requested externally.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Now Drupal knows that there are translations ready.
    $this->assertLingotekDownloadTargetLink('de_AT');
    $this->assertLingotekDownloadTargetLink('es_MX');

    // Even if I just add a new language.
    ConfigurableLanguage::createFromLangcode('de')
      ->setThirdPartySetting('lingotek', 'locale', 'de_DE')
      ->save();
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertLingotekDownloadTargetLink('de_DE');

    // Ensure locales are handled correctly by setting manual values.
    \Drupal::state()
      ->set('lingotek.document_completion_statuses', [
        'de-AT' => 50,
        'de-DE' => 100,
        'es-MX' => 10,
      ]);
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Now Drupal knows which translations are ready.
    $this->assertNoLingotekDownloadTargetLink('de_AT');
    $this->assertLingotekDownloadTargetLink('de_DE');
    $this->assertNoLingotekDownloadTargetLink('es_MX');
    $this->assertLingotekRequestTranslationLink('ca_ES');
    $this->assertLingotekRequestTranslationLink('it_IT');

    \Drupal::state()
      ->set('lingotek.document_completion_statuses', [
        'it-IT' => 100,
        'de-DE' => 50,
        'es-MX' => 10,
      ]);
    // Check all statuses again.
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // All translations must be updated according exclusively with the
    // information from the TMS.
    $this->assertLingotekRequestTranslationLink('de_AT');
    $this->assertLingotekCheckTargetStatusLink('de_DE');
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $this->assertLingotekRequestTranslationLink('ca_ES');
    $this->assertLingotekDownloadTargetLink('it_IT');

    // Source status must be kept too.
    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertSourceStatusStateCount(Lingotek::STATUS_CURRENT, 'EN', 1);
  }

  /**
   * Tests that all the statuses are set when using the Check Translations action.
   */
  public function testCheckSourceStatusNotCompleted() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();

    // The document has not been imported yet.
    \Drupal::state()->set('lingotek.document_status_completion', FALSE);
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // I can check current status, because it wasn't imported but it's not marked
    // as an error.
    $this->assertLingotekCheckSourceStatusLink();

    // Check again, it succeeds.
    \Drupal::state()->set('lingotek.document_status_completion', TRUE);
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Assert that targets can be requested.
    $this->assertLingotekRequestTranslationLink('es_MX');
  }

  /**
   * Tests that all the statuses are set when using the Check Translations action.
   */
  public function testCheckSourceStatusNotCompletedAndUploadedLongAgo() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();

    // The document has not been imported yet, and it was uploaded long ago.
    \Drupal::state()->set('lingotek.document_status_completion', FALSE);
    // TODO: Remove the test in between these comments once 4.0.0 is released
    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $translation_service->setLastUploaded($node, 0);
    $node->setChangedTime(\Drupal::time()->getRequestTime() - 100000)->save();

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // It was marked as error and I can try the update.
    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);
    // END TODO

    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $translation_service->setLastUploaded($node, \Drupal::time()->getRequestTime() - 100000);

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // It was marked as error and I can try the update.
    // Check the right class is added.
    $this->assertSourceStatus('EN', Lingotek::STATUS_ERROR);

    $this->assertLingotekUpdateLink();
  }

  /**
   * Tests that we manage errors when using the Check Source link.
   */
  public function testCheckSourceStatusWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_check_source_status', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();

    $this->clickLink('EN');

    // We failed at checking status, but we don't know what happened.
    // So we don't mark as error but keep it on importing.
    $this->assertNoSourceStatus('EN', Lingotek::STATUS_REQUEST);
    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertText('The check for node status failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the Check Source action.
   */
  public function testCheckSourceStatusActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_check_source_status', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at checking status, but we don't know what happened.
    // So we don't mark as error but keep it on importing.
    $this->assertNoSourceStatus('EN', Lingotek::STATUS_REQUEST);
    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertText('The upload status check for node Llamas are cool translation failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $this->clickLink('ES');

    // We failed at requesting a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('The translation request for node failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $this->clickLink('ES');

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithADocumentArchivedError() {
    \Drupal::state()->set('lingotek.must_document_archived_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $this->clickLink('ES');

    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->assertText('Document node Llamas are cool has been archived. Please upload again.');
  }

  /**
   * Tests that we manage errors when using the request translation link.
   */
  public function testRequestTranslationWithADocumentLockedError() {
    \Drupal::state()->set('lingotek.must_document_locked_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $this->clickLink('ES');

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Document node Llamas are cool has a new version. The document id has been updated for all future interactions. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request translation action.
   */
  public function testRequestTranslationWithActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at requesting a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('The request for node Llamas are cool translation failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request translation action.
   */
  public function testRequestTranslationWithActionWithADocumentArchivedError() {
    \Drupal::state()->set('lingotek.must_document_archived_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->assertText('Document node Llamas are cool has been archived. Please upload again.');
  }

  /**
   * Tests that we manage errors when using the request translation action.
   */
  public function testRequestTranslationWithActionWithADocumentLockedError() {
    \Drupal::state()->set('lingotek.must_document_locked_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Document node Llamas are cool has a new version. The document id has been updated for all future interactions. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request translation action.
   */
  public function testRequestTranslationWithActionWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
  }

  /**
   * Tests that we manage errors when using the request all translations action.
   */
  public function testRequestAllTranslationsWithActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at requesting a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('The request for node Llamas are cool translation failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request all translations action.
   */
  public function testRequestAllTranslationsWithActionWithADocumentArchivedError() {
    \Drupal::state()->set('lingotek.must_document_archived_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_UNTRACKED);
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->assertText('Document node Llamas are cool has been archived. Please upload again.');
  }

  /**
   * Tests that we manage errors when using the request all translations action.
   */
  public function testRequestAllTranslationsWithActionWithADocumentLockedError() {
    \Drupal::state()->set('lingotek.must_document_locked_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Document node Llamas are cool has a new version. The document id has been updated for all future interactions. Please try again.');
  }

  /**
   * Tests that we manage errors when using the request all translations action.
   */
  public function testRequestAllTranslationsWithActionWithAPaymentRequiredError() {
    \Drupal::state()->set('lingotek.must_payment_required_error_in_request_translation', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertSourceStatus('EN', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('Community has been disabled. Please contact support@lingotek.com to re-enable your community.');
  }

  /**
   * Tests that we manage errors when using the check translation status link.
   */
  public function testCheckTranslationStatusWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_check_target_status', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    // Request the translation.
    $this->clickLink('ES');
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);

    // Check the status of the translation.
    $this->clickLink('ES');

    // We failed at checking a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);
    $this->assertText('The request for node translation status failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the check translation status action.
   */
  public function testCheckTranslationStatusWithActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_check_target_status', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at checking a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('The request for node Llamas are cool translation status failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the check all translations statuses action.
   */
  public function testCheckAllTranslationsStatusesWithActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_check_target_status', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at checking a translation, but we don't know what happened.
    // So we don't mark as error but keep it on request.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('The request for node Llamas are cool translation status failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the download translation link.
   */
  public function testDownloadTranslationWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_download', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    // Request the translation.
    $this->clickLink('ES');
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);

    // Check the status of the translation.
    $this->clickLink('ES');
    $this->assertTargetStatus('ES', Lingotek::STATUS_READY);

    // Download translation.
    $this->clickLink('ES');

    // We failed at downloading a translation. Mark as error.
    $this->assertTargetStatus('ES', Lingotek::STATUS_ERROR);
    $this->assertText('The download for node Llamas are cool failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the download translation action.
   */
  public function testDownloadTranslationWithActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_download', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    // Request the translation.
    $this->clickLink('ES');
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);

    // Check the status of the translation.
    $this->clickLink('ES');
    $this->assertTargetStatus('ES', Lingotek::STATUS_READY);

    // Download translation.
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at downloading a translation. Mark as error.
    $this->assertTargetStatus('ES', Lingotek::STATUS_ERROR);
    $this->assertText('The download for node Llamas are cool translation failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the download all translations action.
   */
  public function testDownloadAllTranslationsWithActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_download', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();

    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    // Request the translation.
    $this->clickLink('ES');
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);

    // Check the status of the translation.
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertTargetStatus('ES', Lingotek::STATUS_READY);

    // Download translation.
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at downloading a translation. Mark as error.
    $this->assertTargetStatus('ES', Lingotek::STATUS_ERROR);
    $this->assertText('The download for node Llamas are cool translation failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the cancel action.
   */
  public function testCancelWithActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_cancel', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    // Cancel translation.
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at cancelling.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('The cancellation of node Llamas are cool failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the cancel action.
   */
  public function testCancelTargetWithActionWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_cancel', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    // Cancel translation.
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancelTarget('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We failed at cancelling.
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertText('The cancellation of node Llamas are cool translation to es failed. Please try again.');
  }

  /**
   * Tests that we manage errors when using the cancel action.
   */
  public function testCancelTargetStatusAlwaysKept() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    // I can request a translation
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);

    // Cancel translation.
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancelTarget('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // We succeeded at cancelling.
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);

    \Drupal::state()->set('lingotek.document_completion_statuses', ['es_MX' => 'CANCELLED']);

    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Still target is cancelled.
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);
  }

  /**
   * Tests that unrequested locales are not marked as error when downloading all.
   */
  public function testTranslationDownloadWithUnrequestedLocales() {
    ConfigurableLanguage::createFromLangcode('de')
      ->setThirdPartySetting('lingotek', 'locale', 'de_DE')
      ->save();
    ConfigurableLanguage::createFromLangcode('it')
      ->setThirdPartySetting('lingotek', 'locale', 'it_IT')
      ->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Download all the translations.
    $this->assertLingotekDownloadTargetLink('es_MX');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // The translations not requested shouldn't change its status.
    $this->assertLingotekRequestTranslationLink('de_DE');
    $this->assertLingotekRequestTranslationLink('it_IT');

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

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    \Drupal::state()->resetCache();

    // Request italian.
    $this->assertLingotekRequestTranslationLink('it_IT');
    $this->clickLink('IT');
    $this->assertText("Locale 'it_IT' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('it_IT', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    \Drupal::state()->resetCache();

    // Check status of the Italian translation.
    $this->assertLingotekCheckTargetStatusLink('it_IT');
    $this->clickLink('IT');
    $this->assertIdentical('it_IT', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText('The it_IT translation for node Llamas are cool is ready for download.');

    // Download all the translations.
    $this->assertLingotekDownloadTargetLink('es_MX');
    $this->assertLingotekDownloadTargetLink('it_IT');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // They are marked with the right status.
    $this->assertTargetStatus('ES', 'current');
    $this->assertTargetStatus('IT', 'current');
    $this->assertTargetStatus('DE', 'request');

    // We check all translations.
    \Drupal::state()
      ->set('lingotek.document_completion_statuses', [
        'es-ES' => 100,
        'it-IT' => 100,
      ]);
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // And statuses should remain the same.
    $this->assertTargetStatus('ES', 'current');
    $this->assertTargetStatus('IT', 'current');
    $this->assertTargetStatus('DE', 'request');
  }

  /**
   * Tests that we update the statuses when a translation is deleted.
   */
  public function testDeleteTranslationUpdatesStatuses() {
    $this->testNodeTranslationUsingActionsForMultipleLocales();

    $this->goToContentBulkManagementForm();
    $this->assertTargetStatus('DE', Lingotek::STATUS_CURRENT);

    $this->drupalGet('node/1/translations');
    $this->clickLink('Delete');
    $this->drupalPostForm(NULL, [], t('Delete @language translation', ['@language' => ConfigurableLanguage::load('de')->getName()]));

    $this->goToContentBulkManagementForm();
    $this->assertTargetStatus('DE', Lingotek::STATUS_READY);
  }

  /**
   * Tests that translations can be deleted using the actions on the management page.
   */
  public function testDeleteTranslation() {
    $this->testNodeTranslationUsingActionsForMultipleLocales();

    $this->goToContentBulkManagementForm();

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDeleteTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->confirmBulkDeleteTranslation(1, 1);

    $this->assertLingotekDownloadTargetLink('es_MX');
    $this->assertNoLingotekDownloadTargetLink('de_AT');
  }

  /**
   * Tests that translations can be deleted using the actions on the management page.
   */
  public function testDeleteMissingTranslation() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink();

    $this->goToContentBulkManagementForm();

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDeleteTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('No valid translations for deletion.');
    // Assert we kept selection.
    $this->assertSelectionIsKept($key);

    $this->goToContentBulkManagementForm();
    $this->assertNoLingotekDownloadTargetLink('de_AT');
  }

  /**
   * Tests that translations can be deleted using the actions on the management page.
   */
  public function testDeleteTranslationsInBulk() {
    $this->testNodeTranslationUsingActionsForMultipleLocales();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('ca')
      ->setThirdPartySetting('lingotek', 'locale', 'ca_ES')
      ->save();

    $this->goToContentBulkManagementForm();

    $this->assertLingotekRequestTranslationLink('ca_ES');

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDeleteTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->confirmBulkDeleteTranslations(1, 2);

    $this->assertLingotekDownloadTargetLink('es_MX');
    $this->assertLingotekDownloadTargetLink('de_AT');
  }

  protected function confirmBulkDeleteTranslation($nodeCount, $translationCount) {
    $this->drupalPostForm(NULL, [], t('Delete'));
    $this->assertText("Deleted $translationCount content item.");
  }

  protected function confirmBulkDeleteTranslations($nodeCount, $translationCount) {
    $this->drupalPostForm(NULL, [], t('Delete'));
    $this->assertText("Deleted $translationCount content items.");
  }

  /**
   * Assert the selected action and key are kept.
   *
   * @param string $key
   *   The selection key.
   */
  protected function assertSelectionIsKept(string $key) {
    $assert_session = $this->assertSession();
    $assert_session->optionExists($this->getBulkOperationFormName(), $this->getBulkOperationNameForDeleteTranslation('es', 'node'));
    $this->assertFieldChecked($key);
  }

}
