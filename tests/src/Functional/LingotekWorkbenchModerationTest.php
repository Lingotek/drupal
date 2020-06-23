<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\workbench_moderation\Entity\ModerationState;

/**
 * Tests setting up the integration with workbench moderation.
 *
 * @group lingotek
 */
class LingotekWorkbenchModerationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'workbench_moderation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

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
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'page', TRUE);

    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    // Enable workbench moderation.
    $this->enableModerationThroughUI('article',
      ['draft', 'needs_review', 'published'], 'draft');

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
          ],
          'moderation' => [
            'upload_status' => 'draft',
            'download_transition' => 'draft_needs_review',
          ],
        ],
        'page' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Entity creation with automatic profile not in upload state does not upload.
   */
  public function testCreateEntityWithAutomaticProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Request Review'));

    $this->assertText('Article Llamas are cool has been created.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Entity creation with manual profile not in upload state does not upload.
   */
  public function testCreateEntityWithManualProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Request Review'));

    $this->assertText('Article Llamas are cool has been created.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Entity creation with automatic profile in upload state triggers the upload.
   */
  public function testCreateEntityWithAutomaticProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    $this->assertText('Article Llamas are cool has been created.');
    $this->assertText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Entity creation with manual profile in upload state does not upload.
   */
  public function testCreateEntityWithManualProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    $this->assertText('Article Llamas are cool has been created.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Entity update with automatic profile not in upload state does not upload.
   */
  public function testUpdateEntityWithAutomaticProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    $this->assertText('Article Llamas are cool has been created.');
    $this->drupalPostForm('/node/1/edit', $edit, t('Save and Request Review (this translation)'));

    $this->assertText('Article Llamas are cool has been updated.');
    $this->assertNoText('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  /**
   * Entity update with manual profile not in upload state does not upload.
   */
  public function testUpdateEntityWithManualProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    $this->assertText('Article Llamas are cool has been created.');
    $this->drupalPostForm('/node/1/edit', $edit, t('Save and Request Review (this translation)'));

    $this->assertText('Article Llamas are cool has been updated.');
    $this->assertNoText('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  /**
   * Entity update with automatic profile in upload state triggers the upload.
   */
  public function testUpdateEntityWithAutomaticProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    $this->assertText('Article Llamas are cool has been created.');
    $edit['title[0][value]'] = 'Llamas are cool!';
    $this->drupalPostForm('/node/1/edit', $edit, t('Save and Create New Draft (this translation)'));

    $this->assertText('Article Llamas are cool! has been updated.');
    $this->assertText('Llamas are cool! was updated and sent to Lingotek successfully.');
  }

  /**
   * Entity update with automatic profile in upload state does not trigger the
   * upload because there is not content change.
   */
  public function testUpdateEntityWithAutomaticProfileAndInUploadStateNoStatusChange() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    $this->assertText('Article Llamas are cool has been created.');
    $this->assertText('Llamas are cool sent to Lingotek successfully.');
    $currentStatus = $this->getSession()->getPage()->find('css', 'div[id="edit-current"]');
    $this->assertEqual($currentStatus->getText(), 'Status Draft');

    $this->drupalPostForm('/node/1/edit', $edit, t('Save and Create New Draft (this translation)'));
    $this->assertText('Article Llamas are cool has been updated.');
    $this->assertText('Llamas are cool was updated and sent to Lingotek successfully.');
    $currentStatus = $this->getSession()->getPage()->find('css', 'div[id="edit-current"]');
    $this->assertEqual($currentStatus->getText(), 'Status Draft');
  }

  /**
   * Entity update with manual profile in upload state does not upload.
   */
  public function testUpdateEntityWithManualProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    $this->assertText('Article Llamas are cool has been created.');
    $this->drupalPostForm('/node/1/edit', $edit, t('Save and Create New Draft (this translation)'));

    $this->assertText('Article Llamas are cool has been updated.');
    $this->assertNoText('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  /**
   * Configures "Needs review" as upload state, and "Publish" as the transition.
   */
  protected function configureNeedsReviewAsUploadState() {
    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[article][moderation][upload_status]' => 'needs_review',
      'node[article][moderation][download_transition]' => 'needs_review_published',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], 'lingoteksettings-tab-content-form');
  }

  /**
   * Entity moderation with automatic profile to upload state triggers upload.
   */
  public function testModerationToUploadStateWithAutomaticProfileTriggersUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    // Moderate.
    $edit = ['new_state' => 'needs_review'];
    $this->drupalPostForm(NULL, $edit, 'Apply');
    $this->assertText('The moderation state has been updated.');
    $this->assertText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Entity moderation with automatic profile to other state does not upload.
   */
  public function testModerationToNonUploadStateWithAutomaticProfileDoesntTriggerUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    // Moderate.
    $edit = ['new_state' => 'published'];
    $this->drupalPostForm(NULL, $edit, 'Apply');
    $this->assertText('The moderation state has been updated.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Entity moderation with manual profile to upload state does not upload.
   */
  public function testModerationToUploadStateWithManualProfileDoesntTriggerUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    // Moderate.
    $edit = ['new_state' => 'needs_review'];
    $this->drupalPostForm(NULL, $edit, 'Apply');
    $this->assertText('The moderation state has been updated.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Entity moderation with manual profile to other state does not upload.
   */
  public function testModerationToNonUploadStateWithManualProfileDoesntTriggerUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    // Moderate.
    $edit = ['new_state' => 'published'];
    $this->drupalPostForm(NULL, $edit, 'Apply');
    $this->assertText('The moderation state has been updated.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Download from upload state triggers a transition.
   */
  public function testDownloadFromUploadStateTriggersATransition() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    // The status is draft.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEqual($value, 'Draft', 'Workbench current status is draft');

    // Moderate to Needs review, so it's uploaded.
    $edit = ['new_state' => 'needs_review'];
    $this->drupalPostForm(NULL, $edit, 'Apply');

    // The status is needs review.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEqual($value, 'Needs Review', 'Workbench current status is Needs Review');

    $this->goToContentBulkManagementForm();
    // Request translation.
    $this->clickLink('ES');
    // Check translation.
    $this->clickLink('ES');
    // Download translation.
    $this->clickLink('ES');

    // Let's see the current status is modified.
    $this->clickLink('Llamas are cool');
    $this->assertNoFieldByName('new_state', 'The transition to a new workbench status happened (so no moderation form is shown).');
  }

  public function testDownloadWhenContentModerationWasSetupAfterLingotek() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit, 'page');

    $this->goToContentBulkManagementForm();
    // Request translation.
    $this->clickLink('ES');

    $this->enableModerationThroughUI('page',
      ['draft', 'needs_review', 'published'], 'draft');

    $this->goToContentBulkManagementForm();

    // Check translation.
    $this->clickLink('ES');
    // Download translation.
    $this->clickLink('ES');

    $this->assertTargetStatus('ES', Lingotek::STATUS_CURRENT);
  }

  /**
   * Download from different state doesn't trigger a transition.
   */
  public function testDownloadFromNotUploadStateDoesntTriggerATransition() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->drupalPostForm('/node/add/article', $edit, t('Save and Create New Draft'));

    // The status is draft.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEqual($value, 'Draft', 'Workbench current status is draft');

    // Moderate to Needs review, so it's uploaded.
    $edit = ['new_state' => 'needs_review'];
    $this->drupalPostForm(NULL, $edit, 'Apply');

    // Moderate back to draft, so the transition won't happen on download.
    $edit = ['new_state' => 'draft'];
    $this->drupalPostForm(NULL, $edit, 'Apply');

    $this->goToContentBulkManagementForm();
    // Request translation.
    $this->clickLink('ES');
    // Check translation.
    $this->clickLink('ES');
    // Download translation.
    $this->clickLink('ES');

    // Let's see the current status is unmodified.
    $this->clickLink('Llamas are cool');
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEqual($value, 'Draft', 'The transition to a new workbench status didn\'t happen because the source wasn\'t the expected.');
  }

  /**
   * Tests a content entity that is enabled, but with a disabled bundle.
   */
  public function testUnconfiguredBundle() {
    $this->drupalGet('/admin/lingotek/settings');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit, 'page');

    $this->assertText('Page Llamas are cool has been created.');
    $this->assertText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Enable moderation for a specified content type, using the UI.
   *
   * @param string $content_type_id
   *   Machine name.
   * @param string[] $allowed_states
   *   Array of allowed state IDs.
   * @param string $default_state
   *   Default state.
   */
  protected function enableModerationThroughUI($content_type_id, array $allowed_states, $default_state) {
    $this->drupalGet('admin/structure/types/manage/' . $content_type_id . '/moderation');
    $this->assertFieldByName('enable_moderation_state');
    $this->assertNoFieldChecked('edit-enable-moderation-state');

    $edit['enable_moderation_state'] = 1;

    /** @var \Drupal\workbench_moderation\Entity\ModerationState $state */
    foreach (ModerationState::loadMultiple() as $id => $state) {
      $key = $state->isPublishedState() ? 'allowed_moderation_states_published[' . $state->id() . ']' : 'allowed_moderation_states_unpublished[' . $state->id() . ']';
      $edit[$key] = (int) in_array($id, $allowed_states);
    }

    $edit['default_moderation_state'] = $default_state;

    $this->drupalPostForm(NULL, $edit, t('Save'));
  }

}
