<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests the hooks a node.
 *
 * @group lingotek
 */
class LingotekContentTranslationPreSaveHookTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'image'];

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
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array(
        'type' => 'press_release',
        'name' => 'Press Release'
      ));
    }
    $this->createImageField('field_image', 'press_release');

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'press_release')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'press_release', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'node[press_release][enabled]' => 1,
      'node[press_release][profiles]' => 'automatic',
      'node[press_release][fields][title]' => 1,
      'node[press_release][fields][body]' => 1,
      'node[press_release][fields][field_image]' => 1,
      'node[press_release][fields][field_image:properties][alt]' => 'alt',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $test_image = current($this->drupalGetTestFiles('image'));

    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['files[field_image_0]'] = drupal_realpath($test_image->uri);

    $this->drupalPostForm('node/add/press_release', $edit, t('Preview'));

    unset($edit['files[field_image_0]']);
    $edit['field_image[0][alt]'] = 'Llamas are cool';
    $this->drupalPostForm(NULL, $edit, t('Save and publish'));

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), true);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['field_image'][0]));
    $this->assertTrue(isset($data['field_image'][0]['alt']));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

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
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // No translation is unpublished yet.
    $this->assertNoText('Not published');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLinkByHref('/admin/lingotek/workbench/dummy-document-hash-id/es');
    $url = Url::fromRoute('lingotek.workbench', array('doc_id' => 'dummy-document-hash-id', 'locale' => 'es_MX'), array('language' => ConfigurableLanguage::load('es')))->toString();
    $this->assertRaw('<a href="' . $url .'" target="_blank" hreflang="es">');
    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // This translation has been downloaded as unpublished.
    $this->assertText('Not published');

    // The content is translated and unpublished.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
    $this->assertRaw('node--unpublished');
  }

}