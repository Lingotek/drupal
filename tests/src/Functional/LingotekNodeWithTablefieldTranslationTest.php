<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node that contains a block field.
 *
 * @group lingotek
 * @requires module tablefield
 */
class LingotekNodeWithTablefieldTranslationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'dblog', 'tablefield'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->markTestSkipped('Requires tablefield and that has not a D9 compatible version.');

    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $fieldStorage = \Drupal::entityTypeManager()->getStorage('field_storage_config')->create([
      'field_name' => 'field_table',
      'entity_type' => 'node',
      'type' => 'tablefield',
      'settings' => [],
      'cardinality' => 1,
    ]);
    $fieldStorage->save();
    $field_config = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_name' => 'field_table',
      'label' => 'Table',
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => FALSE,
      'settings' => [],
    ]);
    $field_config->save();

    EntityFormDisplay::load('node.article.default')
      ->setComponent('field_table', [
        'type' => 'tablefield',
        'settings' => [],
      ])
      ->save();
    EntityViewDisplay::load('node.article.default')
      ->setComponent('field_table', [
        'type' => 'tablefield',
      ])
      ->save();

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_ES')
      ->save();
    ConfigurableLanguage::createFromLangcode('es-ar')
      ->setThirdPartySetting('lingotek', 'locale', 'es_AR')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
            'field_table' => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that a node can be translated referencing a standard block.
   */
  public function testNodeWithTablefieldTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+tablefield');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['field_table[0][caption]'] = 'Table caption';
    $edit['field_table[0][tablefield][table][0][0]'] = 'Header 1';
    $edit['field_table[0][tablefield][table][0][1]'] = 'Header 2';
    $edit['field_table[0][tablefield][table][0][2]'] = 'Header 3';
    $edit['field_table[0][tablefield][table][1][0]'] = 'Row 1-1';
    $edit['field_table[0][tablefield][table][1][1]'] = 'Row 1-2';
    $edit['field_table[0][tablefield][table][1][2]'] = 'Row 1-3';
    $edit['field_table[0][tablefield][table][2][0]'] = 'Row 2-1';
    $edit['field_table[0][tablefield][table][2][1]'] = 'Row 2-2';
    $edit['field_table[0][tablefield][table][2][2]'] = 'Row 2-3';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $edit['langcode[0][value]'] = 'en';

    // Because we cannot do ajax requests in this test, we submit and edit later.
    $this->saveAndPublishNodeForm($edit);

    $this->assertText('Llamas are cool sent to Lingotek successfully.');

    $this->assertText('Table caption');
    $this->assertText('Header 1');
    $this->assertText('Header 2');
    $this->assertText('Header 3');
    $this->assertText('Row 1-1');
    $this->assertText('Row 1-2');
    $this->assertText('Row 1-3');
    $this->assertText('Row 2-1');
    $this->assertText('Row 2-2');
    $this->assertText('Row 2-3');

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including field
    // block settings stored in the field.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(6, count($data['field_table'][0]));
    $this->assertEqual($data['field_table'][0]['caption'], 'Table caption');

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
    $this->assertLingotekWorkbenchLink('es_AR');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
    $this->assertText('Texto de la leyenda de la tabla');
    $this->assertText('Cabecera 1');
    $this->assertText('Cabecera 2');
    $this->assertText('Cabecera 3');
    $this->assertText('Texto celda 1 1');
    $this->assertText('Texto celda 1 2');
    $this->assertText('Texto celda 1 3');
    $this->assertText('Texto celda 2 1');
    $this->assertText('Texto celda 2 2');
    $this->assertText('Texto celda 2 3');
  }

}
