<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\workflows\Entity\Workflow;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests setting up the integration with content moderation.
 *
 * @group lingotek
 */
class LingotekContentModerationTest extends LingotekTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'content_moderation'];

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

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    // Enable content moderation.
    $workflow = $this->createEditorialWorkflow();
    $this->enableModerationThroughUI('article');
    $this->addReviewStateToEditorialWorkflow();

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
            'download_transition' => 'request_review',
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
   * Tests creating an entity with automatic profile but not in upload state is not uploaded.
   */
  public function testCreateEntityWithAutomaticProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsRequestReviewNodeForm($edit, 'article');

    $this->assertText('Article Llamas are cool has been created.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Tests creating an entity with manual profile but not in upload state is not uploaded.
   */
  public function testCreateEntityWithManualProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsRequestReviewNodeForm($edit, 'article');

    $this->assertText('Article Llamas are cool has been created.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Tests creating an entity with automatic profile and in upload state is uploaded.
   */
  public function testCreateEntityWithAutomaticProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertText('Article Llamas are cool has been created.');
    $this->assertText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Tests creating an entity with manual profile and in upload state is not uploaded.
   */
  public function testCreateEntityWithManualProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertText('Article Llamas are cool has been created.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  /**
   * Tests updating an entity with automatic profile but not in upload state is not uploaded.
   */
  public function testUpdateEntityWithAutomaticProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertText('Article Llamas are cool has been created.');
    $this->editAsRequestReviewNodeForm('/node/1/edit', $edit);

    $this->assertText('Article Llamas are cool has been updated.');
    $this->assertNoText('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  /**
   * Tests updating an entity with manual profile but not in upload state is not uploaded.
   */
  public function testUpdateEntityWithManualProfileButNotInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertText('Article Llamas are cool has been created.');
    $this->editAsRequestReviewNodeForm('/node/1/edit', $edit);

    $this->assertText('Article Llamas are cool has been updated.');
    $this->assertNoText('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  /**
   * Tests updating an entity with automatic profile and in upload state is uploaded.
   */
  public function testUpdateEntityWithAutomaticProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertText('Article Llamas are cool has been created.');
    $edit['body[0][value]'] = 'Llamas are very cool!';
    $this->editAsNewDraftNodeForm('/node/1/edit', $edit);

    $this->assertText('Article Llamas are cool has been updated.');
    $this->assertText('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  /**
   * Tests updating an entity with manual profile and in upload state is not uploaded.
   */
  public function testUpdateEntityWithManualProfileAndInUploadState() {
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->assertText('Article Llamas are cool has been created.');
    $this->editAsNewDraftNodeForm('/node/1/edit', $edit);

    $this->assertText('Article Llamas are cool has been updated.');
    $this->assertNoText('Llamas are cool was updated and sent to Lingotek successfully.');
  }

  protected function configureNeedsReviewAsUploadState() {
    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
          ],
          'moderation' => [
            'upload_status' => 'needs_review',
            'download_transition' => 'publish',
          ],
        ],
      ],
    ]);
  }

  public function testModerationToUploadStateWithAutomaticProfileTriggersUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    // Moderate.
    $edit = ['new_state' => 'needs_review'];
    $this->drupalPostForm(NULL, $edit, 'Apply');
    $this->assertText('The moderation state has been updated.');
    $this->assertText('Llamas are cool sent to Lingotek successfully.');
  }

  public function testModerationToNonUploadStateWithAutomaticProfileDoesntTriggerUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    // Moderate.
    $edit = ['new_state' => 'published'];
    $this->drupalPostForm(NULL, $edit, 'Apply');
    $this->assertText('The moderation state has been updated.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  public function testModerationToUploadStateWithManualProfileDoesntTriggerUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    // Moderate.
    $edit = ['new_state' => 'needs_review'];
    $this->drupalPostForm(NULL, $edit, 'Apply');
    $this->assertText('The moderation state has been updated.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  public function testModerationToNonUploadStateWithManualProfileDoesntTriggerUpload() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    // Moderate.
    $edit = ['new_state' => 'published'];
    $this->drupalPostForm(NULL, $edit, 'Apply');
    $this->assertText('The moderation state has been updated.');
    $this->assertNoText('Llamas are cool sent to Lingotek successfully.');
  }

  public function testDownloadFromUploadStateTriggersATransition() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

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
    $this->assertNoFieldByName('new_state', 'The transition to a new content moderation status happened (so no moderation form is shown).');
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

    $this->enableModerationThroughUI('page');

    $this->goToContentBulkManagementForm();

    // Check translation.
    $this->clickLink('ES');
    // Download translation.
    $this->clickLink('ES');

    $this->assertTargetStatus('ES', Lingotek::STATUS_CURRENT);
  }

  public function testDownloadWithInvalidTransition() {
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

    $this->config('lingotek.settings')
      ->set('translate.entity.node.article.content_moderation.download_transition', 'invalid_transition')
      ->save();

    // The status is draft.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEquals($value, 'Draft', 'Workbench current status is draft');

    // Moderate to Needs review, so it's uploaded.
    $edit = ['new_state' => 'needs_review'];
    $this->drupalPostForm(NULL, $edit, 'Apply');

    // The status is needs review.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEquals($value, 'Needs Review', 'Workbench current status is Needs Review');

    $this->goToContentBulkManagementForm();
    // Request translation.
    $this->clickLink('ES');
    // Check translation.
    $this->clickLink('ES');
    // Download translation.
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');

    // Let's see the current status is modified.
    $this->clickLink('Llamas are cool');
    // The status didn't change.
    $value = $this->xpath('//div[@id="edit-current"]/text()');
    $value = trim($value[1]->getText());
    $this->assertEquals($value, 'Needs Review', 'Content moderation current status is Needs Review');
  }

  public function testDownloadFromNotUploadStateDoesntTriggerATransition() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+revision');
    $this->configureNeedsReviewAsUploadState();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAsNewDraftNodeForm($edit, 'article');

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
    $this->assertEqual($value, 'Draft', 'The transition to a new content moderation status didn\'t happen because the source wasn\'t the expected.');
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
   */
  protected function enableModerationThroughUI($content_type_id) {
    $this->drupalGet('/admin/config/workflow/workflows/manage/editorial/type/node');
    $this->assertFieldByName("bundles[$content_type_id]");
    $edit["bundles[$content_type_id]"] = TRUE;
    $this->drupalPostForm(NULL, $edit, t('Save'));
  }

  /**
   * Adds a review state to the editorial workflow.
   */
  protected function addReviewStateToEditorialWorkflow() {
    // Add a "Needs review" state to the editorial workflow.
    $workflow = Workflow::load('editorial');
    $definition = $workflow->getTypePlugin();
    $definition->addState('needs_review', 'Needs Review');
    $definition->addTransition('request_review', 'Request Review', ['draft'], 'needs_review');
    $definition->addTransition('publish_review', 'Publish Review', ['needs_review'], 'published');
    $definition->addTransition('back_to_draft', 'Back to Draft', ['needs_review'], 'draft');
    $workflow->save();
  }

}
