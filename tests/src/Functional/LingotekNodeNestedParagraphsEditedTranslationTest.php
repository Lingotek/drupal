<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests translating a node with multiple locales including nested paragraphs.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeNestedParagraphsEditedTranslationTest extends LingotekTestBase {

  protected $paragraphsTranslatable = FALSE;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'image', 'comment', 'content_moderation', 'paragraphs', 'lingotek_paragraphs_test'];

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

  public function testNodeMarkedAsEditedIfParagraphEdited() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+nestedparagraphs_multiple');

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_nested_content');

    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));
    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $this->createNestedParagraphedNode('manual');

    $this->node = Node::load(1);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();

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
    $this->assertIdentical('manual', $used_profile, 'The automatic profile was used.');

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
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][0][subform][field_text_demo][0][value]'] = 'Cats are very cool for the first time';
    $edit['field_paragraph_container[0][subform][field_paragraphs_demo][1][subform][field_text_demo][0][value]'] = 'Cats are very cool for the second time';

    $this->saveAndKeepPublishedNodeForm($edit, 1, FALSE);

    $this->assertText('Paragraphed nested content Llamas are cool has been updated.');
    $this->assertText('Cats are very cool for the first time');
    $this->assertText('Cats are very cool for the second time');
    $this->assertText('Dogs are very cool for the first time');
    $this->assertText('Dogs are very cool for the second time');

    $this->goToContentBulkManagementForm();

    $this->assertSourceStatus('EN', Lingotek::STATUS_EDITED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('ES-AR', Lingotek::STATUS_READY);
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
