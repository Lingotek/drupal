<?php

namespace Drupal\Tests\lingotek\Functional\Controller;

use Drupal\Core\Url;
use Drupal\frozenintime\FrozenTime;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the workbench redirect controller.
 *
 * @group lingotek
 * @group legacy
 */
class LingotekWorkbenchRedirectControllerTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'node', 'frozenintime'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
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
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
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
    $this->assertLingotekWorkbenchLink('es_AR');
    $expiration = FrozenTime::MY_BIRTHDAY + (60 * 30);

    // Click the workbench tab.
    $this->clickLink('ES');

    $basepath = \Drupal::request()->getSchemeAndHttpHost();
    $data = [
      'document_id' => 'dummy-document-hash-id',
      'locale_code' => 'es_AR',
      'client_id' => 'e39e24c7-6c69-4126-946d-cf8fbff38ef0',
      'login_id' => 'testUser@example.com',
      'acting_login_id' => 'testUser@example.com',
      'expiration' => $expiration,
    ];

    $query_data = utf8_encode(http_build_query($data));
    $hmac = urlencode(base64_encode(hash_hmac('sha1', $query_data, 'test_token', TRUE)));

    $this->assertUrl($basepath . '/lingopoint/portal/wb.action?document_id=dummy-document-hash-id&locale_code=es_AR&client_id=e39e24c7-6c69-4126-946d-cf8fbff38ef0&login_id=testUser%40example.com&acting_login_id=testUser%40example.com&expiration=' . $expiration . '&hmac=' . $hmac);
  }

}
