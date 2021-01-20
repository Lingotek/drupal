<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek profile form.
 *
 * @group lingotek
 */
class LingotekProfileFormTest extends LingotekTestBase {

  use IntelligenceMetadataFormTestTrait;

  /**
   * {@inheritdoc}
   *
   * Use 'classy' here, as we depend on that for querying the selects in the
   * target overriddes class.
   *
   * @see testProfileSettingsOverride()
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setupResources();
  }

  /**
   * Test that default profiles are present.
   */
  public function testDefaultProfilesPresent() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');

    // Status of the checkbox matrix is as expected.
    $this->assertFieldChecked('edit-profile-automatic-auto-upload');
    $this->assertFieldChecked('edit-profile-automatic-auto-download');
    $this->assertNoFieldChecked('edit-profile-manual-auto-upload');
    $this->assertNoFieldChecked('edit-profile-manual-auto-download');
    $this->assertNoFieldChecked('edit-profile-disabled-auto-upload');
    $this->assertNoFieldChecked('edit-profile-disabled-auto-download');

    // We cannot edit them.
    $assert_session->linkByHrefNotExists('/admin/lingotek/settings/profile/automatic/edit');
    $assert_session->linkByHrefNotExists('/admin/lingotek/settings/profile/manual/edit');
    $assert_session->linkByHrefNotExists('/admin/lingotek/settings/profile/disabled/edit');

