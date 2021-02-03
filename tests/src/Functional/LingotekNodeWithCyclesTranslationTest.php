<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node.
 *
 * @group lingotek
 */
class LingotekNodeWithCyclesTranslationTest extends LingotekTestBase {

  use EntityReferenceTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'image'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $this->createEntityReferenceField('node', 'article', 'field_reference', 'Reference', 'node');

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'manual',
          'fields' => [
            'title' => 1,
            'body' => 1,
            'field_reference' => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $node2 = $this->createNode([
      'title' => 'Node 2',
      'type' => 'article',
      'langcode' => 'en',
      'field_reference' => ['target_id' => 1],
    ]);

    $this->node = Node::load(1);
    $this->node->field_reference = $node2->id();
    $this->node->save();

    // Check that the translate tab is in the node.
    $this->clickLink('Translate');

    // Upload our node.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The automatic profile was used.');

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

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'Edit in Lingotek Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

}
