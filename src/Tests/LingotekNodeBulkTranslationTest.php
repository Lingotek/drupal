<?php

namespace Drupal\lingotek\Tests;

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

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

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
  public function testNodeTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/es_MX');
    $workbench_link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/dummy-document-hash-id/es_MX' and @target='_blank']");
    $this->assertEqual(count($workbench_link), 1, 'Workbench links open in a new tab.');
  }

  /**
   * Tests that a node can be translated using the actions on the management page.
   */
  public function testNodeTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'request_translation:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_translation:de'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
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
   * Tests that a node can be translated using the actions on the management page for multiple locales.
   */
  public function testNodeTranslationUsingActionsForMultipleLocales() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add two languages.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Request all translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'request_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check all statuses.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Download all translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'download'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/es_MX');
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/de_AT');
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

    $basepath = \Drupal::request()->getBasePath();

    $this->goToContentBulkManagementForm();

    // Let's upload the edited content so it's updated and downloadable.
    $this->clickLink('EN');
    // Check the source status is current.
    $this->clickLink('EN');

    // Check all statuses, after being edited and the source re-uploaded
    // Should be in STATUS_PENDING
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Download all translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'download'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/es_MX');
    $this->assertLinkByHref($basepath . '/admin/lingotek/workbench/dummy-document-hash-id/de_AT');
  }

  public function testNodeTranslationUsingActionsForMultipleLocalesAfterEditingWithPendingPhases() {
    $this->testNodeTranslationUsingActionsForMultipleLocales();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1);

    $basepath = \Drupal::request()->getBasePath();

    $this->goToContentBulkManagementForm();

    // Let's upload the edited content so it's updated and downloadable.
    $this->clickLink('EN');
    // Check the source status is current.
    $this->clickLink('EN');

    // Ensure we won't get a completed document because there are phases pending.
    \Drupal::state()->set('lingotek.document_completion', FALSE);

    // Check all statuses, after being edited and the source re-uploaded
    // Should be in STATUS_PENDING
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Ensure that the statuses are set to PENDING since the source has been
    // reuploaded and the targets are being translated. It is possible that
    // some of the translation is finished and could be downloaded, but that
    // should be marked as STATUS_READY_INTERIM but that has not been
    // implemented yet.
    // TODO: update test to check that status is STATUS_READY_INTERIM and then
    // they can be downloaded and then the status is set to STATUS_INTERMEDIATE
    // see ticket: https://www.drupal.org/node/2850548
    // Check the status is PENDING for Spanish and German.
    $de_edited = $this->xpath("//a[contains(@class,'language-icon') and contains(@class,'target-pending')  and contains(text(), 'DE')]");
    $this->assertEqual(count($de_edited), 1, 'German is marked as pending.');
    $es_edited = $this->xpath("//a[contains(@class,'language-icon') and contains(@class,'target-pending')  and contains(text(), 'ES')]");
    $this->assertEqual(count($es_edited), 1, 'Spanish is marked as pending.');
  }

  /**
   * Tests that a node can be translated using the actions on the management page.
   */
  public function testNodeMultipleLanguageTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Create another node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool 2';
    $edit['body[0][value]'] = 'Llamas are very cool 2';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/2?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Request all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'operation' => 'request_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Check status of all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Download all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'operation' => 'download'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
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
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Add a language so we can check that it's not marked as for requesting if
    // it was already requested.
    ConfigurableLanguage::createFromLangcode('ko')->setThirdPartySetting('lingotek', 'locale', 'ko_KR')->save();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $edit['body[0][value]'] = 'Llamas are very cool EDITED';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->goToContentBulkManagementForm();

    // Check the status is edited for Spanish.
    $untracked = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'ES')]");
    $this->assertEqual(count($untracked), 1, 'Edited translation is shown.');

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $eu_edited = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'EU')]");
    $this->assertEqual(count($eu_edited), 0, 'Vasque is not marked as edited.');
    $eu_request = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-request')  and contains(text(), 'EU')]");
    $this->assertEqual(count($eu_request), 1, 'Vasque is ready for request.');

    // Request korean, with outdated content available.
    $this->clickLink('KO');
    $this->assertText("Locale 'ko_KR' was added as a translation target for node Llamas are cool EDITED.");

    // Reupload the content.
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool EDITED has been updated.');

    // Korean should be marked as requested, so we can check target.
    $status = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-pending')  and contains(text(), 'KO')]");
    $this->assertEqual(count($status), 1, 'Korean is requested, so we can still check the progress status of the translation');

    // Recheck status.
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool EDITED is complete.');

    // Korean should still be marked as requested, so we can check target.
    $status = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-pending')  and contains(text(), 'KO')]");
    $this->assertEqual(count($status), 1, 'Korean is still requested, so we can still check the progress status of the translation');

    // Check the translation after having been edited.
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertText('Operations completed.');

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
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $edit['body[0][value]'] = 'Llamas are very cool EDITED';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    $this->goToContentBulkManagementForm();

    // Check the status is edited for Spanish.
    $untracked = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'ES')]");
    $this->assertEqual(count($untracked), 1, 'Edited translation is shown.');

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $eu_edited = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-edited')  and contains(text(), 'EU')]");
    $this->assertEqual(count($eu_edited), 0, 'Vasque is not marked as edited.');
    $eu_request = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-request')  and contains(text(), 'EU')]");
    $this->assertEqual(count($eu_request), 1, 'Vasque is ready for request.');

    // Recheck status.
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool EDITED is complete.');

    // Check the translation after having been edited.
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertText('Operations completed.');

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

    $basepath = \Drupal::request()->getBasePath();

    // There is a link for requesting the Catalan translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/ca_ES?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->clickLink('CA');
    $this->assertText("Locale 'ca_ES' was added as a translation target for node Llamas are cool.");
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testFormWorksAfterRemovingLanguageWithStatuses() {
    // We need a language added and requested.
    $this->testAddingLanguageAllowsRequesting();

    // Delete a language.
    ConfigurableLanguage::load('es')->delete();

    $this->goToContentBulkManagementForm();

    // There is no link for the Spanish translation.
    $this->assertNoLink('ES');
    $this->assertLink('CA');
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
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking Spanish must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/en_US?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->clickLink('ES');
    $this->assertText('Node Llamas are cool es-MX has been uploaded.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.uploaded_locale'));
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
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must fail.
    $this->clickLink('EN');
    $this->assertText('The upload for node Llamas are cool failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The node has been marked as error.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
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
    $edit['lingotek_translation_profile'] = 'manual';
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
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The node has been marked as error.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
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
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must fail.
    $basepath = \Drupal::request()->getBasePath();
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertText('The upload for node Llamas are cool failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The node has been marked as error.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
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
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Upload the document, which must succeed.
    $basepath = \Drupal::request()->getBasePath();
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertText('Operations completed.');

    // Check upload.
    $this->clickLink('EN');

    // Edit the node.
    $edit = ['title[0][value]' => 'Llamas are cool EDITED'];
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    $this->goToContentBulkManagementForm();
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    $this->assertText('The update for node Llamas are cool EDITED failed. Please try again.');

    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The node has been marked as error.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool EDITED has been updated.');
  }

  /**
   * Tests that all the statuses are set when using the Check Translations action.
   */
  public function testCheckTranslationsAction() {
    // Add a couple of languages.
    ConfigurableLanguage::create(['id' => 'de_AT', 'label' => 'German (Austria)'])->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();
    ConfigurableLanguage::createFromLangcode('ca')->setThirdPartySetting('lingotek', 'locale', 'ca_ES')->save();
    ConfigurableLanguage::createFromLangcode('it')->setThirdPartySetting('lingotek', 'locale', 'it_IT')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Assert that I could request translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');

    // Check statuses, that may been requested externally.
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_translations'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Now Drupal knows that there are translations ready.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');

    // Even if I just add a new language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_DE?destination=' . $basepath . '/admin/lingotek/manage/node');

    // Ensure locales are handled correctly by setting manual values.
    \Drupal::state()->set('lingotek.document_completion_statuses', ['de-AT' => 50, 'de-DE' => 100, 'es-MX' => 10]);
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Now Drupal knows which translations are ready.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/de_DE?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/ca_ES?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/it_IT?destination=' . $basepath . '/admin/lingotek/manage/node');

    \Drupal::state()->set('lingotek.document_completion_statuses', ['it-IT' => 100, 'de-DE' => 50, 'es-MX' => 10]);
    // Check all statuses again.
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // All translations must be updated according exclusively with the
    // information from the TMS.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/de_AT?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/de_DE?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/ca_ES?destination=' . $basepath . '/admin/lingotek/manage/node');
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/download/dummy-document-hash-id/it_IT?destination=' . $basepath . '/admin/lingotek/manage/node');

    // Source status must be kept too.
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
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');

    // The document has not been imported yet.
    \Drupal::state()->set('lingotek.document_status_completion', FALSE);
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // I can check current status, because it wasn't imported but it's not marked
    // as an error.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');

    // Check again, it succeeds.
    \Drupal::state()->set('lingotek.document_status_completion', TRUE);
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // Assert that targets can be requested.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=' . $basepath . '/admin/lingotek/manage/node');
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
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/admin/lingotek/manage/node');
    $edit = [
      'table[1]' => TRUE,
      'operation' => 'upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');

    // The document has not been imported yet, and it was uploaded long ago.
    \Drupal::state()->set('lingotek.document_status_completion', FALSE);
    Node::load(1)->setChangedTime(REQUEST_TIME - 100000)->save();

    $edit = [
      'table[1]' => TRUE,
      'operation' => 'check_upload'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    // It was marked as error and I can try the update.
    // Check the right class is added.
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-error')  and ./a[contains(text(), 'EN')]]");
    $this->assertEqual(count($source_error), 1, 'The node has been marked as error.');

    $this->assertLinkByHref($basepath . '/admin/lingotek/entity/update/dummy-document-hash-id?destination=' . $basepath . '/admin/lingotek/manage/node');
  }

}
