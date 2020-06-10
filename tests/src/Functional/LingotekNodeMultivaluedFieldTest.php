<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;

/**
 * Tests translating a node including multivalued fields.
 *
 * @group lingotek
 */
class LingotekNodeMultivaluedFieldTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'image', 'comment'];

  /**
   * The node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'header']);
    $this->drupalPlaceBlock('page_title_block', ['region' => 'header']);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

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

    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'foo',
      'type' => 'text',
      'cardinality' => FieldStorageConfig::CARDINALITY_UNLIMITED,
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'foo',
      'label' => "Foo field",
    ])->save();

    EntityFormDisplay::load('node.article.default')
      ->setComponent('foo', ['type' => 'text_textfield'])->enable()->save();

    EntityViewDisplay::load('node.article.default')
      ->setComponent('foo', ['type' => 'string', 'label' => 'above'])->enable()->save();

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
            'foo' => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests node translation with multivalued fields.
   */
  public function testMultivaluedFields() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('node/add/article');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['foo[0][value]'] = 'Llamas are very cool field 1';
    $edit['foo[1][value]'] = 'Llamas are very cool field 2';
    $edit['foo[2][value]'] = 'Llamas are very cool field 3';
    $edit['langcode[0][value]'] = 'en';

    // Ensure we added the two new values in the form.
    $this->submitForm([], 'Add another item');
    $this->submitForm([], 'Add another item');

    $this->saveAndPublishNodeForm($edit, NULL);

    $this->node = Node::load(1);

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));

    $this->assertIdentical($data['foo'][0]['value'], 'Llamas are very cool field 1');
    $this->assertIdentical($data['foo'][1]['value'], 'Llamas are very cool field 2');
    $this->assertIdentical($data['foo'][2]['value'], 'Llamas are very cool field 3');

    $this->goToContentBulkManagementForm();

    // There is a link for checking status.
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request the Spanish translation.
    $this->clickLink('ES');
    $this->assertText("Locale 'es_MX' was added as a translation target for node Llamas are cool.");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertText('The es_MX translation for node Llamas are cool is ready for download.');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+multivalue0');

    // Download the Spanish translation.
    $this->clickLink('ES');
    $this->assertText('The translation of node Llamas are cool into es_MX has been downloaded.');

    $this->clickLink('Llamas are cool');
    $this->clickLink('Translate');
    $this->clickLink('Las llamas son chulas');

    $this->assertNoText('Las llamas son muy chulas campo 1');
    $this->assertNoText('Las llamas son muy chulas campo 2');
    $this->assertNoText('Las llamas son muy chulas campo 3');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+multivalue1');

    // We re-download, now with different values for the multivalued field.
    $this->goToContentBulkManagementForm();

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    $this->clickLink('Llamas are cool');
    $this->clickLink('Translate');
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas campo 1');
    $this->assertText('Las llamas son muy chulas campo 2');
    $this->assertText('Las llamas son muy chulas campo 3');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+multivalue2');

    // We re-download, now with different values for the multivalued field.
    $this->goToContentBulkManagementForm();

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    $this->clickLink('Llamas are cool');
    $this->clickLink('Translate');
    $this->clickLink('Las llamas son chulas');

    $this->assertText('Las llamas son muy chulas campo 1');
    $this->assertText('Las llamas son muy chulas con distinto campo 2');
    $this->assertNoText('Las llamas son muy chulas campo 3');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+multivalue3');

    // We re-download, now with different values for the multivalued field.
    $this->goToContentBulkManagementForm();

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.downloaded_locale'));

    $this->clickLink('Llamas are cool');
    $this->clickLink('Translate');
    $this->clickLink('Las llamas son chulas');

    $this->assertText('Las llamas son muy chulas campo 1');
    $this->assertText('Las llamas son muy chulas con distinto campo 2');
    $this->assertText('Las llamas son muy chulas con distinto campo 3');
    $this->assertText('Las llamas son muy chulas con distinto campo 4');
    $this->assertText('Las llamas son muy chulas con distinto campo 5');
    $this->assertText('Las llamas son muy chulas con distinto campo 6');
  }

  /**
   * Tests node translation with multivalued fields and quotation marks.
   */
  public function testWithEncodedQuotations() {
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+multivalue+htmlquotes');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('node/add/article');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = '"Llamas are cool"';
    $edit['body[0][value]'] = '"Llamas are very cool"';
    $edit['foo[0][value]'] = '"Llamas are very cool field 1"';
    $edit['foo[1][value]'] = '"Llamas are very cool field 2"';
    $edit['foo[2][value]'] = '"Llamas are very cool field 3"';
    $edit['langcode[0][value]'] = 'en';

    // Ensure we added the two new values in the form.
    $this->submitForm([], 'Add another item');
    $this->submitForm([], 'Add another item');

    $this->saveAndPublishNodeForm($edit, NULL);

    $this->node = Node::load(1);

    // Check that only the translatable fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->verbose(var_export($data, TRUE));

    $this->assertIdentical($data['foo'][0]['value'], '"Llamas are very cool field 1"');
    $this->assertIdentical($data['foo'][1]['value'], '"Llamas are very cool field 2"');
    $this->assertIdentical($data['foo'][2]['value'], '"Llamas are very cool field 3"');

    $this->goToContentBulkManagementForm();

    // There is a link for checking status.
    $this->clickLink('EN');
    $this->assertSession()->pageTextContains("The import for node \"Llamas are cool\" is complete.");

    // Request the Spanish translation.
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains("Locale 'es_MX' was added as a translation target for node \"Llamas are cool\".");
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.added_target_locale'));

    // Check status of the Spanish translation.
    $this->clickLink('ES');
    $this->assertIdentical('es_MX', \Drupal::state()->get('lingotek.checked_target_locale'));
    $this->assertSession()->pageTextContains('The es_MX translation for node "Llamas are cool" is ready for download.');

    // Download the Spanish translation.
    $this->clickLink('ES');
    $this->assertSession()->pageTextContains('The translation of node "Llamas are cool" into es_MX has been downloaded.');

    $this->clickLink('"Llamas are cool"');
    $this->clickLink('Translate');
    $this->clickLink('"Las llamas son chulas"');

    $this->assertNoText('"Las llamas son muy chulas campo 1"');
    $this->assertNoText('"Las llamas son muy chulas campo 2"');
    $this->assertNoText('"Las llamas son muy chulas campo 3"');
  }

}
