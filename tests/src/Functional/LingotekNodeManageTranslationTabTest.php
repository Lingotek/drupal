<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\taxonomy\Functional\TaxonomyTestTrait;

/**
 * Tests translating a node using the bulk management form.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekNodeManageTranslationTabTest extends LingotekTestBase {

  use TaxonomyTestTrait;
  use EntityReferenceTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'taxonomy'];

  /**
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    $this->vocabulary = $this->createVocabulary();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
      'auto_create' => TRUE,
    ];
    $this->createEntityReferenceField('node', 'article',
      'field_tags', 'Tags', 'taxonomy_term', 'default',
      $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    entity_get_form_display('node', 'article', 'default')
      ->setComponent('field_tags', [
        'type' => 'entity_reference_autocomplete_tags',
      ])->save();
    entity_get_display('node', 'article', 'default')
      ->setComponent('field_tags')->save();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $this->vocabulary->id())->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $this->vocabulary->id(), TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $bundle = $this->vocabulary->id();
    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'body' => 1,
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
  }

  /**
   * Tests that a node can be translated using the links on the management page.
   */
  public function testNodeTranslationUsingLinks() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Check that the manage translations tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $this->assertText('Llamas are cool');
    $this->assertText('Camelid');
    $this->assertText('Herbivorous');

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
    $this->assertLingotekWorkbenchLink('es_MX');
  }

  /**
   * Tests that a node can be translated using the actions on the management page.
   */
  public function testNodeTranslationUsingActions() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_AT')->save();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous']);
    $edit['lingotek_translation_profile'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    // Login as translation manager.
    $this->drupalLogin($this->translationManagerUser);

    // Check that the manage tranlsations tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Manage Translations');

    $this->assertText('Llamas are cool');
    $this->assertText('Camelid');
    $this->assertText('Herbivorous');

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

    // Check status of the German (AT) translation.
    $this->assertLingotekCheckTargetStatusLink('de_AT');
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.checked_target_locale'));

    // Download the German (AT) translation.
    $this->assertLingotekDownloadTargetLink('de_AT');
    $edit = [
      'table[node:1]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslation('de', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('de_AT', \Drupal::state()->get('lingotek.downloaded_locale'));

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('de_AT');
  }

  /**
   * {@inheritdoc}
   *
   * We override this for the destination url.
   */
  protected function getContentBulkManagementFormUrl($entity_type_id = 'node', $prefix = NULL) {
    return ($prefix === NULL ? '' : '/' . $prefix) . '/' . $entity_type_id . '/1/manage';
  }

}
