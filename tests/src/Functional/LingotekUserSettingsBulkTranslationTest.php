<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating user settings using the bulk management form.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekUserSettingsBulkTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'user.settings');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testUserSettingsTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // Clicking English must init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/entity.user.admin_form/entity.user.admin_form?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we cannot request yet a translation.
    $this->assertNoLinkByHref($basepath . '/admin/lingotek/config/request/entity.user.admin_form/entity.user.admin_form/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 3);
    $this->assertText(t('Account settings uploaded successfully'));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->verbose(var_export(\Drupal::state()->get('lingotek.uploaded_content'), TRUE));

    // There is a link for checking status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/entity.user.admin_form/entity.user.admin_form?destination=' . $basepath . '/admin/lingotek/config/manage');
    // And we can already request a translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/entity.user.admin_form/entity.user.admin_form/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('EN', 3);
    $this->assertText('Account settings status checked successfully');

    // Request the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/entity.user.admin_form/entity.user.admin_form/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText("Translation to es_MX requested successfully");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/entity.user.admin_form/entity.user.admin_form/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText("Translation to es_MX checked successfully");

    // Download the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/entity.user.admin_form/entity.user.admin_form/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX');
  }

  /**
   * Tests that a config can be translated using the actions on the management page.
   */
  public function testUserSettingsTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/entity.user.admin_form/entity.user.admin_form?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/entity.user.admin_form/entity.user.admin_form?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the German (AT) translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/entity.user.admin_form/entity.user.admin_form/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/entity.user.admin_form/entity.user.admin_form/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => 'check_translation:de',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the German translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/entity.user.admin_form/entity.user.admin_form/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => 'download:de',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('de_AT');
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testEditedConfigTranslationUsingLinks() {
    // We need a config object with translations first.
    $this->testUserSettingsTranslationUsingLinks();

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Add a language so we can check that it's not marked as for requesting if
    // it was already requested.
    ConfigurableLanguage::createFromLangcode('ko')->setThirdPartySetting('lingotek', 'locale', 'ko_KR')->save();

    // Edit the object
    $this->drupalPostForm('/admin/config/people/accounts', ['anonymous' => 'Unknown user'], t('Save configuration'));

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // Check the source status is edited.
    $this->assertSourceStatus('EN', Lingotek::STATUS_EDITED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_PENDING);
    // Check the status is not edited for Vasque, but available to request
    // translation.
    $this->assertNoTargetStatus('EU', Lingotek::STATUS_EDITED);
    $this->assertTargetStatus('EU', Lingotek::STATUS_REQUEST);

    // Request korean, with outdated content available.
    $this->clickLink('KO');
    $this->assertText("Translation to ko_KR requested successfully");

    // Reupload the content.
    $this->clickLink('EN', 3);
    $this->assertText('Account settings has been updated.');

    // Korean should be marked as requested, so we can check target.
    $status = $this->xpath("//a[contains(@class,'language-icon') and contains(@class, 'target-pending')  and contains(text(), 'KO')]");
    $this->assertEqual(count($status), 1, 'Korean is requested, so we can still check the progress status of the translation');

    // Recheck status.
    $this->clickLink('EN', 3);
    $this->assertText('Account settings status checked successfully');

    // Check the translation after having been edited.
    // Check status of the Spanish translation.
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => 'check_translation:es',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertText('Operations completed.');

    // Download the translation.
    $this->clickLink('ES');
    $this->assertText('Translation to es_MX downloaded successfully');
  }

  /**
   * Tests that a config object can be translated using the actions on the management page.
   */
  public function testUserSettingsMultipleLanguageTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // I can init the upload of content.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/upload/entity.user.admin_form/entity.user.admin_form?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // I can check current status.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_upload/entity.user.admin_form/entity.user.admin_form?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/entity.user.admin_form/entity.user.admin_form/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check status of all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/check_download/entity.user.admin_form/entity.user.admin_form/es_MX?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Download all the translations.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/download/entity.user.admin_form/entity.user.admin_form/de_AT?destination=' . $basepath . '/admin/lingotek/config/manage');
    $edit = [
      'table[entity.user.admin_form]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
  }

  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testAddingLanguageAllowsRequesting() {
    // We need a node with translations first.
    $this->testUserSettingsTranslationUsingLinks();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('ca')->save();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    $basepath = \Drupal::request()->getBasePath();

    // There is a link for requesting the Catalan translation.
    $this->assertLinkByHref($basepath . '/admin/lingotek/config/request/entity.user.admin_form/entity.user.admin_form/ca_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
    $this->clickLink('CA');
    $this->assertText("Translation to ca_ES requested successfully");
  }

  /**
   * Test that when a config is uploaded in a different locale that locale is used.
   * ToDo: Add a test for this.
   */
  public function testAddingConfigInDifferentLocale() {
    $this->pass('Test not implemented yet.');
  }

}
