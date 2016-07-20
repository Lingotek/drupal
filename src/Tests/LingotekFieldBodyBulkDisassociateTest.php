<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;

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
    $type = $this->drupalCreateContentType(array(
      'type' => 'article',
      'name' => 'Article'
    ));
    node_add_body_field($type);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    $edit = [
      'table[node_fields][enabled]' => 1,
      'table[node_fields][profile]' => 'automatic',
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-configuration-form');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'body');
  }


  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testFieldDisassociate() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndTranslateFieldWithLinks();

    // Mark the first for disassociation.
    $edit = [
      'table[node.article.body]' => 'node.article.body',  // Article.
      'operation' => 'disassociate',
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');
    \Drupal::entityManager()->getStorage('field_config')->resetCache();
    $entity = \Drupal::entityManager()->getStorage('field_config')->load('node.article.body');
    $this->assertNull($config_translation_service->getDocumentId($entity));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getSourceStatus($entity));

    // We can request again.
    $this->createAndTranslateFieldWithLinks();

  }

  protected function createAndTranslateFieldWithLinks() {
    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_fields');

    // Clicking English must init the upload of content.
    $this->clickLink('English');
    $this->assertText(t('Body uploaded successfully'));

    // There is a link for checking status.
    $this->clickLink('English');
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
