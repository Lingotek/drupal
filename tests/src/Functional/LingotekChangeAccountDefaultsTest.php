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

    $this->assertTableValue('community', 'Test community (test_community)');
    $this->assertTableValue('workflow', 'Test workflow (test_workflow)');
    $this->assertTableValue('project', 'Test project (test_project)');
    $this->assertTableValue('vault', 'Test vault (test_vault)');

    // Click on the Community link.
    $this->clickLink(t('Edit defaults'), 0);
    $this->drupalPostForm(NULL, ['community' => 'test_community2'], t('Save configuration'));

    $this->assertTableValue('community', 'Test community 2 (test_community2)');
    $this->assertTableValue('workflow', 'Test workflow (test_workflow)');
    $this->assertTableValue('project', 'Test project (test_project)');
    $this->assertTableValue('vault', 'Test vault (test_vault)');

    // Click on the Project link.
    $this->clickLink(t('Edit defaults'), 1);
    $this->drupalPostForm(NULL, ['project' => 'test_project2', 'vault' => 'test_vault2'], t('Save configuration'));

    $this->assertTableValue('community', 'Test community 2 (test_community2)');
    $this->assertTableValue('workflow', 'Test workflow (test_workflow)');
    $this->assertTableValue('project', 'Test project 2 (test_project2)');
    $this->assertTableValue('vault', 'Test vault 2 (test_vault2)');

  }

  /**
   * Check to see if two values are equal.
   *
   * @param $field
   *   The field value to check.
   * @param $expected
   *   The expected value to check.
   * @param $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use \Drupal\Component\Utility\SafeMarkup::format() to embed
   *   variables in the message text, not t(). If left blank, a default message
   *   will be displayed.
   *
   * @return
   *   TRUE if the assertion succeeded, FALSE otherwise.
   */
  protected function assertTableValue($field, $expected, $message = '') {
    $xpathValue = $this->xpath('//tr[@data-drupal-selector="edit-account-table-' . $field . '-row"]//td[2]');
    $value = $xpathValue[0]->getText();
    return $this->assertEquals($expected, $value, $message);
  }

}
