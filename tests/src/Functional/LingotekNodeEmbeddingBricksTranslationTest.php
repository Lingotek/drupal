<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests translating a node with multiple locales embedding another entity.
 *
 * @group lingotek
 */
class LingotekNodeEmbeddingBricksTranslationTest extends LingotekTestBase {

  use TaxonomyTestTrait;
  use EntityReferenceTestTrait;
  use TestFileCreationTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'field_ui',
    'block',
    'node',
    'image',
    'comment',
    'taxonomy',
    'bricks',
  ];

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
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);
    $this->drupalPlaceBlock('local_actions_block', ['region' => 'content', 'weight' => -12]);

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $this->vocabulary = $this->createVocabulary();

    $this->addBrickField();

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();
    ConfigurableLanguage::createFromLangcode('es-ar')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();

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

    $this->drupalGet('/admin/config/regional/content-language');
    $edit = [
      'settings[node][article][fields][field_brick]' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    $bundle = $this->vocabulary->id();
    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
            'field_brick' => 1,
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
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+brick_taxonomy_term');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslation() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_brick[0][target_id]'] = 'Camelid';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including tags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['field_brick']));
    $this->assertEqual('Camelid', $data['field_brick'][0]['name'][0]['value']);

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
  }

  protected function addBrickField() {
    $this->drupalGet('admin/structure/types/manage/article/fields');
    $this->clickLink('Add field');
    $edit = [
      'new_storage_type' => 'field_ui:bricks:taxonomy_term',
      'label' => 'Brick field',
      'field_name' => 'brick',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save and continue');
    $edit = [
      'cardinality' => -1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save field settings');

    $edit = [
      'settings[handler_settings][auto_create]' => TRUE,
      'settings[handler_settings][target_bundles][' . $this->vocabulary->id() . ']' => TRUE,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save settings');

  }

}
