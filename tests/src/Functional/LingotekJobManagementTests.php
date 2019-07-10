<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Lingotek;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\taxonomy\Functional\TaxonomyTestTrait;

/**
 * Tests job management listings.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekJobManagementTests extends LingotekTestBase {

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
    $this->drupalPlaceBlock('page_title_block', ['id' => 'block_1', 'label' => 'Title block', 'region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['id' => 'block_2', 'label' => 'Local tasks block', 'region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->vocabulary = $this->createVocabulary();

    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
      'auto_create' => TRUE,
    ];
    $this->createEntityReferenceField('node', 'article',
      'field_tags', 'Tags', 'taxonomy_term', 'default',
      $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    EntityFormDisplay::load('node.article.default')
      ->setComponent('field_tags', [
        'type' => 'entity_reference_autocomplete_tags',
      ])->save();
    EntityViewDisplay::load('node.article.default')
      ->setComponent('field_tags')->save();

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();
    ConfigurableLanguage::createFromLangcode('es-ar')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
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
            'field_tags' => 1,
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
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+taxonomy_term');
  }

  public function testJobTranslationEmptyTab() {
    $this->drupalGet('admin/lingotek');
    $this->assertLink('Translation Jobs');
    $this->clickLink('Translation Jobs');

    $this->assertText('There are no translation jobs. Use the Content or Config tabs to assign them.');
  }

  public function testJobTranslationTab() {
    $this->createContent();
    $this->drupalGet('admin/lingotek');
    $this->assertLink('Translation Jobs');
    $this->clickLink('Translation Jobs');

    $this->assertText('my-test-job-id-1');
    $this->assertText('3 content items, 1 config items');
    $this->assertLink('View translation job', 0);
    $this->assertLinkByHref('/admin/lingotek/job/my-test-job-id-1');

    $this->assertText('my-test-job-id-2');
    $this->assertText('1 content items, 0 config items');
    $this->assertLink('View translation job', 1);
    $this->assertLinkByHref('/admin/lingotek/job/my-test-job-id-2');
  }

  public function testJobTranslationContentTab() {
    $this->createContent();
    $this->drupalGet('/admin/lingotek/job/my-test-job-id-1');

    // Assert tabs.
    $this->assertLink('Job my-test-job-id-1 Content');
    $this->assertLink('Job my-test-job-id-1 Config');

    // Assert title block heading.
    $this->assertSame('Job my-test-job-id-1 Content', $this->xpath('//h1')[0]->getText());

    // Assert content listed.
    $this->assertLink('Llamas are cool');
    $this->assertSourceStatus('en', Lingotek::STATUS_IMPORTING);
    $this->assertTargetStatus('es', Lingotek::STATUS_REQUEST);
    $this->assertTargetStatus('es-ar', Lingotek::STATUS_REQUEST);
    $this->assertLink('Camelid');
    $this->assertLink('Herbivorous');
    $this->assertNoLink('Awesome');

    // Assert the fields are not there.
    $this->assertNoField('show_advanced');
    $this->assertNoField('job_id');
  }

  public function testJobTranslationConfigTab() {
    $this->createContent();
    $this->drupalGet('/admin/lingotek/job/my-test-job-id-1');

    // Assert tabs.
    $this->assertLink('Job my-test-job-id-1 Content');
    $this->assertLink('Job my-test-job-id-1 Config');

    $this->clickLink('Job my-test-job-id-1 Config');

    // Assert title block heading.
    $this->assertSame('Job my-test-job-id-1 Configuration', $this->xpath('//h1')[0]->getText());

    // Assert config listed.
    $this->assertText('System information');
    $this->assertNoText('Account settings');

    // Assert the fields are not there.
    $this->assertNoField('filters[wrapper][job]');
    $this->assertNoField('job_id');
  }

  public function testJobTranslationContentTabHasOwnFilter() {
    $this->createContent();
    $this->testJobTranslationContentTab();

    // Let's see the differences in the manage content tab.
    $this->drupalGet('/node/1/manage');
    $this->assertLink('Camelid');
    $this->assertLink('Herbivorous');
    $this->assertLink('Awesome');
  }

  public function testJobTranslationConfigTabHasOwnFilter() {
    $this->createContent();
    $this->testJobTranslationConfigTab();

    // Let's see the differences in the regular config tab.
    $this->goToConfigBulkManagementForm();
    $this->assertText('System information');
    $this->assertText('Account settings');
  }

  protected function createContent() {
    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_tags[target_id]'] = implode(',', ['Camelid', 'Herbivorous', 'Awesome']);

    $this->saveAndPublishNodeForm($edit);

    $metadata = LingotekContentMetadata::loadByTargetId('taxonomy_term', 1);
    $metadata->setJobId('my-test-job-id-1');
    $metadata->save();

    $metadata = LingotekContentMetadata::loadByTargetId('taxonomy_term', 2);
    $metadata->setJobId('my-test-job-id-1');
    $metadata->save();

    $metadata = LingotekContentMetadata::loadByTargetId('taxonomy_term', 3);
    $metadata->setJobId('my-test-job-id-2');
    $metadata->save();

    $metadata = LingotekContentMetadata::loadByTargetId('node', 1);
    $metadata->setJobId('my-test-job-id-1');
    $metadata->save();

    $metadata = LingotekConfigMetadata::loadByConfigName('system.site');
    $metadata->setJobId('my-test-job-id-1');
    $metadata->save();

  }

}
