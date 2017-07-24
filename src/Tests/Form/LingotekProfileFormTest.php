<?php

namespace Drupal\lingotek\Tests\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\Tests\LingotekTestBase;

/**
 * Tests the Lingotek profile form.
 *
 * @group lingotek
 */
class LingotekProfileFormTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node'];

  /**
   * Test that default profiles are present.
   */
  public function testDefaultProfilesPresent() {
    $this->drupalGet('admin/lingotek/settings');

    // Status of the checkbox matrix is as expected.
    $this->assertFieldChecked('edit-profile-automatic-auto-upload');
    $this->assertFieldChecked('edit-profile-automatic-auto-download');
    $this->assertNoFieldChecked('edit-profile-manual-auto-upload');
    $this->assertNoFieldChecked('edit-profile-manual-auto-download');
    $this->assertNoFieldChecked('edit-profile-disabled-auto-upload');
    $this->assertNoFieldChecked('edit-profile-disabled-auto-download');

    // We cannot edit them.
    $this->assertNoLinkByHref('/admin/lingotek/settings/profile/automatic/edit');
    $this->assertNoLinkByHref('/admin/lingotek/settings/profile/manual/edit');
    $this->assertNoLinkByHref('/admin/lingotek/settings/profile/disabled/edit');

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
    $this->drupalGet('admin/lingotek/settings');

    $this->clickLink(t('Add new Translation Profile'));

    $profile_id = strtolower($this->randomMachineName());
    $profile_name = $this->randomString();
    $edit = [
      'id' => $profile_id,
      'label' => $profile_name,
      'auto_upload' => 1,
      'auto_download' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->assertText(t('The Lingotek profile has been successfully saved.'));

    // We can edit them.
    $this->assertLinkByHref("/admin/lingotek/settings/profile/$profile_id/edit");

    $this->assertFieldChecked("edit-profile-$profile_id-auto-upload");
    $this->assertFieldChecked("edit-profile-$profile_id-auto-download");
    $this->assertFieldEnabled("edit-profile-$profile_id-auto-upload");
    $this->assertFieldEnabled("edit-profile-$profile_id-auto-download");

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertTrue($profile->hasAutomaticUpload());
    $this->assertTrue($profile->hasAutomaticDownload());
    $this->assertIdentical('default', $profile->getProject());
    $this->assertIdentical('default', $profile->getVault());
    $this->assertIdentical('default', $profile->getWorkflow());
  }

  /**
   * Test editing profiles.
   */
  public function testEditingProfile() {
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
      'project' => 'test_project',
      'vault' => 'test_vault',
      'workflow' => 'test_workflow',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertFalse($profile->hasAutomaticUpload());
    $this->assertTrue($profile->hasAutomaticDownload());
    $this->assertIdentical('test_project', $profile->getProject());
    $this->assertIdentical('test_vault', $profile->getVault());
    $this->assertIdentical('test_workflow', $profile->getWorkflow());

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");
    $this->assertNoFieldChecked("edit-auto-upload");
    $this->assertFieldChecked("edit-auto-download");
    $this->assertOptionSelected('edit-project', 'test_project');
    $this->assertOptionSelected('edit-vault', 'test_vault');
    $this->assertOptionSelected('edit-workflow', 'test_workflow');
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

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = $profile_id;
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

    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => $profile_id,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');


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

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => $profile_id,
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

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
    $this->assertOptionSelected('edit-language-overrides-es-overrides', 'default');
    $this->assertOptionSelected('edit-language-overrides-en-overrides', 'default');

    $edit = [
      'auto_upload' => FALSE,
      'auto_download' => 1,
      'project' => 'default',
      'vault' => 'default',
      'workflow' => 'another_workflow',
      'language_overrides[es][overrides]' => 'custom',
      'language_overrides[es][custom][auto_download]' => FALSE,
      'language_overrides[es][custom][workflow]' => 'test_workflow',
      'language_overrides[de][overrides]' => 'custom',
      'language_overrides[de][custom][auto_download]' => FALSE,
      'language_overrides[de][custom][workflow]' => 'default',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    /** @var \Drupal\lingotek\LingotekProfileInterface $profile */
    $profile = LingotekProfile::load($profile_id);
    $this->assertFalse($profile->hasAutomaticUpload());
    $this->assertTrue($profile->hasAutomaticDownload());
    $this->assertIdentical('default', $profile->getProject());
    $this->assertIdentical('default', $profile->getVault());
    $this->assertIdentical('another_workflow', $profile->getWorkflow());
    $this->assertIdentical('test_workflow', $profile->getWorkflowForTarget('es'));
    $this->assertIdentical('default', $profile->getWorkflowForTarget('de'));
    $this->assertFalse($profile->hasAutomaticDownloadForTarget('es'));
    $this->assertFalse($profile->hasAutomaticDownloadForTarget('de'));

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");
    $this->assertNoFieldChecked("edit-auto-upload");
    $this->assertFieldChecked("edit-auto-download");
    $this->assertOptionSelected('edit-project', 'default');
    $this->assertOptionSelected('edit-vault', 'default');
    $this->assertOptionSelected('edit-workflow', 'another_workflow');
    $this->assertOptionSelected('edit-language-overrides-es-overrides', 'custom');
    $this->assertOptionSelected('edit-language-overrides-de-overrides', 'custom');
    $this->assertOptionSelected('edit-language-overrides-en-overrides', 'default');
    $this->assertNoFieldChecked('edit-language-overrides-es-custom-auto-download');
    $this->assertNoFieldChecked('edit-language-overrides-de-custom-auto-download');
    $this->assertFieldChecked('edit-language-overrides-en-custom-auto-download');
    $this->assertOptionSelected('edit-language-overrides-es-custom-workflow', 'test_workflow');
    $this->assertOptionSelected('edit-language-overrides-de-custom-workflow', 'default');
    $this->assertOptionSelected('edit-language-overrides-en-custom-workflow', 'default');

    // Assert that the override languages are present and ordered alphabetically.
    $selects = $this->xpath('//details[@id="edit-language-overrides"]/*/*//select');
    // There must be 2 select options for each of the 3 languages.
    $this->assertEqual(count($selects), 2 * 3, 'There are options for all the potential language overrides.');
    // And the first one must be German alphabetically.
    $this->assertEqual((string)$selects[0]['id'], 'edit-language-overrides-de-overrides', 'Languages are ordered alphabetically.');
  }

  /**
   * Tests that disabled languages are not shown in the profile form for
   * defining overrides.
   */
  public function testLanguageDisabled() {
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
    $this->assertOptionSelected('edit-language-overrides-de-overrides', 'default');
    $this->assertOptionSelected('edit-language-overrides-de-overrides', 'default');

    // We disable a language.
    $configLingotek->disableLanguage($es);

    // The form shouldn't have the field.
    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");
    $this->assertNoFieldByName('language_overrides[es][overrides]');
    $this->assertOptionSelected('edit-language-overrides-de-overrides', 'default');

    // We enable the language back.
    $configLingotek->enableLanguage($es);

    $this->drupalGet("/admin/lingotek/settings/profile/$profile_id/edit");
    $this->assertFieldByName('language_overrides[es][overrides]');
    $this->assertOptionSelected('edit-language-overrides-es-overrides', 'default');
    $this->assertOptionSelected('edit-language-overrides-de-overrides', 'default');
  }

  /**
   * Asserts that a field in the current page is disabled.
   *
   * @param string $id
   *   Id of field to assert.
   * @param string $message
   *   Message to display.
   * @return bool
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertFieldDisabled($id, $message = '') {
    $elements = $this->xpath('//input[@id=:id]', [':id' => $id]);
    return $this->assertTrue(isset($elements[0]) && !empty($elements[0]['disabled']),
      $message ? $message : t('Field @id is disabled.', ['@id' => $id]), t('Browser'));
  }

  /**
   * Asserts that a field in the current page is enabled.
   *
   * @param $id
   *   Id of field to assert.
   * @param $message
   *   Message to display.
   * @return bool
   *   TRUE on pass, FALSE on fail.
   */
  protected function assertFieldEnabled($id, $message = '') {
    $elements = $this->xpath('//input[@id=:id]', [':id' => $id]);
    return $this->assertTrue(isset($elements[0]) && empty($elements[0]['disabled']),
      $message ? $message : t('Field @id is enabled.', ['@id' => $id]), t('Browser'));
  }

}
