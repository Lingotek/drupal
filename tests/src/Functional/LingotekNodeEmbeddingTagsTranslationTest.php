<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests translating a node with multiple locales embedding another entity.
 *
 * @group lingotek
 */
class LingotekNodeEmbeddingTagsTranslationTest extends LingotekTestBase {

  use TaxonomyTestTrait;
  use EntityReferenceTestTrait;
  use TestFileCreationTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'image', 'comment', 'taxonomy'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['id' => 'block_1', 'label' => 'Title block', 'region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'block_2', 'label' => 'Local tasks block', 'region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->createImageField('field_image', 'article');
    $this->vocabulary = $this->createVocabulary();

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

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();
    ConfigurableLanguage::createFromLangcode('es-ar')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
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
            'field_image' => ['alt'],
            'field_tags' => 1,
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
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+taxonomy_term');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $test_image = current($this->getTestFiles('image'));

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['files[field_image_0]'] = \Drupal::service('file_system')->realpath($test_image->uri);

    $this->drupalPostForm('node/add/article', $edit, t('Preview'));

    unset($edit['files[field_image_0]']);
    $edit['field_image[0][alt]'] = 'Llamas are cool';
    $this->saveAndPublishNodeForm($edit, NULL);

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including tags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 4);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['field_image'][0]));
    $this->assertTrue(isset($data['field_image'][0]['alt']));
    $this->assertEqual(2, count($data['field_tags']));
    $this->assertEqual('Camelid', $data['field_tags'][0]['name'][0]['value']);
    $this->assertEqual('Herbivorous', $data['field_tags'][1]['name'][0]['value']);

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
    $this->assertText('Camélido');
    $this->assertText('Hervíboro');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslationWithADeletedReference() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    $term = Term::load(1);
    $this->assertEqual('Camelid', $term->label());
    $term->delete();

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded, including tags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 4);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    // Only one tag does really exist.
    $this->assertEqual(1, count($data['field_tags']));
    $this->assertEqual('Herbivorous', $data['field_tags'][1]['name'][0]['value']);

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The manual profile was used.');

    // The document should have been imported, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
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
    $this->assertText('Hervíboro');

    $this->drupalGet('/taxonomy/term/2/translations');
    $this->drupalGet('/es-ar/taxonomy/term/2');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslationWithADeletedReferenceInARevision() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+taxonomy_term+metadata');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';

    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded, including tags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 4);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    // Only one tag does really exist.
    $this->assertEqual(2, count($data['field_tags']));
    $this->assertEqual('Camelid', $data['field_tags'][0]['name'][0]['value']);
    $this->assertEqual('Herbivorous', $data['field_tags'][1]['name'][0]['value']);

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The manual profile was used.');

    // Now we create a new revision, and this is removing a field tag reference.
    $this->node = Node::load(1);
    unset($this->node->field_tags[0]);
    $this->node->setNewRevision(TRUE);
    $this->node->save();

    // Check that we removed it correctly.
    $this->drupalGet('node/1');
    $this->assertNoText('Camelid');
    $this->assertText('Herbivorous');

    // We go back to the translations.
    $this->clickLink('Translate');

    // The document should have been imported, so let's check
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

    // The tags are BOTH there. Because we have translated an older revision.
    $this->assertText('Camélido');
    $this->assertText('Hervíboro');
  }

  /**
   * Tests that previous tags are deleted when downloading a new translation.
   */
  public function testNodeTranslationAfterDeletedReference() {
    $this->testNodeTranslationWithADeletedReferenceInARevision();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+taxonomy_term_emptied+metadata');

    // Now we create a new revision, and this is removing both field tag reference.
    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    $this->node = Node::load(1);
    unset($this->node->field_tags[0]);
    $this->node->setNewRevision();
    $this->node->save();

    // Check that we removed the tags correctly.
    $this->drupalGet('node/1');
    $this->assertNoText('Camelid');
    $this->assertNoText('Herbivorous');

    // We go back to the translations.
    $this->clickLink('Translate');

    // And we reupload it.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded, including tags
    // and image even if not set.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 4);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertTrue(isset($data['field_image']));
    $this->assertTrue(isset($data['field_tags']));
    // The tags are emptied.
    $this->assertEmpty($data['field_tags']);
    // The image field is empty.
    $this->assertEmpty($data['field_image']);

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');

    // The tags have been removed from the content when re-downloading.
    $this->assertNoText('Camélido');
    $this->assertNoText('Hervíboro');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslationWithMultipleReferencesToSameContent() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+taxonomy_term+others');

    // Create another tags field.
    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
      'auto_create' => FALSE,
    ];
    $this->createEntityReferenceField('node', 'article',
      'field_other_tags', 'Other Tags', 'taxonomy_term', 'default',
      $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    EntityFormDisplay::load('node.article.default')
      ->setComponent('field_other_tags', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();
    EntityViewDisplay::load('node.article.default')
      ->setComponent('field_other_tags')
      ->save();

    $edit = [
      'node[article][fields][field_other_tags]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], 'lingoteksettings-tab-content-form');

    // Create the terms.
    Term::create(['name' => 'Camelid', 'vid' => $this->vocabulary->id()])->save();
    Term::create(['name' => 'Herbivorous', 'vid' => $this->vocabulary->id()])->save();
    Term::create(['name' => 'Spitting', 'vid' => $this->vocabulary->id()])->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('node/add/article');
    $this->submitForm([], 'Add another item');
    $this->submitForm([], 'Add another item');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['field_other_tags[0][target_id]'] = 'Camelid';
    $edit['field_other_tags[1][target_id]'] = 'Spitting';
    $edit['field_other_tags[2][target_id]'] = 'Herbivorous';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';

    $this->saveAndPublishNodeForm($edit, NULL);

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded, including tags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 5);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    // Two tags exist.
    $this->assertEqual(2, count($data['field_tags']));
    $this->assertEqual('Camelid', $data['field_tags'][0]['name'][0]['value']);
    $this->assertEqual('1', $data['field_tags'][0]['_lingotek_metadata']['_entity_id']);
    $this->assertEqual('Herbivorous', $data['field_tags'][1]['name'][0]['value']);
    $this->assertEqual('2', $data['field_tags'][1]['_lingotek_metadata']['_entity_id']);

    // Also in the other field, but in some cases only the metadata.
    $this->assertEqual(3, count($data['field_other_tags']));

    $this->assertFalse(isset($data['field_other_tags'][0]['name']));
    $this->assertEqual('1', $data['field_other_tags'][0]['_lingotek_metadata']['_entity_id']);

    $this->assertEqual('Spitting', $data['field_other_tags'][1]['name'][0]['value']);
    $this->assertEqual('3', $data['field_other_tags'][1]['_lingotek_metadata']['_entity_id']);

    $this->assertFalse(isset($data['field_other_tags'][2]['name']));
    $this->assertEqual('2', $data['field_other_tags'][2]['_lingotek_metadata']['_entity_id']);

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The manual profile was used.');

    // The document should have been imported, so let's check
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
    $this->assertSession()->pageTextContains('Camélido');
    $this->assertSession()->pageTextContains('Hervíboro');
    $this->assertSession()->pageTextContains('Esputo');

    $this->drupalGet('/taxonomy/term/2/translations');
    $this->drupalGet('/es-ar/taxonomy/term/2');
  }

}
