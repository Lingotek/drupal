<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests translating a taxonomy term.
 *
 * @group lingotek
 */
class LingotekTaxonomyTermTranslationTest extends LingotekTestBase {

  use TaxonomyTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'taxonomy'];

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * The term that should be translated.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $term;

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $this->vocabulary = $this->createVocabulary();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('taxonomy_term', $this->vocabulary->id())->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('taxonomy_term', $this->vocabulary->id(), TRUE);

    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $bundle = $this->vocabulary->id();
    $this->saveLingotekContentTranslationSettings([
      'taxonomy_term' => [
        $bundle => [
          'profiles' => 'automatic',
          'fields' => [
            'name' => 1,
            'description' => 1,
          ],
        ],
      ],
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'taxonomy_term');
  }

  /**
   * Tests that a term can be translated.
   */
  public function testTermTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);
    $bundle = $this->vocabulary->id();

    // Create a term.
    $edit = [];
    $edit['name[0][value]'] = 'Llamas are cool';
    $edit['description[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';

    $this->drupalPostForm("admin/structure/taxonomy/manage/$bundle/add", $edit, t('Save'));

    $this->term = Term::load(1);

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['name'][0]['value']));
    $this->assertEqual(1, count($data['description'][0]));
    $this->assertTrue(isset($data['description'][0]['value']));

    // Check that the translate tab is in the node.
    $this->drupalGet('taxonomy/term/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of taxonomy_term Llamas are cool into es_ES has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

  /**
   * Tests that a term can be translated when created via API with automated upload.
   */
  public function testTermTranslationViaAPIWithAutomatedUpload() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a term.
    $this->term = Term::create([
      'name' => 'Llamas are cool',
      'description' => 'Llamas are very cool',
      'langcode' => 'en',
      'vid' => $this->vocabulary->id(),
    ]);
    $this->term->save();

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['name'][0]['value']));
    $this->assertEqual(1, count($data['description'][0]));
    $this->assertTrue(isset($data['description'][0]['value']));

    // Check that the translate tab is in the node.
    $this->drupalGet('taxonomy/term/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of taxonomy_term Llamas are cool into es_ES has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

  /**
   * Tests that a term can be translated when created via API with automated upload.
   */
  public function testTermTranslationViaAPIWithManualUpload() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $bundle = $this->vocabulary->id();

    $this->saveLingotekContentTranslationSettings([
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

    // Create a term.
    $this->term = Term::create([
      'name' => 'Llamas are cool',
      'description' => 'Llamas are very cool',
      'langcode' => 'en',
      'vid' => $this->vocabulary->id(),
    ]);
    $this->term->save();

    // Check that the translate tab is in the node.
    $this->drupalGet('taxonomy/term/1');
    $this->clickLink('Translate');

    // The document should not have been automatically uploaded, so let's upload it.
    $this->clickLink('Upload');
    $this->checkForMetaRefresh();
    $this->assertText('Uploaded 1 document to Lingotek.');

    // Check that only the configured fields have been uploaded.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertUploadedDataFieldCount($data, 2);
    $this->assertTrue(isset($data['name'][0]['value']));
    $this->assertEqual(1, count($data['description'][0]));
    $this->assertTrue(isset($data['description'][0]['value']));

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('Check Upload Status');
    $this->assertText('The import for taxonomy_term Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('Request translation');
    $this->assertText("Locale 'es_ES' was added as a translation target for taxonomy_term Llamas are cool.");

    // Check translation status.
    $this->clickLink('Check translation status');
    $this->assertText('The es_ES translation for taxonomy_term Llamas are cool is ready for download.');

    // Download translation.
    $this->clickLink('Download completed translation');
    $this->assertText('The translation of taxonomy_term Llamas are cool into es_ES has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $this->assertText('Las llamas son chulas');
    $this->assertText('Las llamas son muy chulas');
  }

}
