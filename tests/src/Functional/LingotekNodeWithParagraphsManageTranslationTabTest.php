<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests translating a node with paragraphs using the bulk management form.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeWithParagraphsManageTranslationTabTest extends LingotekTestBase {

  use EntityReferenceTestTrait;

  protected $paragraphsTranslatable = FALSE;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'image', 'paragraphs', 'lingotek_paragraphs_test'];

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'paragraphed_content_demo')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('paragraph', 'image_text')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'paragraphed_content_demo', TRUE);

    if ($this->paragraphsTranslatable) {
      $this->setParagraphFieldsTranslatability();
    }

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

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
      'paragraph' => [
        'image_text' => [
          'fields' => [
            'field_image_demo' => ['title', 'alt'],
            'field_text_demo' => 1,
          ],
        ],
      ],
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs');
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testNodeTranslationUsingLinks() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit, NULL);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Check that the manage translations tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $assert_session->elementContains('css', 'table#edit-table', 'Llamas are cool');
    // Assert embedded are listed on the embedded table.
    $assert_session->elementNotContains('css', 'table#edit-table', 'Image + Text');
    $assert_session->elementContains('css', 'details#edit-related table', 'Image + Text');

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('es_MX');
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('es_MX');
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_MX', 'dummy-document-hash-id', 'ES');
  }

  /**
   * Tests that a node can be translated using the actions on the management page.
   */
  public function testNodeTranslationUsingActions() {
    $assert_session = $this->assertSession();

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit, NULL);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Check that the manage tranlsations tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $assert_session->elementContains('css', 'table#edit-table', 'Llamas are cool');
    // Assert embedded are listed on the embedded table.
    $assert_session->elementNotContains('css', 'table#edit-table', 'Image + Text');
    $assert_session->elementContains('css', 'details#edit-related table', 'Image + Text');

    // I can init the upload of content.
    $this->assertLingotekUploadLink();
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Request the German (AT) translation.
    $this->assertLingotekRequestTranslationLink('de_AT');
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->assertLingotekCheckTargetStatusLink('de_AT');
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('de_AT');
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('de_AT', 'dummy-document-hash-id', 'DE');
  }

  /**
   * Tests that the paragraphs are listed on the embedded content table.
   */
  public function testParagraphsOnlyVisibleOnEmbeddedTable() {
    $assert_session = $this->assertSession();
    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');

    $this->drupalPostForm(NULL, NULL, t('Add Image + Text'));

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit, NULL);

    // Check that the manage translations tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $assert_session->elementContains('css', 'table#edit-table', 'Llamas are cool');
    // Assert embedded are listed on the embedded table.
    $assert_session->elementNotContains('css', 'table#edit-table', 'Image + Text');
    $assert_session->elementContains('css', 'details#edit-related table', 'Image + Text');
  }

  /**
   * {@inheritdoc}
   *
   * We override this for the destination url.
   */
  protected function getContentBulkManagementFormUrl($entity_type_id = 'node', $prefix = NULL) {
    return ($prefix === NULL ? '' : '/' . $prefix) . '/' . $entity_type_id . '/1/manage';
  }

  protected function setParagraphFieldsTranslatability(): void {
    $edit = [];
    $edit['settings[node][paragraphed_content_demo][fields][field_paragraphs_demo]'] = 1;
    $edit['settings[paragraph][image_text][fields][field_text_demo]'] = 1;
    $this->drupalPostForm('/admin/config/regional/content-language', $edit, 'Save configuration');
    $this->assertSession()->responseContains('Settings successfully updated.');
  }

}
