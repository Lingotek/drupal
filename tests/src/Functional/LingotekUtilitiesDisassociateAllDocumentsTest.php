<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests disassociating all site documents.
 *
 * @group lingotek
 */
class LingotekUtilitiesDisassociateAllDocumentsTest extends LingotekTestBase {

  use TaxonomyTestTrait;

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'taxonomy'];

  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $this->vocabulary = $this->createVocabulary();
    $vocabulary_id = $this->vocabulary->id();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Enable translation for the articles and our vocabulary and ensure the
    // change is picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $vocabulary_id)->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $vocabulary_id, TRUE);

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
          ],
        ],
      ],
      'taxonomy_term' => [
        $vocabulary_id => [
          'profiles' => 'manual',
          'fields' => [
            'name' => 1,
            'description' => 1,
          ],
        ],
      ],
    ]);

    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'manual',
    ]);

    $this->translateNodeWithLinks();
    $this->translateTermWithLinks();
    $this->translateSystemSiteConfig();
    $this->translateArticleContentType();
  }

  public function translateNodeWithLinks() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->clickLink('EN');
    // There is a link for checking status.
    $this->clickLink('EN');
    // Request the Spanish translation.
    $this->clickLink('ES');
    // Check status of the Spanish translation.
    $this->clickLink('ES');
    // Download the Spanish translation.
    $this->clickLink('ES');
  }

  public function translateTermWithLinks() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'taxonomy_term');

    $bundle = $this->vocabulary->id();

    // Create a term.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));

    $this->goToContentBulkManagementForm('taxonomy_term');

    // Clicking English must init the upload of content.
    $this->clickLink('EN');
    // There is a link for checking status.
    $this->clickLink('EN');
    // Request the Spanish translation.
    $this->clickLink('ES');
    // Check status of the Spanish translation.
    $this->clickLink('ES');
    // Download the Spanish translation.
    $this->clickLink('ES');
  }

  public function translateSystemSiteConfig() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();
    // Clicking English must init the upload of content.
    $this->clickLink('EN', 1);
    // There is a link for checking status.
    $this->clickLink('EN', 1);
    // Request the Spanish translation.
    $this->clickLink('ES');
    // Check status of the Spanish translation.
    $this->clickLink('ES');
    // Download the Chinese translation.
    $this->clickLink('ES');
  }

  public function translateArticleContentType() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    // Clicking English must init the upload of content.
    $this->clickLink('EN');
    // There is a link for checking status.
    $this->clickLink('EN');
    // Request the Spanish translation.
    $this->clickLink('ES');
    // Check status of the Spanish translation.
    $this->clickLink('ES');
    // Download the Spanish translation.
    $this->clickLink('ES');
  }

  public function testDisassociateAllDocuments() {
    $this->drupalGet('/admin/lingotek/settings');
    $this->drupalPostForm(NULL, [], 'Disassociate');
    $this->assertRaw("Are you sure you want to disassociate everything from Lingotek?");
    $this->drupalPostForm(NULL, [], 'Disassociate');
    $this->assertText('All translations have been disassociated.');

    $node = Node::load(1);
    $term = Term::load(1);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    // Ensure we have disassociated the node.
    $this->assertNull($content_translation_service->getDocumentId($node), 'The node has been disassociated from its Lingotek Document ID');
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($node));

    // Ensure we have disassociated the term.
    $this->assertNull($content_translation_service->getDocumentId($term), 'The term has been disassociated from its Lingotek Document ID');
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($term));

    // Ensure we are disassociated the article type.
    $article_type = \Drupal::entityTypeManager()->getStorage('node_type')->load('article');
    $this->assertNull($config_translation_service->getDocumentId($article_type), 'The article node type has been disassociated from its Lingotek Document ID');
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getSourceStatus($article_type));

    // Ensure we are disassociated the config system.site.
    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')->getMappers();
    $mapper = $mappers['system.site_information_settings'];
    $this->assertNull($config_translation_service->getConfigDocumentId($mapper), 'The system.site config mapper has been disassociated from its Lingotek Document ID');
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getConfigSourceStatus($mapper));

    // Ensure the UIs show the right statuses.
    $this->goToContentBulkManagementForm('node');
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'EN', 1, 'The node shows as untracked');

    $this->goToContentBulkManagementForm('taxonomy_term');
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'EN', 1, 'The taxonomy term shows as untracked');

    $this->goToConfigBulkManagementForm('config');
    // We have 4 configuration objects.
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'EN', 4, 'The configuration shows as untracked');

    $this->goToConfigBulkManagementForm('node_type');
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'EN', 1, 'The article type shows as untracked');
  }

  public function testDisassociateAllDocumentsWithCancelledDocuments() {
    $this->createAndCancelANode();
    // There's one cancelled and one current.
    $this->goToContentBulkManagementForm('node');
    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertSourceStatus('EN', Lingotek::STATUS_CANCELLED);

    $this->createAndCancelATerm();
    // There's one cancelled and one current.
    $this->goToContentBulkManagementForm('taxonomy_term');
    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertSourceStatus('EN', Lingotek::STATUS_CANCELLED);

    $this->createAndCancelANodeType();
    // There's one cancelled and one current.
    $this->goToConfigBulkManagementForm('node_type');
    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertSourceStatus('EN', Lingotek::STATUS_CANCELLED);

    $this->createAndCancelAConfig();
    // There's one cancelled and one current.
    $this->goToConfigBulkManagementForm('node_type');
    $this->assertSourceStatus('EN', Lingotek::STATUS_CURRENT);
    $this->assertSourceStatus('EN', Lingotek::STATUS_CANCELLED);

    // Disassociate.
    $this->drupalGet('/admin/lingotek/settings');
    $this->drupalPostForm(NULL, [], 'Disassociate');
    $this->assertRaw("Are you sure you want to disassociate everything from Lingotek?");
    $this->drupalPostForm(NULL, [], 'Disassociate');
    $this->assertText('All translations have been disassociated.');

    $node = Node::load(1);
    $term = Term::load(1);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    // Ensure we have disassociated the node.
    $this->assertNull($content_translation_service->getDocumentId($node), 'The node has been disassociated from its Lingotek Document ID');
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($node));

    // Ensure we have disassociated the term.
    $this->assertNull($content_translation_service->getDocumentId($term), 'The term has been disassociated from its Lingotek Document ID');
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($term));

    // Ensure we are disassociated the article type.
    $article_type = \Drupal::entityTypeManager()->getStorage('node_type')->load('article');
    $this->assertNull($config_translation_service->getDocumentId($article_type), 'The article node type has been disassociated from its Lingotek Document ID');
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getSourceStatus($article_type));

    // Ensure we are disassociated the config system.site.
    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')->getMappers();
    $mapper = $mappers['system.site_information_settings'];
    $this->assertNull($config_translation_service->getConfigDocumentId($mapper), 'The system.site config mapper has been disassociated from its Lingotek Document ID');
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getConfigSourceStatus($mapper));

    // Ensure the UIs show the right statuses.
    $this->goToContentBulkManagementForm('node');
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'EN', 2, 'The nodes show as untracked');

    $this->goToContentBulkManagementForm('taxonomy_term');
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'EN', 2, 'The taxonomy term shows as untracked');

    $this->goToConfigBulkManagementForm('config');
    // We have 4 configuration objects.
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'EN', 4, 'The configuration shows as untracked');

    $this->goToConfigBulkManagementForm('node_type');
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'EN', 2, 'The article type shows as untracked');
  }

  /**
   * Tests that we can disassociate orphan content.
   *
   * This should never be an allowed status, but let's ensure that we fail
   * gracefully. If a corresponding node doesn't exist anymore, we should just
   * remove the existing metadata.
   */
  public function testDisassociateOrphanContent() {
    // We create manually the given data for setting up an incorrect status.
    $metadata = LingotekContentMetadata::create();
    $metadata->setDocumentId('a_document_id');
    $metadata->setContentEntityTypeId('node');
    $metadata->setContentEntityId(1);
    $metadata->save();

    // Let's try to disassociate then.
    $this->drupalGet('/admin/lingotek/settings');
    $this->drupalPostForm('admin/lingotek/settings', [], 'Disassociate');
    $this->assertRaw("Are you sure you want to disassociate everything from Lingotek?");
    $this->drupalPostForm(NULL, [], 'Disassociate');
    $this->assertText('All translations have been disassociated.');

    // We create manually the given data for setting up an incorrect status.
    $metadata = LingotekContentMetadata::create();
    $metadata->setDocumentId('a_document_id');
    $metadata->setContentEntityTypeId(NULL);
    $metadata->setContentEntityId(NULL);
    $metadata->save();

    // Let's try to disassociate then.
    $this->drupalGet('/admin/lingotek/settings');
    $this->drupalPostForm('admin/lingotek/settings', [], 'Disassociate');
    $this->assertRaw("Are you sure you want to disassociate everything from Lingotek?");
    $this->drupalPostForm(NULL, [], 'Disassociate');
    $this->assertText('All translations have been disassociated.');
  }

  protected function createAndCancelANode() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Dogs are cool';
    $edit['body[0][value]'] = 'Dogs are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    $this->goToContentBulkManagementForm('node');
    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('node'),
    ];
    $this->submitForm($edit, $this->getApplyActionsButtonLabel());
  }

  protected function createAndCancelATerm() {
    $bundle = $this->vocabulary->id();

    // Create a term.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));
    $this->goToContentBulkManagementForm('taxonomy_term');
    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('taxonomy_term'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
  }

  protected function createAndCancelANodeType() {
    // Create a node type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Page',
    ]);

    $this->goToConfigBulkManagementForm('node_type');
    $edit = [
      'table[page]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('node_type'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
  }

  protected function createAndCancelAConfig() {
    $this->goToConfigBulkManagementForm('config');
    // Clicking English must init the upload of content.
    $this->clickLink('EN', 0);
    // There is a link for checking status.
    $this->clickLink('EN', 0);
    $edit = [
      'table[system.site_maintenance_mode]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('config'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
  }

}
