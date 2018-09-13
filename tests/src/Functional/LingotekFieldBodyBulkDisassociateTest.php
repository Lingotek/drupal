<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Tests disassociating a field using the bulk management form.
 *
 * @group lingotek
 */
class LingotekFieldBodyBulkDisassociateTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'field_ui'];

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    node_add_body_field($type);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    $this->drupalGet('admin/lingotek/settings');
    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'automatic',
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-configuration-form');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'body');
  }

  /**
   * Tests that a field config can be disassociated using the bulk operations on the management page.
   */
  public function testFieldDisassociate() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndTranslateFieldWithLinks();

    // Mark the first for disassociation.
    $edit = [
      'table[node.article.body]' => 'node.article.body',
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDisassociate('node_fields'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');
    \Drupal::entityManager()->getStorage('field_config')->resetCache();
    $entity = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');

    // Assert that no document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(0, count($deleted_docs), 'No document has been deleted remotely because the module is not configured to perform the operation.');

    $this->assertNull($config_translation_service->getDocumentId($entity));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getSourceStatus($entity));

    // We can request again.
    $this->createAndTranslateFieldWithLinks();
  }

  /**
   * Tests that a field config can be disassociated using the bulk operations on the management page.
   */
  public function testFieldDisassociateWithRemovalOfRemoteDocument() {
    // Enable remote delete while disassociating.
    $this->drupalGet('admin/lingotek/settings');
    $edit = [
      'delete_tms_documents_upon_disassociation' => TRUE,
    ];
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-preferences-form');

    $this->createAndTranslateFieldWithLinks();

    // Mark the first for disassociation.
    $edit = [
      'table[node.article.body]' => 'node.article.body',
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDisassociate('node_fields'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');
    \Drupal::entityManager()->getStorage('field_config')->resetCache();
    $entity = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');

    // Assert that the document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(1, count($deleted_docs), 'The document has been deleted remotely.');

    $this->assertNull($config_translation_service->getDocumentId($entity));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getSourceStatus($entity));

    // We can request again.
    $this->createAndTranslateFieldWithLinks();
  }

  protected function createAndTranslateFieldWithLinks() {
    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // Clicking English must init the upload of content.
    $this->clickLink('EN');
    $this->assertText(t('Body uploaded successfully'));

    // There is a link for checking status.
    $this->clickLink('EN');
    $this->assertText('Body status checked successfully');

    // Request the Spanish translation.
    $this->clickLink('ES');
    $this->assertText("Translation to es_ES requested successfully");

    // Check status of the Spanish translation.
    $this->clickLink('ES');
    $this->assertText("Translation to es_ES status checked successfully");

    // Download the Spanish translation.
    $this->clickLink('ES');
    $this->assertText('Translation to es_ES downloaded successfully');
  }

}
