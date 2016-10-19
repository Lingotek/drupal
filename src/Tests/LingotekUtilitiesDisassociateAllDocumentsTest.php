<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\VocabularyInterface;
use Drupal\taxonomy\Tests\TaxonomyTestTrait;

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

  protected function setUp() {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));

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
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'manual',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
      "taxonomy_term[$vocabulary_id][enabled]" => 1,
      "taxonomy_term[$vocabulary_id][profiles]" => 'manual',
      "taxonomy_term[$vocabulary_id][fields][name]" => 1,
      "taxonomy_term[$vocabulary_id][fields][description]" => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');

    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'manual',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    $this->translateNodeWithLinks();
    $this->translateTermWithLinks();
    $this->translateSystemSiteConfig();
    $this->translateArticleContentType();
  }

  public function translateNodeWithLinks() {
    // Create a node.
    $edit = array();
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_profile'] = 'manual';
    $this->drupalPostForm('node/add/article', $edit, t('Save and publish'));

    $this->goToContentBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->clickLink('English');
    // There is a link for checking status.
    $this->clickLink('English');
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
    $edit = array();
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));

    $this->goToContentBulkManagementForm('taxonomy_term');

    // Clicking English must init the upload of content.
    $this->clickLink('English');
    // There is a link for checking status.
    $this->clickLink('English');
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
    $this->clickLink('English', 1);
    // There is a link for checking status.
    $this->clickLink('English', 1);
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
    $this->clickLink('English');
    // There is a link for checking status.
    $this->clickLink('English');
    // Request the Spanish translation.
    $this->clickLink('ES');
    // Check status of the Spanish translation.
    $this->clickLink('ES');
    // Download the Spanish translation.
    $this->clickLink('ES');
  }

  public function testDisassociateAllDocuments() {
    $this->drupalGet('/admin/lingotek/settings');
    $this->drupalPostForm('admin/lingotek/settings', [], 'Disassociate');

    $node = Node::load(1);
    $term = Term::load(1);

    /** @var LingotekContentTranslationServiceInterface $content_translation_service */
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
    $article_type = \Drupal::entityManager()->getStorage('node_type')->load('article');
    $this->assertNull($config_translation_service->getDocumentId($article_type), 'The article node type has been disassociated from its Lingotek Document ID');
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getSourceStatus($article_type));

    // Ensure we are disassociated the config system.site.
    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')->getMappers();
    $mapper = $mappers['system.site_information_settings'];
    $this->assertNull($config_translation_service->getConfigDocumentId($mapper), 'The system.site config mapper has been disassociated from its Lingotek Document ID');
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getConfigSourceStatus($mapper));

    // Ensure the UIs show the right statuses.
    $this->goToContentBulkManagementForm('node');
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'English', 1, 'The node shows as untracked');

    $this->goToContentBulkManagementForm('taxonomy_term');
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'English', 1, 'The taxonomy term shows as untracked');

    $this->goToConfigBulkManagementForm('config');
    // We have 4 configuration objects.
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'English', 4, 'The configuration shows as untracked');

    $this->goToConfigBulkManagementForm('node_type');
    $this->assertSourceStatusStateCount(Lingotek::STATUS_UNTRACKED, 'English', 1, 'The article type shows as untracked');
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
    \Drupal::database()->insert('lingotek_content_metadata')
      ->fields(['document_id', 'entity_type', 'entity_id'])
      ->values([
        'document_id' => 'a_document_id',
        'entity_type' => 'node',
        'entity_id' => 1,
      ])->execute();

    // Let's try to disassociate then.
    $this->drupalGet('/admin/lingotek/settings');
    $this->drupalPostForm('admin/lingotek/settings', [], 'Disassociate');
  }

}