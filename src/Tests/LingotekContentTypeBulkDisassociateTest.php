<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Tests translating config using the bulk management form.
 *
 * @group lingotek
 */
class LingotekContentTypeBulkDisassociateTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article'
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

  }

  /**
   * Tests that a config entity can be disassociated using the bulk operations on the management page.
   */
  public function testContentTypeDisassociate() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndTranslateContentTypeWithLinks();

    // Mark the first for disassociation.
    $edit = [
      'table[article]' => 'article',
      'operation' => 'disassociate',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');
    \Drupal::entityManager()->getStorage('node_type')->resetCache();
    $entity = \Drupal::entityManager()->getStorage('node_type')->load('article');

    // Assert that no document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(0, count($deleted_docs), 'No document has been deleted remotely because the module is not configured to perform the operation.');

    $this->assertNull($config_translation_service->getDocumentId($entity));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getSourceStatus($entity));

    // We can request again.
    $this->createAndTranslateContentTypeWithLinks();
  }

  /**
   * Tests that a config entity can be disassociated using the bulk operations on the management page.
   */
  public function testContentTypeDisassociateWithRemovalOfRemoteDocument() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Enable remote delete while disassociating.
    $edit = [
      'delete_tms_documents_upon_disassociation' => TRUE,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-preferences-form');

    $this->createAndTranslateContentTypeWithLinks();

    // Mark the first for disassociation.
    $edit = [
      'table[article]' => 'article',
      'operation' => 'disassociate',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');
    \Drupal::entityManager()->getStorage('node_type')->resetCache();
    $entity = \Drupal::entityManager()->getStorage('node_type')->load('article');

    // Assert that the document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(1, count($deleted_docs), 'The document has been deleted remotely.');

    $this->assertNull($config_translation_service->getDocumentId($entity));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getSourceStatus($entity));

    // We can request again.
    $this->createAndTranslateContentTypeWithLinks();
  }

  protected function createAndTranslateContentTypeWithLinks() {
    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    // Clicking English must init the upload of content.
    $this->clickLink('EN');
    $this->assertText(t('Article uploaded successfully'));

    // There is a link for checking status.
    $this->clickLink('EN');
    $this->assertText('Article status checked successfully');

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
