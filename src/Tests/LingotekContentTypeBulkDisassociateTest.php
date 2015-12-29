<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;

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
    $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    $edit = [
      'table[node_type][enabled]' => 1,
      'table[node_type][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

  }


  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testContentTypeDisassociate() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndTranslateContentTypeWithLinks();

    // Mark the first for disassociation.
    $edit = [
      'table[article]' => 'article',  // Article.
      'operation' => 'disassociate',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');
    \Drupal::entityManager()->getStorage('node_type')->resetCache();
    $entity = \Drupal::entityManager()->getStorage('node_type')->load('article');
    $this->assertNull($config_translation_service->getDocumentId($entity));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getSourceStatus($entity));

    // We can request again.
    $this->createAndTranslateContentTypeWithLinks();

  }

  protected function createAndTranslateContentTypeWithLinks() {
    // Go to the bulk config management page.
    $this->drupalGet('admin/lingotek/config/manage');
    $edit = [
      'filters[wrapper][bundle]' => 'node_type',  // Content types.
    ];
    $this->drupalPostForm(NULL, $edit, t('Filter'));


    // Clicking English must init the upload of content.
    $this->clickLink('English');
    $this->assertText(t('Article uploaded successfully'));

    // There is a link for checking status.
    $this->clickLink('English');
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
