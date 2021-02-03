<?php

namespace Drupal\Tests\lingotek\Functional\Controller;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the workbench redirect controller.
 *
 * @group lingotek
 */
class LingotekWorkbenchRedirectControllerTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
  }

  /**
   * Tests that the workbench link works.
   */
  public function testWorkbenchLink() {
    // We need this helper for setting the host.
    $this->drupalGet(Url::fromRoute('lingotek_test.fake_sethost'));

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $this->saveAndPublishNodeForm($edit);

    // Go to the bulk management form.
    $this->goToContentBulkManagementForm();

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $this->clickLink('EN');
    $this->assertText('The import for node Llamas are cool is complete.');

    // Request translation.
    $this->clickLink('ES');
    $this->assertText("Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $this->clickLink('ES');
    $this->assertText('The es_AR translation for node Llamas are cool is ready for download.');

    // Download the Spanish translation.
    $this->assertLingotekDownloadTargetLink('es_AR');
    $this->clickLink('ES');

    // Now the link is to the workbench, and it opens in a new tab.
    $this->assertLingotekWorkbenchLink('es_AR', 'dummy-document-hash-id', 'ES');

    // Click the workbench tab.
    $this->clickLink('ES');

    $basepath = \Drupal::request()->getSchemeAndHttpHost();
    $this->assertUrl($basepath . '/workbench/document/dummy-document-hash-id/locale/es_AR');
  }

}
