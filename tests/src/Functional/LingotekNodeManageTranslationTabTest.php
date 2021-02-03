<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\lingotek\Lingotek;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests translating a node using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeManageTranslationTabTest extends LingotekTestBase {

  use TaxonomyTestTrait;
  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'taxonomy'];

  /**
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);

    $this->vocabulary = $this->createVocabulary();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
      'auto_create' => TRUE,
    ];
    $this->createEntityReferenceField('node', 'article',
      'field_tags', 'Tags', 'taxonomy_term', 'default',
      $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    EntityFormDisplay::load('node.article.default')
      ->setComponent('field_tags', [
        'type' => 'entity_reference_autocomplete_tags',
      ])
      ->save();
    EntityViewDisplay::load('node.article.default')
      ->setComponent('field_tags')
      ->save();

    $this->createEntityReferenceField('taxonomy_term', $this->vocabulary->id(),
      'field_tags', 'Tags', 'taxonomy_term', 'default',
      $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $entity_form_display = EntityFormDisplay::load('taxonomy_term' . '.' . $this->vocabulary->id() . '.' . 'default');
    if (!$entity_form_display) {
      $entity_form_display = EntityFormDisplay::create([
        'targetEntityType' => 'taxonomy_term',
        'bundle' => $this->vocabulary->id(),
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $entity_form_display->setComponent('field_tags', [
        'type' => 'entity_reference_autocomplete_tags',
      ])
      ->save();
    $display = EntityViewDisplay::load('taxonomy_term' . '.' . $this->vocabulary->id() . '.' . 'default');
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => 'taxonomy_term',
        'bundle' => $this->vocabulary->id(),
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $display->setComponent('field_tags')
      ->save();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $this->vocabulary->id())->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $this->vocabulary->id(), TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $bundle = $this->vocabulary->id();
    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
          ],
        ],
      ],
      'taxonomy_term' => [
        $bundle => [
          'profiles' => 'manual',
          'fields' => [
            'name' => 1,
            'description' => 1,
          ],
        ],
      ],
    ]);
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
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->createRelatedTermsForTestingDepth();

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Check that the manage translations tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $this->assertText('Llamas are cool');
    $this->assertText('Camelid');
    $this->assertText('Herbivorous');
    // Assert second level is not included.
    $this->assertNoText('Hominid');
    // Assert third level is not included.
    $this->assertNoText('Ruminant');

    $this->drupalPostForm(NULL, ['depth' => 2], 'Apply');

    $this->assertText('Llamas are cool');
    $this->assertText('Camelid');
    $this->assertText('Herbivorous');
    // Assert second level is included.
    $this->assertText('Hominid');
    // Assert third level is not included.
    $this->assertNoText('Ruminant');

    $this->drupalPostForm(NULL, ['depth' => 3], 'Apply');

    $this->assertText('Llamas are cool');
    $this->assertText('Camelid');
    $this->assertText('Herbivorous');
    // Assert second level is included.
    $this->assertText('Hominid');
    // Assert third level is also included.
    $this->assertText('Ruminant');

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

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
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('es_MX');
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');
  }

  /**
   * Tests that a node cannot be translated if not configured, and will provide user-friendly messages.
   */
  public function testNodeTranslationMessageWhenBundleNotTranslatable() {
    $assert_session = $this->assertSession();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Pages are cool';
    $edit['body[0][value]'] = 'Pages are very cool';
    $this->drupalPostForm('node/add/page', $edit, t('Save'));

    // Check that the manage translations tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $assert_session->pageTextContains('Not enabled');
    $this->clickLink('EN');
    $assert_session->pageTextContains('Cannot upload Page Pages are cool. That Content type is not enabled for translation.');

    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertIdentical(NULL, \Drupal::state()
      ->get('lingotek.uploaded_locale'));
    $assert_session->pageTextContains('Cannot upload Page Pages are cool. That Content type is not enabled for translation.');
  }

  /**
   * Tests that a node cannot be translated if not configured, and will provide user-friendly messages.
   */
  public function testNodeTranslationMessageWhenBundleNotConfigured() {
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

    // Check that the manage translations tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $assert_session->pageTextContains('Not enabled');
    $this->clickLink('EN');
    $assert_session->pageTextContains('Cannot upload Page Pages are cool. That Content type is not enabled for Lingotek translation.');

    $edit = [
      'table[node:1]' => TRUE,
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
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->createRelatedTermsForTestingDepth();

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Check that the manage tranlsations tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $this->assertText('Llamas are cool');
    $this->assertText('Camelid');
    $this->assertText('Herbivorous');
    // Assert second level is not included.
    $this->assertNoText('Hominid');
    // Assert third level is not included.
    $this->assertNoText('Ruminant');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the German (AT) translation.
    $this->assertLingotekRequestTranslationLink('de_AT');
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the German (AT) translation.
    $this->assertLingotekCheckTargetStatusLink('de_AT');
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the German (AT) translation.
    $this->assertLingotekDownloadTargetLink('de_AT');
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('de_AT', 'dummy-document-hash-id', 'DE');
  }

  /**
   * Tests if job id is uploaded on upload.
   */
  public function testJobIdOnUpload() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);

    $edit = [
      'table[node:1]' => TRUE,
      'job_id' => 'my_custom_job_id',
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];

    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));
    $this->assertIdentical('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The column for Job ID exists and there are values.
    $this->assertText('Job ID');
    $this->assertText('my_custom_job_id');
  }

  /**
   * Tests that can we assign job ids with the bulk operation.
   */
  public function testAssignJobIds() {
    // Create a couple of content.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'node');
    $this->assertLingotekUploadLink(1, 'taxonomy_term', NULL, 'node');

    $edit = [
      'table[node:1]' => TRUE,
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no upload.
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Node Llamas are cool has been uploaded.');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID without notification to the TMS, no update happens.
    $edit = [
      'table[node:1]' => TRUE,
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no upload.
    \Drupal::state()->resetCache();
    $this->assertNotNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNotNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertEquals('my_custom_job_id', \Drupal::state()->get('lingotek.uploaded_job_id'));
  }

  /**
   * Tests that can we assign job ids with the bulk operation with TMS update.
   */
  public function testAssignJobIdsWithTMSUpdate() {
    // Create a couple of content.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'node');
    $this->assertLingotekUploadLink(1, 'taxonomy_term', NULL, 'node');

    $edit = [
      'table[node:1]' => TRUE,
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is no update, because there are no document ids.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_content'));
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is displayed.
    $this->assertText('my_custom_job_id');

    // And the job id is used on upload.
    $this->clickLink('EN');

    $this->assertText('Node Llamas are cool has been uploaded.');
    // Check that the job id used was the right one.
    \Drupal::state()->resetCache();
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'my_custom_job_id');

    // If we update the job ID with notification to the TMS, an update happens.
    $edit = [
      'table[node:1]' => TRUE,
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'other_job_id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // There is an update.
    \Drupal::state()->resetCache();
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_title'));
    $this->assertIdentical(\Drupal::state()->get('lingotek.uploaded_job_id'), 'other_job_id');
  }

  /**
   * Tests that we cannot assign job ids with invalid chars.
   */
  public function testAssignInvalidJobIdsWithTMSUpdate() {
    // Create a couple of content.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'node');
    $this->assertLingotekUploadLink(1, 'taxonomy_term', NULL, 'node');
    $this->clickLink('EN');

    $edit = [
      'table[node:1]' => TRUE,
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'job_id' => 'my\invalid\id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('The job ID name cannot contain invalid chars as "/" or "\".');

    // There is no update, because it's not valid.
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));

    $edit = [
      'job_id' => 'my/invalid/id',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('The job ID name cannot contain invalid chars as "/" or "\".');

    // There is no update, because it's not valid.
    $this->assertNull(\Drupal::state()->get('lingotek.uploaded_job_id'));
  }

  /**
   * Tests that can we cancel assignation of job ids with the bulk operation.
   */
  public function testCancelAssignJobIds() {
    // Create a couple of content.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'node');
    $this->assertLingotekUploadLink(1, 'taxonomy_term', NULL, 'node');

    // Canceling resets.
    $edit = [
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Camelid');
    $this->assertNoText('Llamas are cool');
    $this->drupalPostForm(NULL, [], 'Cancel');

    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertNoText('Camelid');
    $this->assertText('Llamas are cool');
  }

  /**
   * Tests that can we reset assignation of job ids with the bulk operation.
   */
  public function testResetAssignJobIds() {
    // Create a couple of content.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'node');
    $this->assertLingotekUploadLink(1, 'taxonomy_term', NULL, 'node');

    // Canceling resets.
    $edit = [
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertText('Camelid');
    $this->assertNoText('Llamas are cool');

    $this->goToContentBulkManagementForm();

    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->assertNoText('Camelid');
    $this->assertText('Llamas are cool');
  }

  /**
   * Tests clearing job ids.
   */
  public function testClearJobIds() {
    // Create a couple of content.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'node');
    $this->assertLingotekUploadLink(1, 'taxonomy_term', NULL, 'node');

    $this->clickLink('EN');

    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    $edit = [
      'table[node:1]' => TRUE,
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalPostForm(NULL, [], 'Clear Job ID');
    $this->assertText('Job ID was cleared successfully.');

    // There is no upload.
    \Drupal::state()->resetCache();
    $this->assertEquals('my_custom_job_id_1', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertNoText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  /**
   * Tests clearing job ids with TMS update.
   */
  public function testClearJobIdsWithTMSUpdate() {
    // Create a couple of content.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1, 'node');
    $this->assertLingotekUploadLink(1, 'taxonomy_term', NULL, 'node');

    $this->clickLink('EN');

    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_1',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    $edit = [
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForAssignJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $edit = [
      'job_id' => 'my_custom_job_id_2',
      'update_tms' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Assign Job ID');
    $this->assertText('Job ID was assigned successfully.');

    // The job id is displayed.
    $this->assertText('my_custom_job_id_1');
    $this->assertText('my_custom_job_id_2');

    $edit = [
      'table[node:1]' => TRUE,
      'table[taxonomy_term:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForClearJobId('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalPostForm(NULL, ['update_tms' => 1], 'Clear Job ID');
    $this->assertText('Job ID was cleared successfully.');

    // There is an update with empty job id.
    \Drupal::state()->resetCache();
    $this->assertEquals('', \Drupal::state()->get('lingotek.uploaded_job_id'));

    // The job id is gone.
    $this->assertNoText('my_custom_job_id_1');
    $this->assertNoText('my_custom_job_id_2');
  }

  public function testCorrectTargetsInNonSourceLanguage() {
    $this->testNodeTranslationUsingLinks();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();

    $this->goToContentBulkManagementForm('node');

    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('DE', Lingotek::STATUS_REQUEST);
    $this->assertNoTargetStatus('EN', Lingotek::STATUS_CURRENT);

    $this->goToContentBulkManagementForm('node', 'es');

    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('DE', Lingotek::STATUS_REQUEST);
    $this->assertNoTargetStatus('EN', Lingotek::STATUS_CURRENT);
  }

  /**
   * Tests that the depth level filter works properly and the embedded content
   * is in a separate table.
   */
  public function testEmbeddedContentInSeparateListing() {
    $assert_session = $this->assertSession();
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->createRelatedTermsForTestingDepth();

    // Check that the manage translations tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $assert_session->elementContains('css', 'table#edit-table', 'Llamas are cool');
    // Assert first level is included.
    $assert_session->elementContains('css', 'table#edit-table', 'Camelid');
    $assert_session->elementContains('css', 'table#edit-table', 'Herbivorous');
    // Assert second level is not included.
    $assert_session->elementNotContains('css', 'table#edit-table', 'Hominid');
    // Assert third level is not included.
    $assert_session->elementNotContains('css', 'table#edit-table', 'Ruminant');

    $this->drupalPostForm(NULL, ['depth' => 2], 'Apply');

    $assert_session->elementContains('css', 'table#edit-table', 'Llamas are cool');
    // Assert first level is included.
    $assert_session->elementContains('css', 'table#edit-table', 'Camelid');
    $assert_session->elementContains('css', 'table#edit-table', 'Herbivorous');
    // Assert second level is included.
    $assert_session->elementContains('css', 'table#edit-table', 'Hominid');
    // Assert third level is not included.
    $assert_session->elementNotContains('css', 'table#edit-table', 'Ruminant');

    $this->drupalPostForm(NULL, ['depth' => 3], 'Apply');

    $assert_session->elementContains('css', 'table#edit-table', 'Llamas are cool');
    // Assert first level is included.
    $assert_session->elementContains('css', 'table#edit-table', 'Camelid');
    $assert_session->elementContains('css', 'table#edit-table', 'Herbivorous');
    // Assert second level is included.
    $assert_session->elementContains('css', 'table#edit-table', 'Hominid');
    // Assert third level is included.
    $assert_session->elementContains('css', 'table#edit-table', 'Ruminant');

    // If we configure the field so it's embedded, we won't list its contents
    // anymore as a related content in manage tab.
    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
            'field_tags' => 1,
          ],
        ],
      ],
    ]);
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $assert_session->elementContains('css', 'table#edit-table', 'Llamas are cool');
    // Assert first level is not included.
    $assert_session->elementNotContains('css', 'table#edit-table', 'Camelid');
    $assert_session->elementNotContains('css', 'table#edit-table', 'Herbivorous');
    // Assert second level is not included.
    $assert_session->elementNotContains('css', 'table#edit-table', 'Hominid');
    // Assert third level is not included.
    $assert_session->elementNotContains('css', 'table#edit-table', 'Ruminant');

    // But the first two are listed as embedded content.
    $assert_session->elementContains('css', 'details#edit-related table', 'Camelid');
    $assert_session->elementContains('css', 'details#edit-related table', 'Herbivorous');
    // Assert second level is not included.
    $assert_session->elementNotContains('css', 'details#edit-related table', 'Hominid');
    // Assert third level is not included.
    $assert_session->elementNotContains('css', 'details#edit-related table', 'Ruminant');
  }

  /**
   * {@inheritdoc}
   *
   * We override this for the destination url.
   */
  protected function getContentBulkManagementFormUrl($entity_type_id = 'node', $prefix = NULL) {
    return ($prefix === NULL ? '' : '/' . $prefix) . '/' . $entity_type_id . '/1/manage';
  }

  /**
   * Create some terms with relations so we can test if they are listed or not.
   */
  protected function createRelatedTermsForTestingDepth() {
    $term3 = Term::create(['name' => 'Hominid', 'vid' => $this->vocabulary->id()]);
    $term3->save();

    $term2 = Term::load(2);
    $term2->field_tags = $term3;
    $term2->save();

    $term4 = Term::create(['name' => 'Ruminant', 'vid' => $this->vocabulary->id()]);
    $term4->save();

    $term3 = Term::load(3);
    $term3->field_tags = $term4;
    $term3->save();
  }

}
