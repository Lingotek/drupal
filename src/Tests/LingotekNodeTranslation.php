<?php

namespace Drupal\lingotek\Tests;

use Drupal\Core\Language\LanguageManager;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Tests translating a node.
 *
 * @group lingotek
 */
class LingotekNodeTranslation extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node'];

  /**
   * @var NodeInterface
   */
  protected $node;

  protected function setUp() {
    parent::setUp();

    // Add a language.
    $configurableLanguage = new ConfigurableLanguage(array('label' => 'Spanish', 'id' => 'es'), 'configurable_language');
    $configurableLanguage->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);
    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('router.builder')->rebuild();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();

    // Create a test node.
    $this->node = Node::create(['type' => 'article', 'title' => 'Llamas are cool', 'body' => 'Llamas are very cool']);
    $this->node->save();

  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeTranslation() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Check that the translate tab is in the node.
    $this->drupalGet($this->node->url('edit-form'));
    $this->clickLink('Translate');
  }

}