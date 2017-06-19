<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
/**
 * Tests translating a node with multiple locales including metatags.
 *
 * @group lingotek
 */
class LingotekNodeMetatagsTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'image', 'comment', 'metatag'];

  /**
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));
    $this->createMetatagField('field_metatag', 'node', 'article');

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
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'automatic',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      'node[article][fields][field_metatag]' => 1,

    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+metatag');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithMetatagsTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $test_image = current($this->drupalGetTestFiles('image'));

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_metatag[0][basic][description]'] = 'This text will help SEO find my llamas.';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), true);
    $this->verbose(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['field_metatag']));
    $this->assertEqual('This text will help SEO find my llamas.', $data['field_metatag'][0]['description']);

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
    $this->assertLinkByHref('/admin/lingotek/workbench/dummy-document-hash-id/es');
    $url = Url::fromRoute('lingotek.workbench', array('doc_id' => 'dummy-document-hash-id', 'locale' => 'es_AR'), array('language' => ConfigurableLanguage::load('es-ar')))->toString();
    $this->assertRaw('<a href="' . $url .'" target="_blank" hreflang="es-ar">');
    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
    $this->assertRaw('<meta name="description" content="Este texto ayudará al SEO a encontrar mis llamas." />');
    $this->assertRaw('<meta name="title" content="Las llamas son chulas | Drupal" />');
  }

  /**
   * Adds a metatag field.
   *
   * @param string $field_name
   *   The field name.
   * @param $entity_type
   *   The entity type.
   * @param $bundle
   *   The bundle.
   */
  protected function createMetatagField($field_name, $entity_type, $bundle) {
    // Create a field with settings to validate.
    $fieldStorage = entity_create('field_storage_config', array(
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'metatag',
    ));
    $fieldStorage->save();
    $field = entity_create('field_config', array(
      'field_storage' => $fieldStorage,
      'bundle' => $bundle,
    ));
    $field->save();
    entity_get_form_display($entity_type, $bundle, 'default')
      ->setComponent($field_name, array(
        'type' => 'metatag_firehose',
      ))
      ->save();
    entity_get_display($entity_type, $bundle, 'default')
      ->setComponent($field_name, array(
        'type' => 'metatag_empty_formatter',
      ))
      ->save();
  }

}
