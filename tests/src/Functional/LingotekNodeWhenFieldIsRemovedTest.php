<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests removing a field that was enabled for translation but it's not anymore.
 *
 * @group lingotek
 */
class LingotekNodeWhenFieldIsRemovedTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    $this->addNewField('node', 'article', 'new_field', 'New field');

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
            'new_field' => 1,
          ],
        ],
      ],
    ]);
  }

  /**
   * Tests that we can translate after the field is removed.
   */
  public function testFieldIsRemoved() {
    $assert_session = $this->assertSession();
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+new_field');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['new_field[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->assertLingotekUploadLink();
    // And we cannot request yet a translation.
    $this->assertNoLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertText('Node Llamas are cool has been uploaded.');
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 3);
    $this->assertTrue(isset($data['title'][0]['value']));
    $this->assertEqual(1, count($data['body'][0]));
    $this->assertTrue(isset($data['body'][0]['value']));
    $this->assertEqual(1, count($data['new_field'][0]));
    $this->assertTrue(isset($data['new_field'][0]['value']));

    // There is a link for checking status.
    $this->assertLingotekCheckSourceStatusLink();
    // And we can already request a translation.
    $this->assertLingotekRequestTranslationLink('es_MX');
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Now we remove the field!
    $this->removeField('node', 'article', 'new_field');

    // The field is not enabled anymore.
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $this->assertFalse($lingotek_config->isFieldLingotekEnabled('node', 'article', 'new_field'), 'The field was disabled from Lingotek when deleted');

    $this->goToContentBulkManagementForm();

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

    // Open the node and it works.
    $this->clickLink('Llamas are cool');
    $this->clickLink('Translate');
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

  /**
   * Adds a new text field.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   * @param string $label
   *   The label.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The field config.
   */
  protected function addNewField($entity_type_id, $bundle, $field_name, $label) {
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type_id,
      'type' => 'text_with_summary',
      'translatable' => TRUE,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
      'settings' => [
        'display_summary' => TRUE,
      ],
    ]);
    $field->save();

    // Assign widget settings for the 'default' form mode.
    EntityFormDisplay::load($entity_type_id . '.' . $bundle . '.' . 'default')
      ->setComponent($field_name, [
        'type' => 'text_textarea_with_summary',
      ])
      ->save();
    EntityViewDisplay::load($entity_type_id . '.' . $bundle . '.' . 'default')
      ->setComponent($field_name, [
        'label' => 'hidden',
        'type' => 'text_default',
      ])
      ->save();

    // The teaser view mode is created by the Standard profile and therefore
    // might not exist.
    $view_modes = \Drupal::service('entity_display.repository')->getViewModes($entity_type_id);
    if (isset($view_modes['teaser'])) {
      EntityViewDisplay::load($entity_type_id . '.' . $bundle . '.' . 'default')
        ->setComponent($field_name, [
          'label' => 'hidden',
          'type' => 'text_summary_or_trimmed',
        ])
        ->save();
    }
    return $field;
  }

  /**
   * Removes a given field using the UI.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param string $bundle
   *   The bundle.
   * @param $field_name
   *   The field name.
   */
  protected function removeField($entity_type_id, $bundle, $field_name) {
    $assert_session = $this->assertSession();
    $this->drupalGet('/admin/structure/types/manage/article/fields');
    $assert_session->linkByHrefExists("/admin/structure/types/manage/$bundle/fields/$entity_type_id.$bundle.$field_name/delete");

    $this->drupalGet("/admin/structure/types/manage/$bundle/fields/$entity_type_id.$bundle.$field_name/delete");
    $this->drupalPostForm(NULL, [], 'Delete');
  }

}
