<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;

/**
 * Tests cancelling config entities using the bulk management form.
 *
 * @group lingotek
 */
class LingotekContentTypeBulkCancelTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node'];

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

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);
  }

  /**
   * Tests that a config entity can be cancelled using the bulk operations on the management page.
   */
  public function testContentTypeCancel() {
    $assert_session = $this->assertSession();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndTranslateContentTypeWithLinks();

    // Mark the first for cancelling.
    $edit = [
      'table[article]' => 'article',
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');
    \Drupal::entityTypeManager()->getStorage('node_type')->resetCache();

    // Assert that the document has been cancelled remotely.
    $cancelled_docs = \Drupal::state()->get('lingotek.cancelled_docs', []);
    $this->assertEqual(1, count($cancelled_docs), 'The document has been cancelled remotely.');

    // Assert that no document has been deleted remotely.
    $deleted_docs = \Drupal::state()->get('lingotek.deleted_docs', []);
    $this->assertEqual(0, count($deleted_docs), 'No document has been deleted remotely.');

    \Drupal::entityTypeManager()->getStorage('node_type')->resetCache();
    $entity = \Drupal::entityTypeManager()->getStorage('node_type')->load('article');
    $this->assertNull($config_translation_service->getDocumentId($entity));

    $this->assertSourceStatus('EN', Lingotek::STATUS_CANCELLED);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);

    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $config_translation_service->getSourceStatus($entity));
    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $config_translation_service->getTargetStatus($entity, 'es'));

    // We can request again.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/es_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

    $this->createAndTranslateContentTypeWithLinks();
  }

  /**
   * Tests that a config entity target can be cancelled using the bulk operations on the management page.
   */
  public function testContentTypeCancelTarget() {
    $assert_session = $this->assertSession();

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndTranslateContentTypeWithLinks();

    // Mark the first for cancelling.
    $edit = [
      'table[article]' => 'article',
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancelTarget('es', 'node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');
    \Drupal::entityTypeManager()->getStorage('node_type')->resetCache();

    // Assert that the document target has been cancelled remotely.
    $cancelled_locales = \Drupal::state()->get('lingotek.cancelled_locales', []);
    $this->assertTrue(isset($cancelled_locales['dummy-document-hash-id']) && in_array('es_ES', $cancelled_locales['dummy-document-hash-id']),
        'The document target has been cancelled remotely.');

    \Drupal::entityTypeManager()->getStorage('node_type')->resetCache();
    $entity = \Drupal::entityTypeManager()->getStorage('node_type')->load('article');
    $this->assertEquals('dummy-document-hash-id', $config_translation_service->getDocumentId($entity));

    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertTargetStatus('ES', Lingotek::STATUS_CANCELLED);

    $this->assertIdentical(Lingotek::STATUS_CURRENT, $config_translation_service->getSourceStatus($entity));
    $this->assertIdentical(Lingotek::STATUS_CANCELLED, $config_translation_service->getTargetStatus($entity, 'es'));

    // We cannot request again.
    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefNotExists($basepath . '/admin/lingotek/config/request/node_type/article/es_ES?destination=' . $basepath . '/admin/lingotek/config/manage');
  }

  protected function createAndTranslateContentTypeWithLinks() {
    $assert_session = $this->assertSession();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();
    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/upload/node_type/article?destination=' . $basepath . '/admin/lingotek/config/manage');

    // Clicking English must init the upload of content.
    $this->clickLink('EN');
    $this->assertText(t('Article uploaded successfully'));

    // There is a link for checking status.
    $this->clickLink('EN');
    $this->assertText('Article status checked successfully');

    $assert_session->linkByHrefExists($basepath . '/admin/lingotek/config/request/node_type/article/es_ES?destination=' . $basepath . '/admin/lingotek/config/manage');

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
