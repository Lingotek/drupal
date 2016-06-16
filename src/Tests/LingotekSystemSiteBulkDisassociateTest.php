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
class LingotekSystemSiteBulkDisassociateTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  protected function setUp() {
    parent::setUp();

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();
  }


  /**
   * Tests that a config can be translated using the links on the management page.
   */
  public function testSystemSiteDisassociate() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $this->createAndTranslateSystemSiteWithLinks();

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // Mark the first two for disassociation.
    $edit = [
      'table[system.site_information_settings]' => TRUE,  // System information.
      'operation' => 'disassociate'
    ];
    $this->drupalPostForm(NULL, $edit, t('Execute'));

    /** @var LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $mappers = \Drupal::service('plugin.manager.config_translation.mapper')->getMappers();
    $mapper = $mappers['system.site_information_settings'];
    $this->assertNull($config_translation_service->getConfigDocumentId($mapper));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $config_translation_service->getConfigSourceStatus($mapper));

    // We can request again.
    $this->createAndTranslateSystemSiteWithLinks();

  }

  protected function createAndTranslateSystemSiteWithLinks() {
    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm();

    // Clicking English must init the upload of content.
    $this->clickLink('English', 1);
    $this->assertText(t('System information uploaded successfully'));

    // There is a link for checking status.
    $this->clickLink('English', 1);
    $this->assertText('System information status checked successfully');

    // Request the Spanish translation.
    $this->clickLink('ES');
    $this->assertText("Translation to es_ES requested successfully");

    // Check status of the Spanish translation.
    $this->clickLink('ES');
    $this->assertText("Translation to es_ES checked successfully");

    // Download the Spanish translation.
    $this->clickLink('ES');
    $this->assertText('Translation to es_ES downloaded successfully');
  }

}
