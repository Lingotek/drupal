<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node that contains a block field.
 *
 * @group lingotek
 */
class LingotekNodeWithBlockfieldTranslationTest extends LingotekTestBase {

  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'dblog', 'block_content', 'block_field', 'frozenintime'];

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

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $bundle = BlockContentType::create([
      'id' => 'custom_content_block',
      'label' => 'Custom content block',
      'revision' => FALSE,
    ]);
    $bundle->save();

    block_content_add_body_field('custom_content_block');

    $fieldStorage = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->create([
        'field_name' => 'field_block',
        'entity_type' => 'node',
        'type' => 'block_field',
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      ]);
    $fieldStorage->save();
    $field = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_storage' => $fieldStorage,
      'bundle' => 'article',
    ]);
    $field->save();

    EntityFormDisplay::load('node.article.default')
      ->setComponent('field_block', [
        'type' => 'block_field_default',
        'settings' => [
          'configuration_form' => 'full',
        ],
      ])
      ->save();
    EntityViewDisplay::load('node.article.default')
      ->setComponent('field_block', [
        'type' => 'block_field',
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

    ContentLanguageSettings::loadByEntityTypeBundle('block_content', 'custom_content_block')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('block_content', 'custom_content_block', TRUE);

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
            'field_block' => 1,
          ],
        ],
      ],
      'block_content' => [
        'custom_content_block' => [
          'profiles' => 'manual',
          'fields' => [
            'body' => 1,
          ],
        ],
      ],

    ]);
  }

  /**
   * Tests that a node can be translated referencing a standard block.
   */
  public function testNodeWithConfigBlockTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+blockfield');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['field_block[0][plugin_id]'] = 'current_theme_block';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $edit['langcode[0][value]'] = 'en';

    // Because we cannot do ajax requests in this test, we submit and edit later.
    $this->saveAndPublishNodeForm($edit);

    // Ensure it has the expected timestamp for updated and upload
    foreach (LingotekConfigMetadata::loadMultiple() as $metadata) {
      $this->assertEmpty($metadata->getLastUpdated());
      $this->assertEmpty($metadata->getLastUploaded());
    }

    $edit['field_block[0][settings][label_display]'] = TRUE;
    $edit['field_block[0][settings][label]'] = 'Current theme overridden title block';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->assertText('Current theme overridden title block');
    $this->assertText('Current theme: stark');

    // Ensure it has the expected timestamp for updated and upload
    $timestamp = \Drupal::time()->getRequestTime();
    foreach (LingotekConfigMetadata::loadMultiple() as $metadata) {
      $this->assertEmpty($metadata->getLastUpdated());
      $this->assertEquals($timestamp, $metadata->getLastUploaded());
    }

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including field
    // block settings stored in the field.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['field_block'][0]));
    $this->assertEqual($data['field_block'][0]['label'], 'Current theme overridden title block');

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
    $this->assertText('Tema actual titulo sobreescrito del bloque');
    $this->assertText('Current theme: stark');
  }

  /**
   * Tests that a node can be translated referencing a standard block.
   */
  public function testNodeWithCustomConfigBlockTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+blockfieldcustom');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['field_block[0][plugin_id]'] = 'lingotek_test_rich_text_block';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $edit['langcode[0][value]'] = 'en';

    // Because we cannot do ajax requests in this test, we submit and edit later.
    $this->saveAndPublishNodeForm($edit);

    $edit['field_block[0][settings][label_display]'] = TRUE;
    $edit['field_block[0][settings][label]'] = 'Custom block title';
    $edit['field_block[0][settings][rich_text][value]'] = 'Custom block body';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->assertText('Custom block title');
    $this->assertText('Custom block body');

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including field
    // block settings stored in the field.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(2, count($data['field_block'][0]));
    $this->assertEqual($data['field_block'][0]['label'], 'Custom block title');
    $this->assertEqual($data['field_block'][0]['rich_text.value'], 'Custom block body');

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
    $this->assertText('Título de bloque personalizado');
    $this->assertText('Cuerpo de bloque personalizado');

    // The original content didn't change.
    $this->drupalGet('node/1');
    $this->assertText('Llamas are cool');
    $this->assertText('Llamas are very cool');
    $this->assertText('Custom block title');
    $this->assertText('Custom block body');
  }

  /**
   * Tests that a node can be translated referencing a content block.
   */
  public function testNodeWithContentBlockTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+contentblockfield');

    // Create a block.
    $edit = [];
    $edit['info[0][value]'] = 'Dogs block';
    $edit['body[0][value]'] = 'Dogs are very cool block';
    $this->drupalPostForm('block/add/custom_content_block', $edit, t('Save'));

    $dogsBlock = BlockContent::load(1);

    $edit = [];
    $edit['info[0][value]'] = 'Cats block';
    $edit['body[0][value]'] = 'Cats are very cool block';
    $this->drupalPostForm('block/add/custom_content_block', $edit, t('Save'));

    $catsBlock = BlockContent::load(2);

    // Create a node.
    $this->drupalGet('node/add/article');
    $this->drupalPostForm(NULL, [], 'Add another item');
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['field_block[0][plugin_id]'] = 'block_content:' . $dogsBlock->uuid();
    $edit['field_block[1][plugin_id]'] = 'block_content:' . $catsBlock->uuid();
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $edit['langcode[0][value]'] = 'en';

    // Because we cannot do ajax requests in this test, we submit and edit later.
    $this->saveAndPublishNodeForm($edit, NULL);

    $edit['field_block[0][settings][label_display]'] = TRUE;
    $edit['field_block[0][settings][label]'] = 'Dogs overridden title block';
    $edit['field_block[1][settings][label_display]'] = TRUE;
    $edit['field_block[1][settings][label]'] = 'Cats overridden title block';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    $this->assertText('Dogs overridden title block');
    $this->assertText('Dogs are very cool block');
    $this->assertText('Cats overridden title block');
    $this->assertText('Cats are very cool block');

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including field
    // block settings stored in the field.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(2, count($data['field_block']));
    $this->assertEqual(3, count($data['field_block'][0]));
    $this->assertEqual(3, count($data['field_block'][1]));
    $this->assertEqual($data['field_block'][0]['label'], 'Dogs overridden title block');
    $this->assertEqual($data['field_block'][0]['info'], '');
    $this->assertTrue(isset($data['field_block'][0]['entity']));
    $this->assertEqual($data['field_block'][0]['entity']['body'][0]['value'], 'Dogs are very cool block');
    $this->assertEqual($data['field_block'][0]['entity']['_lingotek_metadata']['_entity_type_id'], 'block_content');
    $this->assertEqual($data['field_block'][0]['entity']['_lingotek_metadata']['_entity_id'], '1');
    $this->assertEqual($data['field_block'][1]['label'], 'Cats overridden title block');
    $this->assertEqual($data['field_block'][1]['info'], '');
    $this->assertTrue(isset($data['field_block'][1]['entity']));
    $this->assertEqual($data['field_block'][1]['entity']['body'][0]['value'], 'Cats are very cool block');
    $this->assertEqual($data['field_block'][1]['entity']['_lingotek_metadata']['_entity_type_id'], 'block_content');
    $this->assertEqual($data['field_block'][1]['entity']['_lingotek_metadata']['_entity_id'], '2');

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
    $this->assertText('Bloque sobreescrito con título Perros');
    $this->assertText('Bloque Los perros son muy chulos');
    $this->assertText('Bloque sobreescrito con título Gatos');
    $this->assertText('Bloque Los gatos son muy chulos');
  }

}
