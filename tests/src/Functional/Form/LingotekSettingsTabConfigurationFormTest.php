<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek configuration settings form.
 *
 * @group lingotek
 */
class LingotekSettingsTabConfigurationFormTest extends LingotekTestBase {

  /**
   * Test that if there are no entities, there is a proper feedback to the user.
   */
  public function testConfigurationForm() {
    $this->drupalGet('admin/lingotek/settings');
    // Nothing is selected.
    $this->assertNoFieldChecked('edit-table-configurable-language-enabled');
    // Check the configurable language and set the manual profile.
    $edit = [
      'table[configurable_language][enabled]' => 1,
      'table[configurable_language][profile]' => 'manual',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save', [], 'lingoteksettings-tab-configuration-form');

    // The values shown are correct.
    $this->assertFieldChecked('edit-table-configurable-language-enabled');
    $this->assertFieldByName('table[configurable_language][profile]', 'manual');

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation */
    $config_translation = \Drupal::service('lingotek.config_translation');
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');

    $this->assertTrue($config_translation->isEnabled('configurable_language'));
    $this->assertEqual('manual', $lingotek_config->getConfigEntityDefaultProfileId('configurable_language'));
    $this->assertEqual(['configurable_language'], $config_translation->getEnabledConfigTypes());
  }

}
