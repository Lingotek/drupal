<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
/**
 * Tests translating a node with multiple locales including paragraphs.
 *
 * @group lingotek
 */
class LingotekNodeParagraphsTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'image', 'comment', 'paragraphs', 'lingotek_paragraphs_test'];

  /**
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Add locales.
    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish',
      'native' => 'Español',
      'direction' => '',
    ];
    $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);

    $post = [
      'code' => 'es_AR',
      'language' => 'Spanish',
      'native' => 'Español',
      'direction' => '',
    ];
    $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'paragraphed_content_demo')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('paragraph', 'image_text')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'paragraphed_content_demo', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->drupalGet('admin/config/regional/content-language');

    $edit = [
      'node[paragraphed_content_demo][enabled]' => 1,
      'node[paragraphed_content_demo][profiles]' => 'automatic',
      'node[paragraphed_content_demo][fields][title]' => 1,
      'node[paragraphed_content_demo][fields][field_paragraphs_demo]' => 1,
      'paragraph[image_text][enabled]' => 1,
      'paragraph[image_text][fields][field_image_demo]' => 1,
      'paragraph[image_text][fields][field_image_demo:properties][title]' => 'title',
      'paragraph[image_text][fields][field_image_demo:properties][alt]' => 'alt',
      'paragraph[image_text][fields][field_text_demo]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithParagraphsTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $test_image = current($this->drupalGetTestFiles('image'));

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
//    $edit['field_metatag[0][basic][description]'] = 'This text will help SEO find my llamas.';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $this->drupalPostForm(NULL, $edit, t('Save and publish'));

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), true);
    $this->verbose(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertEqual($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEqual($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool');

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
    $this->clickLinkHelper(t('Request translation'), 0,  '//a[normalize-space()=:label and contains(@href,\'es_AR\')]');
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLinkByHref('/admin/lingotek/workbench/dummy-document-hash-id-1/es');
    $url = Url::fromRoute('lingotek.workbench', array('doc_id' => 'dummy-document-hash-id-1', 'locale' => 'es_AR'), array('language' => ConfigurableLanguage::load('es-ar')))->toString();
    $this->assertRaw('<a href="' . $url .'" target="_blank" hreflang="es-ar">');
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
    $this->drupalGet('node/add/paragraphed_content_demo');
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for a first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for a second time';

    $this->drupalPostForm(NULL, $edit, t('Save and publish'));

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

    // And paragraphs metadata is there too.
    $this->assertTrue(isset($serialized_node['field_paragraphs_demo'][0]['_lingotek_metadata']), 'The Lingotek metadata is included in the first paragraph.');
    $this->assertEqual('paragraph', $serialized_node['field_paragraphs_demo'][0]['_lingotek_metadata']['_entity_type_id'], 'Entity type id is included as metadata in the first paragraph.');
    $this->assertEqual(1, $serialized_node['field_paragraphs_demo'][0]['_lingotek_metadata']['_entity_id'], 'Entity id is included as metadata in the first paragraph.');
    $this->assertEqual(1, $serialized_node['field_paragraphs_demo'][0]['_lingotek_metadata']['_entity_revision'], 'Entity revision id is included as metadata in the first paragraph.');

    $this->assertTrue(isset($serialized_node['field_paragraphs_demo'][1]['_lingotek_metadata']), 'The Lingotek metadata is included in the second paragraph.');
    $this->assertEqual('paragraph', $serialized_node['field_paragraphs_demo'][1]['_lingotek_metadata']['_entity_type_id'], 'Entity type id is included as metadata in the second paragraph.');
    $this->assertEqual(2, $serialized_node['field_paragraphs_demo'][1]['_lingotek_metadata']['_entity_id'], 'Entity id is included as metadata in the second paragraph.');
    $this->assertEqual(2, $serialized_node['field_paragraphs_demo'][1]['_lingotek_metadata']['_entity_revision'], 'Entity revision id is included as metadata in the second paragraph.');
  }

}
