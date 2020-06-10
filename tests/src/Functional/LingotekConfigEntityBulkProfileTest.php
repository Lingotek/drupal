<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests changing a profile using the bulk management form.
 *
 * @group lingotek
 */
class LingotekConfigEntityBulkProfileTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node'];

  /**
   * @var \Drupal\node\Entity\NodeInterface
   */
  protected $node;

  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    $this->saveLingotekConfigTranslationSettings([
      'node_type' => 'automatic',
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'content_type');
  }

  /**
   * Tests that the translation profiles can be updated with the bulk actions.
   */
  public function testChangeTranslationProfileBulk() {
    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'system.site');

    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:automatic',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 1, 'Automatic Profile set');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:manual',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there is one node with the Manual Profile
    // Check that there are two nodes with the Automatic Profile
    $manual_profile = $this->xpath("//td[contains(text(), 'Manual')]");
    $this->assertEqual(count($manual_profile), 1, 'Manual Profile set');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:disabled',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there are three nodes with the Disabled Profile
    $disabled_profile = $this->xpath("//td[contains(text(), 'Disabled')]");
    $this->assertEqual(count($disabled_profile), 1, 'Disabled Profile set');

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:automatic',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 1, 'Automatic Profile set');
  }

  /**
   * Tests that the translation profiles can be updated with the bulk actions after
   * cancelling.
   */
  public function testChangeTranslationProfileBulkAfterCancelling() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Go to the bulk config management page.
    $this->goToConfigBulkManagementForm('node_type');

    $basepath = \Drupal::request()->getBasePath();

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $edit = [
      'table[article]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:automatic',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 1, 'Automatic Profile set');
  }

}
