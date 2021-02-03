<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\link\LinkItemInterface;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node that contains a link field.
 *
 * @group lingotek
 */
class LingotekNodeWithLinkTranslationTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'image', 'comment', 'link', 'dblog'];

  /**
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * @var string
   */
  protected $field_name = 'field_link';

  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Create a link field.
    // Create a field with settings to validate.
    $fieldStorage = \Drupal::entityTypeManager()->getStorage('field_storage_config')->create([
      'field_name' => $this->field_name,
      'entity_type' => 'node',
      'type' => 'link',
    ]);
    $fieldStorage->save();
    $field = \Drupal::entityTypeManager()->getStorage('field_config')->create([
      'field_storage' => $fieldStorage,
      'bundle' => 'article',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_GENERIC,
      ],
    ]);
    $field->save();

    EntityFormDisplay::load('node.article.default')
      ->setComponent($this->field_name, [
        'type' => 'link_default',
      ])
      ->save();
    EntityViewDisplay::load('node.article.default')
      ->setComponent($this->field_name, [
        'type' => 'link',
      ])
      ->save();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();
    ConfigurableLanguage::createFromLangcode('es-ar')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();

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
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
            $this->field_name => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithLinkTranslation() {
    $assert_session = $this->assertSession();
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+link');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit["$this->field_name[0][uri]"] = 'http://drupal.org';
    $edit["$this->field_name[0][title]"] = 'My field link title';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including tags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(2, count($data[$this->field_name][0]));
    $this->assertEqual($data[$this->field_name][0]['title'], 'My field link title');
    $this->assertEqual($data[$this->field_name][0]['uri'], 'http://drupal.org');
    $this->verbose(var_export($data, TRUE));

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
    $this->assertText('Enlace con fotos de llamas');
    $assert_session->linkByHrefExists('http://drupal.org/es');
  }

  /**
   * Tests that a node can be translated when an invalid link is returned.
   */
  public function testNodeWithInvalidLinkTranslation() {
    $assert_session = $this->assertSession();
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+invalidlink');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit["$this->field_name[0][uri]"] = 'http://drupal.org';
    $edit["$this->field_name[0][title]"] = 'My field link title';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded, including tags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(2, count($data[$this->field_name][0]));
    $this->assertEqual($data[$this->field_name][0]['title'], 'My field link title');
    $this->assertEqual($data[$this->field_name][0]['uri'], 'http://drupal.org');
    $this->verbose(var_export($data, TRUE));

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
    $this->assertText('Enlace con fotos de llamas');
    // There is no invalid link.
    $assert_session->linkByHrefNotExists('this is not a valid uri');
    // The original link is kept.
    $assert_session->linkByHrefExists('http://drupal.org');
    // Test the error is logged.
    $status = (bool) \Drupal::database()->queryRange('SELECT 1 FROM {watchdog} WHERE message = :message', 0, 1, [':message' => "Field field_link for node Llamas are cool in language es-ar not saved, invalid uri \"this is not a valid uri\""]);
    $this->assert($status, 'A watchdog message was logged for the invalid uri in a field');
  }

}
