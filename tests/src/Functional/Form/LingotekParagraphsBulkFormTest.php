<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the bulk management form integration with Paragraphs.
 *
 * @group lingotek
 */
class LingotekParagraphsBulkFormTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'node',
    'image',
    'paragraphs',
    'lingotek_paragraphs_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_tasks_block', [
      'region' => 'content',
      'weight' => -10,
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'paragraphed_content_demo')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'paragraphed_content_demo', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();

    // We need to rebuild routes.
    $this->rebuildAll();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'paragraphed_content_demo' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'field_paragraphs_demo' => 1,
          ],
        ],
      ],
    ]);
    $this->saveLingotekContentTranslationSettings([
      'paragraph' => [
        'image_text' => [
          'fields' => [
            'field_image_demo' => ['title', 'alt'],
            'field_text_demo' => 1,
          ],
        ],
      ],
    ]);
  }

  public function testParagraphsParentShownOnListing() {
    $assert_session = $this->assertSession();

    $this->addDemoContent();

    $edit = ['contrib[paragraphs][enable_bulk_management]' => 1];
    $this->drupalPostForm(NULL, $edit, 'Save settings', [], 'lingoteksettings-integrations-form');
    $this->assertText('The configuration options have been saved.');

    $this->goToContentBulkManagementForm('paragraph');

    $this->assertText('Parent');
    $assert_session->linkExists('Welcome to the Paragraphs Demo module!', 4);
    $assert_session->linkExists('Library item');
  }

  protected function addDemoContent() {
    // Create three paragraphs to structure the content.
    $paragraph = Paragraph::create([
      'type' => 'image_text',
      'field_text_demo' => [
        'value' => '<h2>Paragraphs is the new way of content creation!</h2>
      <p>It allows you — Site Builders — to make things cleaner so that you can give more editing power to your end-users.
      Instead of putting all their content in one WYSIWYG body field including images and videos, end-users can now choose on-the-fly between pre-defined Paragraph Types independent from one another. Paragraph Types can be anything you want from a simple text block or image to a complex and configurable slideshow.</p>',
        'format' => 'basic_html',
      ],
    ]);
    $paragraph->save();

    $paragraph2 = Paragraph::create([
      'type' => 'image_text',
      'field_text_demo' => [
        'value' => '<p>This demo creates some default Paragraph types from which you can easily create some content (Nested Paragraph, Text, Image + Text, Text + Image, Image and User). It also includes some basic styling and assures that the content is responsive on any device.</p>',
        'format' => 'basic_html',
      ],
    ]);
    $paragraph2->save();

    $paragraph3 = Paragraph::create([
      'type' => 'image_text',
      'field_text_demo' => [
        'value' => '<p>Apart from the included Paragraph types, you can create your own simply by going to Structure -> Paragraphs types.</p>',
        'format' => 'basic_html',
      ],
    ]);
    $paragraph3->save();

    $paragraph4 = Paragraph::create([
      'type' => 'image_text',
      'field_text_demo' => [
        'value' => '<p>A search api example can be found <a href="/paragraphs_search">here</a></p>',
        'format' => 'basic_html',
      ],
    ]);
    $paragraph4->save();

    $paragraph5 = Paragraph::create([
      'type' => 'paragraph_container',
      'field_paragraphs_demo' => [
        $paragraph4,
      ],
    ]);
    $paragraph5->save();

    // Add demo content with four paragraphs.
    $node = Node::create([
      'type' => 'paragraphed_content_demo',
      'title' => 'Welcome to the Paragraphs Demo module!',
      'langcode' => 'en',
      'uid' => '0',
      'status' => 1,
      'field_paragraphs_demo' => [
        $paragraph,
        $paragraph2,
        $paragraph3,
        $paragraph5,
      ],
    ]);
    $node->save();

    $paragraph6 = Paragraph::create([
      'type' => 'image_text',
      'field_text_demo' => [
        'value' => 'This is content from the library. We can reuse it multiple times without duplicating it.',
        'format' => 'plain_text',
      ],
    ]);
    $paragraph6->save();

    $node = Node::create([
      'type' => 'paragraphed_content_demo',
      'title' => 'Library item',
      'field_paragraphs_demo' => [
        $paragraph6,
      ],
    ]);
    $node->save();
  }

}