    // The fields are disabled.
    $this->assertFieldDisabled('edit-profile-automatic-auto-upload');
    $this->assertFieldDisabled('edit-profile-automatic-auto-download');
    $this->assertFieldDisabled('edit-profile-manual-auto-upload');
    $this->assertFieldDisabled('edit-profile-manual-auto-download');
    $this->assertFieldDisabled('edit-profile-disabled-auto-upload');
    $this->assertFieldDisabled('edit-profile-disabled-auto-download');
  }

  /**
   * Test adding a profile are present.
   */
  public function testAddingProfile() {
    $assert_session = $this->assertSession();
    $this->drupalGet('admin/lingotek/settings');
    $this->clickLink(t('Add new Translation Profile'));

    $profile_id = strtolower($this->randomMachineName());
    $profile_name = $this->randomString();
    $edit = [
      'id' => $profile_id,
      'label' => $profile_name,
      'auto_upload' => 1,
      'auto_request' => 1,
      'auto_download' => 1,
      'append_type_to_title' => 'yes',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The Lingotek profile has been successfully saved.'));

    // We can edit them.
    $assert_session->linkByHrefExists("/admin/lingotek/settings/profile/$profile_id/edit");

    $this->assertFieldChecked("edit-profile-$profile_id-auto-upload");
    $this->assertFieldChecked("edit-profile-$profile_id-auto-request");
    $this->assertFieldChecked("edit-profile-$profile_id-auto-download");
    $this->assertFieldEnabled("edit-profile-$profile_id-auto-upload");
    $this->assertFieldEnabled("edit-profile-$profile_id-auto-request");
    $this->assertFieldEnabled("edit-profile-$profile_id-auto-download");

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertTrue($profile->hasAutomaticUpload());
    $this->assertTrue($profile->hasAutomaticRequest());
    $this->assertTrue($profile->hasAutomaticDownload());
    $this->assertIdentical('yes', $profile->getAppendContentTypeToTitle());
    $this->assertIdentical('default', $profile->getProject());
    $this->assertIdentical('default', $profile->getVault());
    $this->assertIdentical('default', $profile->getWorkflow());
    $this->assertFalse($profile->hasIntelligenceMetadataOverrides());
    $this->assertIdentical('drupal_default', $profile->getFilter());
    $this->assertIdentical('drupal_default', $profile->getSubfilter());
  }

  /**
   * Tests that both project default and default workflows are shown in the profile form.
   */
  public function testWorkflows() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $this->clickLink(t('Add new Translation Profile'));

    $this->assertFieldByName('workflow');
    $assert_session->optionExists('edit-workflow', 'project_default');
    $assert_session->optionExists('edit-workflow', 'default');
  }

  /**
   * Test editing profiles.
   */
  public function testEditingProfile() {
    $assert_session = $this->assertSession();

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
    ]);
    $profile->save();
    $profile_id = $profile->id();

    $this->assertIdentical('global_setting', $profile->getAppendContentTypeToTitle());
    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");

    $edit = [
      'auto_upload' => FALSE,
      'auto_download' => 1,
      'project' => 'test_project',
      'vault' => 'test_vault',
      'workflow' => 'test_workflow',
      'filter' => 'test_filter',
      'subfilter' => 'test_filter2',
      'append_type_to_title' => 'no',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertFalse($profile->hasAutomaticUpload());
    $this->assertTrue($profile->hasAutomaticDownload());
    $this->assertIdentical('no', $profile->getAppendContentTypeToTitle());
    $this->assertIdentical('test_project', $profile->getProject());
    $this->assertIdentical('test_vault', $profile->getVault());
    $this->assertIdentical('test_workflow', $profile->getWorkflow());
    $this->assertIdentical('test_filter', $profile->getFilter());
    $this->assertIdentical('test_filter2', $profile->getSubfilter());

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");
    $this->assertNoFieldChecked("edit-auto-upload");
    $this->assertFieldChecked("edit-auto-download");
    $assert_session->optionExists('edit-append-type-to-title', 'no');
    $assert_session->optionExists('edit-project', 'test_project');
    $assert_session->optionExists('edit-vault', 'test_vault');
    $assert_session->optionExists('edit-workflow', 'test_workflow');
    $assert_session->optionExists('edit-filter', 'test_filter');
    $assert_session->optionExists('edit-subfilter', 'test_filter2');
  }

  /**
   * Test editing profiles auto properties in the listing.
   */
  public function testEditingProfileInListing() {
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
    ]);
    $profile->save();
    $profile_id = $profile->id();
    $this->drupalGet("/admin/lingotek/settings");

    $edit = [
      "profile[$profile_id][auto_upload]" => 1,
      "profile[$profile_id][auto_request]" => 0,
      "profile[$profile_id][auto_download]" => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration', [], 'lingotek-profile-admin-overview-form');

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertTrue($profile->hasAutomaticUpload());
    $this->assertFalse($profile->hasAutomaticRequest());
    $this->assertTrue($profile->hasAutomaticDownload());

    $this->assertFieldChecked("profile[$profile_id][auto_upload]");
    $this->assertNoFieldChecked("profile[$profile_id][auto_request]");
    $this->assertFieldChecked("profile[$profile_id][auto_download]");

    $edit = [
      "profile[$profile_id][auto_upload]" => 1,
      "profile[$profile_id][auto_request]" => 1,
      "profile[$profile_id][auto_download]" => 0,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration', [], 'lingotek-profile-admin-overview-form');

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertTrue($profile->hasAutomaticUpload());
    $this->assertTrue($profile->hasAutomaticRequest());
    $this->assertFalse($profile->hasAutomaticDownload());

    $this->assertFieldChecked("profile[$profile_id][auto_upload]");
    $this->assertFieldChecked("profile[$profile_id][auto_request]");
    $this->assertNoFieldChecked("profile[$profile_id][auto_download]");

    $edit = [
      "profile[$profile_id][auto_upload]" => 0,
      "profile[$profile_id][auto_request]" => 1,
      "profile[$profile_id][auto_download]" => 0,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration', [], 'lingotek-profile-admin-overview-form');

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertFalse($profile->hasAutomaticUpload());
    $this->assertTrue($profile->hasAutomaticRequest());
    $this->assertFalse($profile->hasAutomaticDownload());

    $this->assertNoFieldChecked("profile[$profile_id][auto_upload]");
    $this->assertFieldChecked("profile[$profile_id][auto_request]");
    $this->assertNoFieldChecked("profile[$profile_id][auto_download]");
  }

  /**
   * Test deleting profile.
   */
  public function testDeletingProfile() {
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
    ]);
    $profile->save();
    $profile_id = $profile->id();

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/delete");

    // Confirm the form.
    $this->assertText('This action cannot be undone.');
    $this->drupalPostForm(NULL, [], t('Delete'));

    // Profile was deleted.
    $this->assertRaw(t('The lingotek profile %profile has been deleted.', ['%profile' => $profile->label()]));

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertNull($profile);
  }

  /**
   * Test deleting profile being used in content is not deleted.
   */
  public function testDeletingProfileBeingUsedInContent() {
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
    ]);
    $profile->save();
    $profile_id = $profile->id();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    $this->saveLingotekContentTranslationSettingsForNodeTypes();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = $profile_id;
    $this->saveAndPublishNodeForm($edit);
    $this->assertUrl('/node/1', [], 'Node has been created.');

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/delete");

    // Confirm the form.
    $this->assertText(t('This action cannot be undone.'));
    $this->drupalPostForm(NULL, [], t('Delete'));

    $this->assertNoRaw(t('The lingotek profile %profile has been deleted.', ['%profile' => $profile->label()]));
    $this->assertRaw(t('The Lingotek profile %profile is being used so cannot be deleted.', ['%profile' => $profile->label()]));

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertNotNull($profile);
  }

  /**
   * Test deleting profile being used in content is not deleted.
   */
  public function testDeletingProfileBeingUsedInConfigSettings() {
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
    ]);
    $profile->save();
    $profile_id = $profile->id();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');
    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:' . $profile_id,
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/delete");

    // Confirm the form.
    $this->assertText(t('This action cannot be undone.'));
    $this->drupalPostForm(NULL, [], t('Delete'));

    $this->assertNoRaw(t('The lingotek profile %profile has been deleted.', ['%profile' => $profile->label()]));
    $this->assertRaw(t('The Lingotek profile %profile is being used so cannot be deleted.', ['%profile' => $profile->label()]));

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertNotNull($profile);
  }

  /**
   * Test deleting profile being configured for usage in content is not deleted.
   */
  public function testDeletingProfileBeingUsedInContentSettings() {
    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
    ]);
    $profile->save();
    $profile_id = $profile->id();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    $this->saveLingotekContentTranslationSettingsForNodeTypes(['article'], $profile_id);

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/delete");

    // Confirm the form.
    $this->assertText(t('This action cannot be undone.'));
    $this->drupalPostForm(NULL, [], t('Delete'));

    $this->assertNoRaw(t('The lingotek profile %profile has been deleted.', ['%profile' => $profile->label()]));
    $this->assertRaw(t('The Lingotek profile %profile is being used so cannot be deleted.', ['%profile' => $profile->label()]));

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertNotNull($profile);
  }

  /**
   * Test profiles language settings override.
   */
  public function testProfileSettingsOverride() {
    $assert_session = $this->assertSession();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
    ]);
    $profile->save();
    $profile_id = $profile->id();

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");
    $assert_session->optionExists('edit-language-overrides-es-overrides', 'default');
    $assert_session->optionExists('edit-language-overrides-en-overrides', 'default');

    $edit = [
      'auto_upload' => FALSE,
      'auto_download' => 1,
      'project' => 'default',
      'vault' => 'default',
      'workflow' => 'test_workflow2',
      'language_overrides[es][overrides]' => 'custom',
      'language_overrides[es][custom][auto_download]' => FALSE,
      'language_overrides[es][custom][workflow]' => 'test_workflow',
      'language_overrides[es][custom][vault]' => 'test_vault',
      'language_overrides[de][overrides]' => 'custom',
      'language_overrides[de][custom][auto_download]' => FALSE,
      'language_overrides[de][custom][workflow]' => 'default',
      'language_overrides[de][custom][vault]' => 'default',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertFalse($profile->hasAutomaticUpload());
    $this->assertTrue($profile->hasAutomaticDownload());
    $this->assertIdentical('default', $profile->getProject());
    $this->assertIdentical('default', $profile->getVault());
    $this->assertIdentical('test_workflow2', $profile->getWorkflow());
    $this->assertIdentical('test_workflow', $profile->getWorkflowForTarget('es'));
    $this->assertIdentical('default', $profile->getWorkflowForTarget('de'));
    $this->assertIdentical('test_vault', $profile->getVaultForTarget('es'));
    $this->assertIdentical('default', $profile->getVaultForTarget('de'));
    $this->assertFalse($profile->hasAutomaticDownloadForTarget('es'));
    $this->assertFalse($profile->hasAutomaticDownloadForTarget('de'));

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");
    $this->assertNoFieldChecked("edit-auto-upload");
    $this->assertFieldChecked("edit-auto-download");
    $assert_session->optionExists('edit-project', 'default');
    $assert_session->optionExists('edit-vault', 'default');
    $assert_session->optionExists('edit-workflow', 'test_workflow2');
    $assert_session->optionExists('edit-language-overrides-es-overrides', 'custom');
    $assert_session->optionExists('edit-language-overrides-de-overrides', 'custom');
    $assert_session->optionExists('edit-language-overrides-en-overrides', 'default');
    $this->assertNoFieldChecked('edit-language-overrides-es-custom-auto-download');
    $this->assertNoFieldChecked('edit-language-overrides-de-custom-auto-download');
    $this->assertFieldChecked('edit-language-overrides-en-custom-auto-download');
    $assert_session->optionExists('edit-language-overrides-es-custom-workflow', 'test_workflow');
    $assert_session->optionExists('edit-language-overrides-de-custom-workflow', 'default');
    $assert_session->optionExists('edit-language-overrides-en-custom-workflow', 'default');

    // Assert that the override languages are present and ordered alphabetically.
    $selects = $this->xpath('//details[@id="edit-language-overrides"]/*/*//select');
    // There must be 2 select options for each of the 3 languages.
    $this->assertEqual(count($selects), 3 * 3, 'There are options for all the potential language overrides.');
    // And the first one must be German alphabetically.
    $this->assertEqual($selects[0]->getAttribute('id'), 'edit-language-overrides-de-overrides', 'Languages are ordered alphabetically.');
  }

  /**
   * Tests that a profile target can be set as disabled.
   */
  public function testProfileTargetOverrideAsDisabled() {
    // Add a language.
    $es = ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX');
    $de = ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE');
    $es->save();
    $de->save();

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
    ]);
    $profile->save();
    $profile_id = $profile->id();

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");

    $edit = [
      'auto_upload' => FALSE,
      'auto_download' => 1,
      'project' => 'default',
      'vault' => 'default',
      'workflow' => 'test_workflow2',
      'language_overrides[es][overrides]' => 'disabled',
      'language_overrides[de][overrides]' => 'custom',
      'language_overrides[de][custom][auto_request]' => TRUE,
      'language_overrides[de][custom][auto_download]' => TRUE,
      'language_overrides[de][custom][workflow]' => 'default',
      'language_overrides[de][custom][vault]' => 'default',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertFalse($profile->hasAutomaticUpload());
    $this->assertTrue($profile->hasAutomaticDownload());
    $this->assertIdentical('default', $profile->getProject());
    $this->assertIdentical('default', $profile->getVault());
    $this->assertIdentical('test_workflow2', $profile->getWorkflow());
    $this->assertIdentical(NULL, $profile->getWorkflowForTarget('es'));
    $this->assertIdentical(NULL, $profile->getVaultForTarget('es'));
    $this->assertTrue($profile->hasDisabledTarget('es'));
    $this->assertFalse($profile->hasAutomaticRequestForTarget('es'));
    $this->assertTrue($profile->hasAutomaticRequestForTarget('de'));
    $this->assertFalse($profile->hasAutomaticDownloadForTarget('es'));
    $this->assertTrue($profile->hasAutomaticDownloadForTarget('de'));
  }

  /**
   * Tests that disabled languages are not shown in the profile form for
   * defining overrides.
   */
  public function testLanguageDisabled() {
    $assert_session = $this->assertSession();

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $configLingotek */
    $configLingotek = \Drupal::service('lingotek.configuration');

    // Add a language.
    $es = ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX');
    $de = ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE');
    $es->save();
    $de->save();

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::create([
      'id' => strtolower($this->randomMachineName()),
      'label' => $this->randomString(),
    ]);
    $profile->save();
    $profile_id = $profile->id();

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");
    $this->assertFieldByName('language_overrides[es][overrides]');
    $assert_session->optionExists('edit-language-overrides-de-overrides', 'default');
    $assert_session->optionExists('edit-language-overrides-de-overrides', 'default');

    // We disable a language.
    $configLingotek->disableLanguage($es);

    // The form shouldn't have the field.
    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");
    $this->assertNoFieldByName('language_overrides[es][overrides]');
    $assert_session->optionExists('edit-language-overrides-de-overrides', 'default');

    // We enable the language back.
    $configLingotek->enableLanguage($es);

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");
    $this->assertFieldByName('language_overrides[es][overrides]');
    $assert_session->optionExists('edit-language-overrides-es-overrides', 'default');
    $assert_session->optionExists('edit-language-overrides-de-overrides', 'default');
  }

  /**
   * Tests that by default intelligence overrides are disabled.
   */
  public function testIntelligenceOverrideDefaults() {
    $this->drupalGet('admin/lingotek/settings');
    $this->clickLink(t('Add new Translation Profile'));
    $this->assertNoFieldChecked('edit-intelligence-metadata-overrides-override');
    $this->assertIntelligenceFieldDefaults();
  }

  /**
   * Tests that we can enable intelligence metadata overrides.
   */
  public function testEnableIntelligenceOverride() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $this->clickLink(t('Add new Translation Profile'));

    $profile_id = strtolower($this->randomMachineName());
    $profile_name = $this->randomString();
    $edit = [
      'id' => $profile_id,
      'label' => $profile_name,
      'auto_upload' => 1,
      'auto_download' => 1,
      'intelligence_metadata_overrides[override]' => 1,
      'intelligence_metadata[use_author]' => 1,
      'intelligence_metadata[use_author_email]' => 1,
      'intelligence_metadata[use_contact_email_for_author]' => FALSE,
      'intelligence_metadata[use_business_unit]' => 1,
      'intelligence_metadata[use_business_division]' => 1,
      'intelligence_metadata[use_campaign_id]' => 1,
      'intelligence_metadata[use_campaign_rating]' => 1,
      'intelligence_metadata[use_channel]' => 1,
      'intelligence_metadata[use_contact_name]' => 1,
      'intelligence_metadata[use_contact_email]' => 1,
      'intelligence_metadata[use_content_description]' => 1,
      'intelligence_metadata[use_external_style_id]' => 1,
      'intelligence_metadata[use_purchase_order]' => 1,
      'intelligence_metadata[use_region]' => 1,
      'intelligence_metadata[use_base_domain]' => 1,
      'intelligence_metadata[use_reference_url]' => 1,
      'intelligence_metadata[default_author_email]' => 'test@example.com',
      'intelligence_metadata[business_unit]' => 'Test Business Unit',
      'intelligence_metadata[business_division]' => 'Test Business Division',
      'intelligence_metadata[campaign_id]' => 'Campaign ID',
      'intelligence_metadata[campaign_rating]' => 5,
      'intelligence_metadata[channel]' => 'Channel Test',
      'intelligence_metadata[contact_name]' => 'Test Contact Name',
      'intelligence_metadata[contact_email]' => 'contact@example.com',
      'intelligence_metadata[content_description]' => 'Content description',
      'intelligence_metadata[external_style_id]' => 'my-style-id',
      'intelligence_metadata[purchase_order]' => 'PO32',
      'intelligence_metadata[region]' => 'region2',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The Lingotek profile has been successfully saved.'));

    // We can edit them.
    $assert_session->linkByHrefExists("/admin/lingotek/settings/profile/$profile_id/edit");

    $this->assertFieldChecked("edit-profile-$profile_id-auto-upload");
    $this->assertFieldChecked("edit-profile-$profile_id-auto-download");
    $this->assertFieldEnabled("edit-profile-$profile_id-auto-upload");
    $this->assertFieldEnabled("edit-profile-$profile_id-auto-download");

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");

    // Assert the intelligence metadata values.
    $this->assertFieldChecked('edit-intelligence-metadata-overrides-override');
    $this->assertNoFieldChecked('edit-intelligence-metadata-use-contact-email-for-author');
    $this->assertFieldByName('intelligence_metadata[default_author_email]', 'test@example.com');
    $this->assertFieldByName('intelligence_metadata[business_unit]', 'Test Business Unit');
    $this->assertFieldByName('intelligence_metadata[business_division]', 'Test Business Division');
    $this->assertFieldByName('intelligence_metadata[campaign_id]', 'Campaign ID');
    $this->assertFieldByName('intelligence_metadata[campaign_rating]', 5);
    $this->assertFieldByName('intelligence_metadata[channel]', 'Channel Test');
    $this->assertFieldByName('intelligence_metadata[contact_name]', 'Test Contact Name');
    $this->assertFieldByName('intelligence_metadata[contact_email]', 'contact@example.com');
    $this->assertFieldByName('intelligence_metadata[content_description]', 'Content description');
    $this->assertFieldByName('intelligence_metadata[external_style_id]', 'my-style-id');
    $this->assertFieldByName('intelligence_metadata[purchase_order]', 'PO32');
    $this->assertFieldByName('intelligence_metadata[region]', 'region2');

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertTrue($profile->hasAutomaticUpload());
    $this->assertTrue($profile->hasAutomaticDownload());
    $this->assertIdentical('default', $profile->getProject());
    $this->assertIdentical('default', $profile->getVault());
    $this->assertIdentical('default', $profile->getWorkflow());

    // Assert the intelligence metadata values.
    $this->assertTrue($profile->hasIntelligenceMetadataOverrides());
    $this->assertTrue($profile->getAuthorPermission());
    $this->assertTrue($profile->getAuthorEmailPermission());
    // As the value returned here can be FALSE and NULL, use assertEmpty().
    $this->assertEmpty($profile->getContactEmailForAuthorPermission());
    $this->assertTrue($profile->getBusinessUnitPermission());
    $this->assertTrue($profile->getBusinessDivisionPermission());
    $this->assertTrue($profile->getCampaignIdPermission());
    $this->assertTrue($profile->getCampaignRatingPermission());
    $this->assertTrue($profile->getChannelPermission());
    $this->assertTrue($profile->getContactNamePermission());
    $this->assertTrue($profile->getContactEmailPermission());
    $this->assertTrue($profile->getContentDescriptionPermission());
    $this->assertTrue($profile->getExternalStyleIdPermission());
    $this->assertTrue($profile->getPurchaseOrderPermission());
    $this->assertTrue($profile->getRegionPermission());
    $this->assertTrue($profile->getBaseDomainPermission());
    $this->assertTrue($profile->getReferenceUrlPermission());

    $this->assertIdentical($profile->getDefaultAuthorEmail(), 'test@example.com');
    $this->assertIdentical($profile->getBusinessUnit(), 'Test Business Unit');
    $this->assertIdentical($profile->getBusinessDivision(), 'Test Business Division');
    $this->assertIdentical($profile->getCampaignId(), 'Campaign ID');
    $this->assertIdentical($profile->getCampaignRating(), 5);
    $this->assertIdentical($profile->getChannel(), 'Channel Test');
    $this->assertIdentical($profile->getContactName(), 'Test Contact Name');
    $this->assertIdentical($profile->getContactEmail(), 'contact@example.com');
    $this->assertIdentical($profile->getContentDescription(), 'Content description');
    $this->assertIdentical($profile->getExternalStyleId(), 'my-style-id');
    $this->assertIdentical($profile->getPurchaseOrder(), 'PO32');
    $this->assertIdentical($profile->getRegion(), 'region2');
  }

  /**
   * Tests that filter is shown in the profile form when there are filters.
   */
  public function testFilters() {
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/lingotek/settings');
    $this->clickLink(t('Add new Translation Profile'));

    $this->assertFieldByName('filter');
    $this->assertFieldByName('subfilter');
    $assert_session->optionExists('edit-filter', 'default');
    $assert_session->optionExists('edit-filter', 'project_default');
    $assert_session->optionExists('edit-filter', 'drupal_default');
    $assert_session->optionExists('edit-filter', 'test_filter');
    $assert_session->optionExists('edit-filter', 'test_filter2');
    $assert_session->optionExists('edit-filter', 'test_filter3');
    $assert_session->optionExists('edit-subfilter', 'default');
    $assert_session->optionExists('edit-subfilter', 'project_default');
    $assert_session->optionExists('edit-subfilter', 'drupal_default');
    $assert_session->optionExists('edit-subfilter', 'test_filter');
    $assert_session->optionExists('edit-subfilter', 'test_filter2');
    $assert_session->optionExists('edit-subfilter', 'test_filter3');
  }

  /**
   * Tests that only three filters are given when no resource filters are available.
   */
  public function testNoFilters() {
    $assert_session = $this->assertSession();

    \Drupal::configFactory()->getEditable('lingotek.settings')->set('account.resources.filter', [])->save();

    $this->drupalGet('admin/lingotek/settings');
    $this->clickLink(t('Add new Translation Profile'));

    $this->assertFieldByName('filter');
    $option_field = $assert_session->optionExists('edit-filter', 'drupal_default');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $assert_session->optionExists('edit-filter', 'default');
    $assert_session->optionExists('edit-filter', 'project_default');
    $assert_session->optionNotExists('edit-filter', 'test_filter');
    $assert_session->optionNotExists('edit-filter', 'test_filter2');

    $this->assertFieldByName('subfilter');
    $option_field = $assert_session->optionExists('edit-subfilter', 'drupal_default');
    $this->assertTrue($option_field->hasAttribute('selected'));
    $assert_session->optionExists('edit-subfilter', 'default');
    $assert_session->optionExists('edit-subfilter', 'project_default');
    $assert_session->optionNotExists('edit-subfilter', 'test_filter');
    $assert_session->optionNotExists('edit-subfilter', 'test_filter2');
  }

  /**
   * Asserts that a field in the current page is disabled.
   *
   * @param string $id
   *   Id of field to assert.
   * @param string $message
   *   Message to display.
   *
   * @return bool
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertFieldDisabled($id, $message = '') {
    $elements = $this->xpath('//input[@id=:id]', [':id' => $id]);
    return $this->assertTrue(isset($elements[0]) && !empty($elements[0]->getAttribute('disabled')),
      $message ? $message : t('Field @id is disabled.', ['@id' => $id]), t('Browser'));
  }

  /**
   * Asserts that a field in the current page is enabled.
   *
   * @param $id
   *   Id of field to assert.
   * @param $message
   *   Message to display.
   *
   * @return bool
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertFieldEnabled($id, $message = '') {
    $elements = $this->xpath('//input[@id=:id]', [':id' => $id]);
    return $this->assertTrue(isset($elements[0]) && empty($elements[0]->getAttribute('disabled')),
      $message ? $message : t('Field @id is enabled.', ['@id' => $id]), t('Browser'));
  }

  /**
   * Setup test resources for the test.
   */
  protected function setupResources() {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('account.resources.community', [
      'test_community' => 'Test community',
      'test_community2' => 'Test community 2',
    ]);
    $config->set('account.resources.project', [
      'test_project' => 'Test project',
      'test_project2' => 'Test project 2',
    ]);
    $config->set('account.resources.vault', [
      'test_vault' => 'Test vault',
      'test_vault2' => 'Test vault 2',
    ]);
    $config->set('account.resources.workflow', [
      'test_workflow' => 'Test workflow',
      'test_workflow2' => 'Test workflow 2',
    ]);
    $config->set('account.resources.filter', [
      'test_filter' => 'Test filter',
      'test_filter2' => 'Test filter 2',
      'test_filter3' => 'Test filter 3',
    ]);
    $config->save();
  }

}
