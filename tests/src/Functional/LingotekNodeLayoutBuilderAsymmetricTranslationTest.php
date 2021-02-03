<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Component\Serialization\Json;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests translating a node with multiple locales including paragraphs.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeLayoutBuilderAsymmetricTranslationTest extends LingotekTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'block_content',
    'node',
    'layout_builder',
    'layout_builder_at',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_tasks_block', [
      'region' => 'content',
      'weight' => -10,
    ]);

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_ES')
      ->save();
    ConfigurableLanguage::createFromLangcode('es-ar')
      ->setThirdPartySetting('lingotek', 'locale', 'es_AR')
      ->save();

    $this->configureLayoutBuilder(['article']);

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

    $edit['settings[node][article][fields][layout_builder__layout]'] = 1;
    $this->drupalPostForm('/admin/config/regional/content-language', $edit, 'Save configuration');
    $this->assertSession()->responseContains('Settings successfully updated.');

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
            'layout_builder__layout' => 1,
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
   * Tests that a node can be translated.
   */
  public function testNodeWithLayoutBuilderATBlockTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+layoutbuilderat');

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('node/add/article');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';

    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Add a block with a custom label.
    $page->clickLink('Layout');
    $assert_session->elementTextContains('css', '.layout-builder__message.layout-builder__message--overrides', 'You are editing the layout for this Article content item. Edit the template for all Article content items instead.');
    $assert_session->linkExists('Edit the template for all Article content items instead.');

    $page->clickLink('Add block');
    $page->clickLink('Powered by Drupal');
    $page->fillField('settings[label]', 'Block title layout builder override');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');

    $page->clickLink('Add block');
    $page->clickLink('Lingotek Test Rich text');
    $page->fillField('settings[label]', 'Rich text label');
    $page->fillField('settings[rich_text][value]', 'Rich text value');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');

    $page->pressButton('Save layout');
    $assert_session->pageTextContains('Block title layout builder override');
    $assert_session->pageTextContains('Rich text label');
    $assert_session->pageTextContains('Rich text value');

    // Get the UUID of the component.
    $components = Node::load(1)->get('layout_builder__layout')->getSection(0)->getComponents();
    $uuids = array_keys($components);
    $uuidArticleLinks = $uuids[0];
    $uuidArticleBody = $uuids[1];
    $uuidArticlePoweredBy = $uuids[2];
    $uuidArticleRichText = $uuids[3];

    // As those uuids are generated, we don't know them in our document fake
    // responses. We need a token replacement system for fixing that.
    \Drupal::state()->set('lingotek.data_replacements', [
      '###UID_LINKS###' => $uuidArticleLinks,
      '###UID_BODY###' => $uuidArticleBody,
      '###UID_POWEREDBY###' => $uuidArticlePoweredBy,
      '###UID_RICHTEXT###' => $uuidArticleRichText,
    ]);

    $this->clickLink('Translate');
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $assert_session->pageTextContains('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded.
    $data = Json::decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'));

    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEquals($data['body'][0]['value'], 'Llamas are very cool');
    $this->assertCount(4, $data['layout_builder__layout']['components']);
    $this->assertEquals($data['layout_builder__layout']['components'][$uuidArticlePoweredBy]['label'], 'Block title layout builder override');
    $this->assertEquals($data['layout_builder__layout']['components'][$uuidArticleRichText]['label'], 'Rich text label');
    $this->assertEquals($data['layout_builder__layout']['components'][$uuidArticleRichText]['rich_text.value'], 'Rich text value');
    // We don't include non translatable properties.
    $this->assertFalse(isset($data['layout_builder__layout']['components'][$uuidArticleRichText]['rich_text.format']));

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()
      ->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $assert_session->pageTextContains('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $assert_session->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $assert_session->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Lingotek Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $assert_session->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $assert_session->pageTextContains('Las llamas son chulas');
    $assert_session->pageTextContains('Las llamas son muy chulas');
    $assert_session->pageTextContains('Título de bloque de layout builder sobreescrito');
    $assert_session->pageTextContains('Título de texto enriquecido');
    $assert_session->pageTextContains('Valor de texto enriquecido');

    // The original content didn't change.
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Llamas are cool');
    $assert_session->pageTextContains('Llamas are very cool');
    $assert_session->pageTextContains('Block title layout builder override');
    $assert_session->pageTextContains('Rich text label');
    $assert_session->pageTextContains('Rich text value');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithLayoutBuilderATCustomBlockTranslation() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+layoutbuilderatcustom');

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('node/add/article');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';

    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Add a block with a custom label.
    $page->clickLink('Layout');
    $assert_session->elementTextContains('css', '.layout-builder__message.layout-builder__message--overrides', 'You are editing the layout for this Article content item. Edit the template for all Article content items instead.');
    $assert_session->linkExists('Edit the template for all Article content items instead.');

    $page->clickLink('Add block');
    $page->clickLink('Create custom block');
    $page->fillField('settings[label]', 'Overridden block with Dogs title');
    $page->fillField('settings[block_form][body][0][value]', 'Block Dogs are very cool');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');

    $page->pressButton('Save layout');
    $assert_session->pageTextContains('Overridden block with Dogs title');
    $assert_session->pageTextContains('Block Dogs are very cool');

    // Get the UUID of the component.
    $components = Node::load(1)->get('layout_builder__layout')->getSection(0)->getComponents();
    $uuids = array_keys($components);
    $uuidArticleLinks = $uuids[0];
    $uuidArticleBody = $uuids[1];
    $uuidArticleContentBlock = $uuids[2];

    // As those uuids are generated, we don't know them in our document fake
    // responses. We need a token replacement system for fixing that.
    \Drupal::state()->set('lingotek.data_replacements', [
      '###UID_LINKS###' => $uuidArticleLinks,
      '###UID_BODY###' => $uuidArticleBody,
      '###UID_CONTENTBLOCK###' => $uuidArticleContentBlock,
    ]);

    $this->clickLink('Translate');
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $assert_session->pageTextContains('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded.
    $data = Json::decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'));

    file_put_contents('/tmp/uploaded-json', \Drupal::state()
      ->get('lingotek.uploaded_content', '[]'));

    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertEquals($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEquals($data['body'][0]['value'], 'Llamas are very cool');
    $this->assertCount(3, $data['layout_builder__layout']['components']);
    $this->assertEquals($data['layout_builder__layout']['components'][$uuidArticleContentBlock]['label'], 'Overridden block with Dogs title');
    $this->assertCount(1, $data['layout_builder__layout']['entities']);
    $this->assertCount(1, $data['layout_builder__layout']['entities']['block_content']);

    $this->assertUploadedDataFieldCount($data['layout_builder__layout']['entities']['block_content'][1], 1);
    $this->assertEquals($data['layout_builder__layout']['entities']['block_content'][1]['body'][0]['value'], 'Block Dogs are very cool');
    $this->assertEquals($data['layout_builder__layout']['entities']['block_content'][1]['_lingotek_metadata']['_entity_type_id'], 'block_content');
    $this->assertEquals($data['layout_builder__layout']['entities']['block_content'][1]['_lingotek_metadata']['_entity_id'], '1');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()
      ->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('manual', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $assert_session->pageTextContains('The import for node Llamas are cool is complete.');

    // Request translation.
    $link = $this->xpath('//a[normalize-space()="Request translation" and contains(@href,"es_AR")]');
    $link[0]->click();
    $assert_session->pageTextContains("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $assert_session->pageTextContains('The es_AR translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'Edit in Lingotek Workbench');

    // Download translation.
    $this->clickLink('Download completed translation');
    $assert_session->pageTextContains('The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $assert_session->pageTextContains('Las llamas son chulas');
    $assert_session->pageTextContains('Las llamas son muy chulas');
    $assert_session->pageTextContains('Bloque sobreescrito con título Perros');
    $assert_session->pageTextContains('Bloque Los perros son muy chulos');

    // The original content didn't change.
    $this->drupalGet('node/1');
    $assert_session->pageTextContains('Llamas are cool');
    $assert_session->pageTextContains('Llamas are very cool');
    $assert_session->pageTextContains('Overridden block with Dogs title');
    $assert_session->pageTextContains('Block Dogs are very cool');
  }

  /**
   * Enable layout builder for the default view mode for the given node types.
   *
   * @param array $nodeTypes
   *   Node types.
   */
  protected function configureLayoutBuilder(array $nodeTypes): void {
    // From the manage display page, go to manage the layout.
    foreach ($nodeTypes as $nodeType) {
      $this->drupalGet("admin/structure/types/manage/$nodeType/display/default");
      $this->drupalPostForm(NULL, ['layout[enabled]' => TRUE], 'Save');
      $this->drupalPostForm(NULL, ['layout[allow_custom]' => TRUE], 'Save');
    }
  }

}
