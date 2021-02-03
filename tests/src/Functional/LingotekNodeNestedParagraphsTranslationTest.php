<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests translating a node with multiple locales including nested paragraphs.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeNestedParagraphsTranslationTest extends LingotekTestBase {

  protected $paragraphsTranslatable = FALSE;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'image', 'comment', 'paragraphs', 'lingotek_paragraphs_test'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();
    ConfigurableLanguage::createFromLangcode('es-ar')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'paragraphed_nested_content')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('paragraph', 'paragraph_container')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('paragraph', 'image_text')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'paragraphed_nested_content', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('paragraph', 'paragraph_container', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('paragraph', 'image_text', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    if ($this->paragraphsTranslatable) {
      $this->setParagraphFieldsTranslatability();
    }

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'paragraphed_nested_content' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'field_paragraph_container' => 1,
          ],
        ],
      ],
      'paragraph' => [
        'image_text' => [
          'fields' => [
            'field_image_demo' => ['title', 'alt'],
            'field_text_demo' => 1,
          ],
        ],
        'paragraph_container' => [
          'fields' => [
            'field_paragraphs_demo' => 1,
          ],
        ],
      ],
    ]);
    $this->drupalGet('admin/lingotek/settings');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+nestedparagraphs');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithParagraphsTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_nested_content');

    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $this->saveAndPublishNodeForm($edit, NULL);

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEqual($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEqual($data['field_paragraph_container'][0]['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Lingotek Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

  /**
   * Tests that the metadata of the node and the embedded paragraphs is included.
   */
  public function testContentEntityMetadataIsIncluded() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_nested_content');
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';

    $this->saveAndPublishNodeForm($edit, NULL);

    $this->node = Node::load(1);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $serialized_node = $translation_service->getSourceData($this->node);
    $this->verbose(var_export($serialized_node, TRUE));
    // Main node metadata is there.
    $this->assertTrue(isset($serialized_node['_lingotek_metadata']), 'The Lingotek metadata is included in the extracted data.');
    $this->assertEqual('node', $serialized_node['_lingotek_metadata']['_entity_type_id'], 'Entity type id is included as metadata.');
    $this->assertEqual(1, $serialized_node['_lingotek_metadata']['_entity_id'], 'Entity id is included as metadata.');
    $this->assertEqual(1, $serialized_node['_lingotek_metadata']['_entity_revision'], 'Entity revision id is included as metadata.');
  }

  /**
   * Paragraphs don't have a title, so we should disallow filtering by it.
   */
  public function testBulkManagementParagraphsDontAllowFilteringByLabel() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_nested_content');
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';

    $this->saveAndPublishNodeForm($edit, NULL);

    $this->goToContentBulkManagementForm('paragraph');
    $this->assertNoField('filters[wrapper][label]', 'There is no filter by label as paragraphs have no label.');
  }

  /**
   * Paragraphs don't have a title, so we ignore a label filter if it exists.
   */
  public function testBulkManagementParagraphsIgnoreFilterByLabel() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_nested_content');
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';

    $this->saveAndPublishNodeForm($edit, NULL);

    // Ensure paragraphs tab is enabled.
    $this->drupalPostForm('admin/lingotek/settings', ['contrib[paragraphs][enable_bulk_management]' => 1], 'Save settings', [], 'lingoteksettings-integrations-form');

    $this->goToContentBulkManagementForm('paragraph');
    // Assert there is at least one paragraph in the list.
    $this->assertText('Image + Text');

    // Set a filter, and there should still be paragraphs.
    /** @var \Drupal\user\PrivateTempStore $tempStore */
    $tempStore = \Drupal::service('tempstore.private')->get('lingotek.management.filter.paragraph');
    $tempStore->set('label', 'Llamas');

    $this->goToContentBulkManagementForm('paragraph');
    $this->assertText('Image + Text');
  }

  public function testParagraphEditsAreKeptWhenTranslating() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+nestedparagraphs_multiple');

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_nested_content');

    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $this->createNestedParagraphedNode('automatic');

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEqual($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEqual($data['field_paragraph_container'][0]['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool for the first time');
    $this->assertEqual($data['field_paragraph_container'][0]['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Llamas are very cool for the second time');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Lingotek Workbench');

    // Edit the original node.
    $this->drupalGet('node/1');
    $this->clickLink('Edit');

    $edit = [];
    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]'] = 'Dogs are very cool for the first time';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][1][subform][field_text_demo][0][value]'] = 'Dogs are very cool for the second time';

    $this->saveAndKeepPublishedNodeForm($edit, 1, FALSE);

    $this->assertText('Paragraphed nested content Dogs are cool has been updated.');
    $this->assertText('Dogs are very cool for the first time');
    $this->assertText('Dogs are very cool for the second time');

    // Go back to translations.
    $this->clickLink('Translate');

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Dogs are cool is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Dogs are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas por primera vez');
    $this->assertText('Las llamas son muy chulas por segunda vez');

    // The saved revision is kept.
    $this->clickLink('Translate');
    $this->clickLink('Dogs are cool');
    $this->assertText('Dogs are very cool for the first time');
    $this->assertText('Dogs are very cool for the second time');
  }

  public function testParagraphRevisionsAreKeptWhenTranslating() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+nestedparagraphs_multiple');

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_nested_content');

    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $this->createNestedParagraphedNode('automatic');

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEqual($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEqual($data['field_paragraph_container'][0]['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool for the first time');
    $this->assertEqual($data['field_paragraph_container'][0]['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Llamas are very cool for the second time');
    $this->assertEqual($data['field_paragraph_container'][1]['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Dogs are very cool for the first time');
    $this->assertEqual($data['field_paragraph_container'][1]['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Dogs are very cool for the second time');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Lingotek Workbench');

    // Edit the original node.
    $this->drupalGet('node/1');
    $this->clickLink('Edit');

    $edit = [];
    $edit['title[0][value]'] = 'Cats are cool';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]'] = 'Cats are very cool for the first time';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][1][subform][field_text_demo][0][value]'] = 'Cats are very cool for the second time';
    $edit['revision'] = 1;
    $this->saveAndUnpublishNodeForm($edit, 1, FALSE);

    $this->assertText('Paragraphed nested content Cats are cool has been updated.');
    $this->assertText('Cats are very cool for the first time');
    $this->assertText('Cats are very cool for the second time');

    // Go back to translations.
    $this->clickLink('Translate');

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Cats are cool is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Cats are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas por primera vez');
    $this->assertText('Las llamas son muy chulas por segunda vez');
    $this->assertText('Los perros son muy chulos por primera vez');
    $this->assertText('Los perros son muy chulos por segunda vez');

    // The latest revision is kept.
    $this->clickLink('Translate');
    $this->clickLink('Cats are cool');
    $this->assertText('Cats are very cool for the first time');
    $this->assertText('Cats are very cool for the second time');
    $this->assertText('Dogs are very cool for the first time');
    $this->assertText('Dogs are very cool for the second time');
    $this->assertNoText('Llamas are very cool for the first time');
    $this->assertNoText('Llamas are very cool for the second time');

    // The published revision is not updated.
    $this->drupalGet('node/1/revisions/1/view');
    $this->assertText('Llamas are very cool for the first time');
    $this->assertText('Llamas are very cool for the second time');
    $this->assertText('Dogs are very cool for the first time');
    $this->assertText('Dogs are very cool for the second time');
    $this->assertNoText('Cats are very cool for the first time');
    $this->assertNoText('Cats are very cool for the second time');
  }

  /**
   * Tests that metadata is created when a paragraph is added.
   */
  public function testParagraphContentMetadataIsSavedWhenContentAdded() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_nested_content');

    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $this->saveAndPublishNodeForm($edit, NULL);

    $metadata = LingotekContentMetadata::loadMultiple();
    $this->assertEqual(3, count($metadata), 'There is metadata saved for the parent entity and the child nested entities.');
  }

  /**
   * Tests that orphan paragraph references don't break the upload or download.
   */
  public function testMissingParagraphDoesntBreakUploadOrDownload() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_nested_content');

    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit, NULL);

    Paragraph::load(1)->delete();

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    $this->clickLink('Upload');
    $this->checkForMetaRefresh();

    // Check that only the configured fields have been uploaded,
    // but not the missing one.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertUploadedDataFieldCount($data['field_paragraph_container'][0], 0);
    $this->assertEqual($data['title'][0]['value'], 'Llamas are cool');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The manual profile was used.');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Lingotek Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertNoText('Las llamas son muy chulas');
  }

  /**
   * Tests that paragraph references aren't removed on download.
   */
  public function testParagraphedNodeDownloadDoesntChangeReferencesOnSource() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+nestedparagraphs_multiple');

    // Add paragraphed content via API.
    $this->createNestedParagraphedNode();

    $this->drupalGet('node/1/edit');

    $this->goToContentBulkManagementForm();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that only the configured fields have been uploaded,
    // but not the missing one.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEqual($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEqual($data['field_paragraph_container'][0]['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool for the first time');
    $this->assertEqual($data['field_paragraph_container'][0]['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Llamas are very cool for the second time');
    $this->assertEqual($data['field_paragraph_container'][1]['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Dogs are very cool for the first time');
    $this->assertEqual($data['field_paragraph_container'][1]['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Dogs are very cool for the second time');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The manual profile was used.');

    // Request translation.
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => 'request_translation:es-ar',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $this->drupalGet('node/1');
    $this->clickLink('Edit');
    $this->drupalPostForm(NULL, NULL, t('Remove'));
    $this->drupalPostForm(NULL, NULL, t('Confirm removal'));
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = [];
    $edit['field_paragraph_container[1][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]'] = 'Cats are very cool for the second time';
    $edit['field_paragraph_container[1][subform][field_paragraphs_demo][0][_weight]'] = 2;
    $edit['field_paragraph_container[1][subform][field_paragraphs_demo][1][subform][field_text_demo][0][value]'] = 'Cats are very cool for the third time';
    $edit['field_paragraph_container[1][subform][field_paragraphs_demo][1][_weight]'] = 1;
    $edit['field_paragraph_container[1][subform][field_paragraphs_demo][2][subform][field_text_demo][0][value]'] = 'Cats are very cool for the FOURTH time';
    $this->saveAndKeepPublishedNodeForm($edit, 1, FALSE);

    $this->assertNoText('Llamas are very cool for the first time');
    $this->assertNoText('Llamas are very cool for the second time');

    $this->assertNoText('Dogs are very cool for the first time');
    $this->assertNoText('Dogs are very cool for the second time');

    $this->assertNoText('Cats are very cool for the first time');
    $this->assertText('Cats are very cool for the second time');
    $this->assertText('Cats are very cool for the third time');
    $this->assertText('Cats are very cool for the FOURTH time');

    // Download translation.
    $this->goToContentBulkManagementForm();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => 'download:es-ar',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('es_AR', \Drupal::state()->get('lingotek.downloaded_locale'));

    $this->drupalGet('node/1/translations');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');

    if ($this->paragraphsTranslatable) {
      $this->assertText('Las llamas son muy chulas por primera vez');
      $this->assertText('Las llamas son muy chulas por segunda vez');
    }
    else {
      $this->assertNoText('Las llamas son muy chulas por primera vez');
      $this->assertNoText('Las llamas son muy chulas por segunda vez');
      // We show the data that was actually uploaded and translated from the
      // previous revision. The first revision is missing, as it was not
      // translated.
      $this->assertText('Los perros son muy chulos por primera vez');
      $this->assertText('Los perros son muy chulos por segunda vez');
      // That paragraph exists, but was not translated so it's not shown at all.
      $this->assertNoText('Los gatos son muy chulos por primera vez');
      $this->assertNoText('Los gatos son muy chulos por segunda vez');
      $this->assertNoText('Los gatos son muy chulos por tercera vez');
      $this->assertNoText('Los gatos son muy chulos por cuarta vez');
    }

    $this->clickLink('Translate');
    $this->clickLink('Llamas are cool');

    $this->assertText('Llamas are cool');
    $this->assertNoText('Llamas are very cool for the first time');
    $this->assertNoText('Llamas are very cool for the second time');

    $this->assertNoText('Dogs are very cool for the first time');
    $this->assertNoText('Dogs are very cool for the second time');

    $this->assertNoText('Cats are very cool for the first time');
    $this->assertText('Cats are very cool for the FOURTH time');
    $this->assertText('Cats are very cool for the third time');
    $this->assertText('Cats are very cool for the second time');
  }

  public function testEditingAfterNodeWithParagraphsTranslation() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->testNodeWithParagraphsTranslation();

    $this->drupalGet('es-ar/node/1/edit');
    $assert_session->fieldValueEquals('field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]', 'Las llamas son muy chulas');

    $this->drupalGet('node/1/edit');
    $assert_session->fieldValueEquals('field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]', 'Llamas are very cool');

    $this->drupalPostForm(NULL, NULL, t('Remove'));
    $this->drupalPostForm(NULL, NULL, t('Confirm removal'));

    $page->pressButton('Save (this translation)');
    $assert_session->pageTextContains('Llamas are cool has been updated.');
  }

  protected function createNestedParagraphedNode($profile = 'manual') {
    $nestedParagraph1 = Paragraph::create([
      'type' => 'image_text',
      'field_text_demo' => 'Llamas are very cool for the first time',
    ]);
    $nestedParagraph1->save();
    $nestedParagraph2 = Paragraph::create([
      'type' => 'image_text',
      'field_text_demo' => 'Llamas are very cool for the second time',
    ]);
    $nestedParagraph2->save();
    $paragraph1 = Paragraph::create([
      'type' => 'paragraph_container',
      'field_paragraphs_demo' => [$nestedParagraph1, $nestedParagraph2],
    ]);
    $paragraph1->save();

    $nestedParagraph3 = Paragraph::create([
      'type' => 'image_text',
      'field_text_demo' => 'Dogs are very cool for the first time',
    ]);
    $nestedParagraph3->save();
    $nestedParagraph4 = Paragraph::create([
      'type' => 'image_text',
      'field_text_demo' => 'Dogs are very cool for the second time',
    ]);
    $nestedParagraph4->save();
    $paragraph2 = Paragraph::create([
      'type' => 'paragraph_container',
      'field_paragraphs_demo' => [$nestedParagraph3, $nestedParagraph4],
    ]);
    $paragraph2->save();

    $metadata = LingotekContentMetadata::create(['profile' => $profile]);
    $metadata->save();

    $node = Node::create([
      'type' => 'paragraphed_nested_content',
      'title' => 'Llamas are cool',
      'lingotek_metadata' => $metadata,
      'field_paragraph_container' => [$paragraph1, $paragraph2],
      'status' => TRUE,
    ]);
    $node->save();
  }

  protected function setParagraphFieldsTranslatability(): void {
    $edit = [];
    $edit['settings[node][paragraphed_nested_content][fields][field_paragraph_container]'] = 1;
    $edit['settings[paragraph][paragraph_container][fields][field_paragraphs_demo]'] = 1;
    $this->drupalPostForm('/admin/config/regional/content-language', $edit, 'Save configuration');
    $this->assertSession()->responseContains('Settings successfully updated.');
  }

}
