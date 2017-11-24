<?php

namespace Drupal\Tests\lingotek\Functional;

/**
 * Tests changing account defaults.
 *
 * @group lingotek
 */
class LingotekChangeAccountDefaultsTest extends LingotekTestBase {

  public function testChangeCommunity() {
    $this->drupalGet('admin/lingotek/settings');

    $config = $this->config('lingotek.settings');
    $this->assertIdentical('test_community', $config->get('default.community'));
    $this->assertIdentical('test_project', $config->get('default.project'));
    $this->assertIdentical('test_vault', $config->get('default.vault'));

    // Click on the Community link.
    $this->clickLink(t('Edit defaults'), 0);
    $this->drupalPostForm(NULL, ['community' => 'test_community2'], t('Save configuration'));

    $config = $this->config('lingotek.settings');
    $this->assertIdentical('test_community2', $config->get('default.community'));
    $this->assertIdentical('test_project', $config->get('default.project'));
    $this->assertIdentical('test_vault', $config->get('default.vault'));

    // Click on the Project link.
    $this->clickLink(t('Edit defaults'), 1);
    $this->drupalPostForm(NULL, ['project' => 'test_project2', 'vault' => 'test_vault2'], t('Save configuration'));

    $config = $this->config('lingotek.settings');
    $this->assertIdentical('test_community', $config->get('default.community'));
    $this->assertIdentical('test_project2', $config->get('default.project'));
    $this->assertIdentical('test_vault2', $config->get('default.vault'));

  }

}
