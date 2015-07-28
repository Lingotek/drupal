<?php

namespace Drupal\lingotek\Tests;

use Drupal\node\Entity\Node;
use Drupal\simpletest\WebTestBase;

/**
 * Base class for Lingotek test. Performs authorization of the account.
 */
class LingotekTestBase extends WebTestBase {

  /*
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['lingotek', 'lingotek_test'];

  protected function setUp() {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect to Lingotek');
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->drupalPostForm(NULL, ['project' => 'test_project', 'vault' => 'test_vault'], 'Save configuration');

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
    }

    $node = Node::create(['type' => 'article', 'title' => 'Llamas are cool', 'body' => 'Llamas are very cool']);
    $node->save();
  }



}