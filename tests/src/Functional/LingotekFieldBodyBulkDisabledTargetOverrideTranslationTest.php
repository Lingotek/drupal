<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating a field using the bulk management form.
 *
 * @group lingotek
 */
class LingotekFieldBodyBulkDisabledTargetOverrideTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    node_add_body_field($type);

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

    $this->saveLingotekConfigTranslationSettings([
      'node_fields' => 'profile_with_disabled_targets',
    ]);
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'body');
  }

  /**
   * Tests that a field can be translated using the links on the management page.
   */
  public function testFieldBodyTranslationUsingLinks() {
    $assert_session = $this->assertSession();

    $basepath = \Drupal::request()->getBasePath();
    $this->goToConfigBulkManagementForm('node_fields');

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/field_config/node.article.body?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');
    $this->assertText('Body uploaded successfully');
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/field_config/node.article.body?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->clickLink('EN');
    $this->assertText('Body status checked successfully');

    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);
  }

  /**
   * Tests that a field can be translated using the actions on the management page.
   */
  public function testFieldBodyTranslationUsingActions() {
    $assert_session = $this->assertSession();
    $basepath = \Drupal::request()->getBasePath();

    $this->goToConfigBulkManagementForm('node_fields');

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/field_config/node.article.body?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    // I can init the upload of content.
    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    $this->assertText('Operations completed.');

    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Request the disabled target translation.
    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('ca', 'node_fields'),
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
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/check_download/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => 'check_translation:ca',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical(NULL, \Drupal::state()
      ->get('lingotek.checked_target_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Download the Catalan translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/download/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => 'download:ca',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical(NULL, \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);
  }

  /**
   * Tests that a field can be translated using the actions on the management page for multiple locales.
   */
  public function testFieldBodyTranslationUsingActionsForMultipleLocales() {
    $assert_session = $this->assertSession();
    $basepath = \Drupal::request()->getBasePath();

    $this->goToConfigBulkManagementForm('node_fields');

    // Clicking English must init the upload of content.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/field_config/node.article.body?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node_fields'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    $this->assertText('Operations completed.');

    // There is a link for checking status.
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/check_upload/field_config/node.article.body?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node_fields'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

    $this->assertText('Operations completed.');

    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Request the disabled target translation.
    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node_fields'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical(['dummy-document-hash-id' => ['es_MX']], \Drupal::state()
      ->get('lingotek.requested_locales'));

    $this->assertText('Operations completed.');

    $this->assertTargetStatus('es', Lingotek::STATUS_PENDING);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Check status of the disabled target translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/check_download/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node_fields'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical(NULL, \Drupal::state()
      ->get('lingotek.checked_target_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_READY);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);

    // Download the Catalan translation.
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/download/field_config/node.article.body/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[node.article.body]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node_fields'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    $this->assertTargetStatus('es', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ca', Lingotek::STATUS_DISABLED);
  }

}
