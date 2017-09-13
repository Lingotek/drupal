<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node.
 *
 * @group lingotek
 */
class LingotekNodeTranslationTest extends LingotekTestBase {

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

  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'article',
        'name' => 'Article'
      ]);
    }
    $this->createImageField('field_image', 'article');

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

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
      'node[article][fields][field_image]' => 1,
      'node[article][fields][field_image:properties][alt]' => 'alt',
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
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['files[field_image_0]'] = drupal_realpath($test_image->uri);

    $this->drupalPostForm('node/add/article', $edit, t('Preview'));

    unset($edit['files[field_image_0]']);
    $edit['field_image[0][alt]'] = 'Llamas are cool';
    $this->saveAndPublishNodeForm($edit, NULL);

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['field_image'][0]));
    $this->assertTrue(isset($data['field_image'][0]['alt']));
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

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

    // Assert the link keeps the language.
    $this->assertLinkByHref('/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX');
    $this->assertNoLinkByHref('/es/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.added_target_locale'));

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Check that the Edit link points to the workbench and it is opened in a new tab.
    $this->assertLinkToWorkbenchInNewTabInSinglePage('dummy-document-hash-id', 'es', 'es_MX');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.downloaded_locale'));

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

  /**
   * Tests that a node can be translated after edited.
   */
  public function testEditedNodeTranslation() {
    // We need a node with translations first.
    $this->testNodeTranslation();

    // Add a language so we can check that it's not marked as dirty if there are
    // no translations.
    ConfigurableLanguage::createFromLangcode('eu')->setThirdPartySetting('lingotek', 'locale', 'eu_ES')->save();

    // Edit the node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $edit['body[0][value]'] = 'Llamas are very cool EDITED';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndKeepPublishedThisTranslationNodeForm($edit, 1);

    $this->clickLink('Translate');

    // Check the status is not edited for Vasque, but available to request
    // translation.
    $this->assertLinkByHref('admin/lingotek/entity/add_target/dummy-document-hash-id/eu_ES');
    $this->clickLink('Request translation', 1);
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool EDITED.");

    // Recheck status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_MX translation for node Llamas are cool EDITED is ready for download.');

    // Download the translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of node Llamas are cool EDITED into es_MX has been downloaded.');
  }

  /**
   * Test that when a node is uploaded in a different locale that locale is used.
   */
  public function testAddingContentInDifferentLocale() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool es-MX';
    $edit['body[0][value]'] = 'Llamas are very cool es-MX';
    $edit['langcode[0][value]'] = 'es';
    $this->saveAndPublishNodeForm($edit);

    $this->assertText('Llamas are cool es-MX sent to Lingotek successfully.');
    $this->assertIdentical('es_MX', \Drupal::state()
      ->get('lingotek.uploaded_locale'));
  }

  /**
   * Test that when a node is created we cannot assign a profile if using a restricted user.
   */
  public function testCannotAssignProfileToContentWithoutRightPermission() {
    $editor = $this->drupalCreateUser(['bypass node access']);
    // Login as editor.
    $this->drupalLogin($editor);
    // Get the node form.
    $this->drupalGet('node/add/article');
    // Assert translation profile cannot be assigned.
    $this->assertNoField('lingotek_translation_profile');

    $translation_manager = $this->drupalCreateUser([
      'bypass node access',
      'assign lingotek translation profiles'
    ]);
    // Login as translation manager.
    $this->drupalLogin($translation_manager);
    // Get the node form.
    $this->drupalGet('node/add/article');
    // Assert translation profile can be assigned.
    $this->assertField('lingotek_translation_profile');
  }

  /**
   * Tests that no translation can be requested if the language is disabled.
   */
  public function testLanguageDisabled() {
    // Add a language.
    $italian = ConfigurableLanguage::createFromLangcode('it')
      ->setThirdPartySetting('lingotek', 'locale', 'it_IT');
    $italian->save();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->saveAndPublishNodeForm($edit);

    $this->node = Node::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()
      ->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertIdentical('en_US', \Drupal::state()
      ->get('lingotek.uploaded_locale'));

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

    // There are two links for requesting translations, or we can add them
    // manually.
    $this->assertLinkByHref('/admin/lingotek/entity/add_target/dummy-document-hash-id/it_IT');
    $this->assertLinkByHref('/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX');
    $this->assertLinkByHref('/node/1/translations/add/en/it');
    $this->assertLinkByHref('/node/1/translations/add/en/es');

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $lingotek_config->disableLanguage($italian);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1/translations');

    // Italian is not present anymore, but still can add a translation.
    $this->assertNoLinkByHref('/admin/lingotek/entity/add_target/dummy-document-hash-id/it_IT');
    $this->assertLinkByHref('/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX');
    $this->assertLinkByHref('/node/1/translations/add/en/it');
    $this->assertLinkByHref('/node/1/translations/add/en/es');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnError() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document, which must fail.
    $this->clickLink('Upload');
    $this->assertText('The upload for node Llamas are cool failed. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->assertText('Uploaded 1 document to Lingotek.');
  }

  /**
   * Test that we handle errors in update.
   */
  public function testUpdatingWithAnError() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // Upload the document, which must succeed.
    $this->clickLink('Upload');
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that the upload succeeded.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    // Go back to the form.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Re-upload. Must fail now.
    $this->clickLink('Upload');
    $this->assertText('The update for node Llamas are cool EDITED failed. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');

    // I can still re-try the upload.
    \Drupal::state()->set('lingotek.must_error_in_upload', FALSE);
    $this->clickLink('Upload');
    $this->assertText('Uploaded 1 document to Lingotek.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUploadingWithAnErrorViaAPI() {
    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    // The document was uploaded automatically and failed.
    $this->assertText('The upload for node Llamas are cool failed. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');
  }

  /**
   * Test that we handle errors in upload.
   */
  public function testUpdatingWithAnErrorViaAPI() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    \Drupal::state()->set('lingotek.must_error_in_upload', TRUE);

    // Edit the node.
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $this->saveAndKeepPublishedNodeForm($edit, 1);

    // The document was updated automatically and failed.
    $this->assertText('The update for node Llamas are cool EDITED failed. Please try again.');

    // The node has been marked with the error status.
    $this->node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $source_status = $translation_service->getSourceStatus($this->node);
    $this->assertEqual(Lingotek::STATUS_ERROR, $source_status, 'The node has been marked as error.');
  }

}
