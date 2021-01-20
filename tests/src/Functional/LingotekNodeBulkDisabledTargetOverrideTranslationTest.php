<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating a node using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkDisabledTargetOverrideTranslationTest extends LingotekTestBase {

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
    ConfigurableLanguage::createFromLangcode('ca')
      ->setThirdPartySetting('lingotek', 'locale', 'ca_ES')
      ->save();

    $profile = LingotekProfile::create([
      'label' => 'Profile with disabled targets',
      'id' => 'profile_with_disabled_targets',
      'project' => 'test_project',
      'vault' => 'test_vault',
      'auto_upload' => FALSE,
      'workflow' => 'test_workflow',
      'language_overrides' => [
        'es' => ['overrides' => 'custom', 'custom' => ['auto_request' => TRUE, 'workflow' => 'test_workflow', 'vault' => 'test_vault']],
        'ca' => ['overrides' => 'disabled'],
      ],
    ]);
    $profile->save();

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

    $this->saveLingotekContentTranslationSettingsForNodeTypes(['article'], 'profile_with_disabled_targets');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testNodeTranslationUsingLinks() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'profile_with_disabled_targets';
    $this->saveAndPublishNodeForm($edit);

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
    $this->assertNoLingotekRequestTranslationLink('ca_ES');
    $this->assertLingotekRequestTranslationLink('es_MX');

    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    $this->assertNoLingotekRequestTranslationLink('ca_ES');
    $this->assertLingotekRequestTranslationLink('es_MX');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);
  }

  /**
   * Tests that a node can be translated using the actions on the management page.
   */
  public function testNodeTranslationUsingActions() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'profile_with_disabled_targets';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('ca_ES');
    $this->assertNoLingotekRequestTranslationLink('es_MX');

    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    $this->assertText('Operations completed.');

    $this->assertNoLingotekRequestTranslationLink('ca_ES');
    $this->assertLingotekRequestTranslationLink('es_MX');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Request the disabled target translation.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('ca', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical(NULL, \Drupal::state()
      ->get('lingotek.added_target_locale'));
    $this->assertIdentical(NULL, \Drupal::state()
      ->get('lingotek.requested_locales'));

    $this->assertText('Operations completed.');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Check status of the disabled target translation.
    $this->assertNoLingotekCheckTargetStatusLink('ca_ES');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslation('ca', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical(NULL, \Drupal::state()
      ->get('lingotek.checked_target_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Download the Catalan translation.
    $this->assertNoLingotekDownloadTargetLink('ca_ES');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslation('ca', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical(NULL, \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);
  }

  /**
   * Tests that a node can be translated using the actions on the management page for multiple locales.
   */
  public function testNodeTranslationUsingActionsForMultipleLocales() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'profile_with_disabled_targets';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('ca_ES');
    $this->assertNoLingotekRequestTranslationLink('es_MX');

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    $this->assertText('Operations completed.');

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    $this->assertText('Operations completed.');

    $this->assertNoLingotekRequestTranslationLink('ca_ES');
    $this->assertLingotekRequestTranslationLink('es_MX');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Request the disabled target translation.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical(['dummy-document-hash-id' => ['es_MX']], \Drupal::state()
      ->get('lingotek.requested_locales'));

    $this->assertText('Operations completed.');

    $this->assertTargetStatus('es', Lingotek::STATUS_PENDING);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Check status of the disabled target translation.
    $this->assertNoLingotekCheckTargetStatusLink('ca_ES');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical(NULL, \Drupal::state()
      ->get('lingotek.checked_target_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_READY);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Download the Catalan translation.
    $this->assertNoLingotekDownloadTargetLink('ca_ES');
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);
  }

}
