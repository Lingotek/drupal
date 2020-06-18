<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ContentLanguageSettings;

/**
 * Tests toolbar links with Lingotek module enabled.
 *
 * @group lingotek
 */
class LingotekToolbarIntegrationTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   *
   * Use 'classy' here, as we depend on that for querying the nav structure.
   *
   * @see testProfileSettingsOverride()
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'lingotek',
    'lingotek_test',
    'node',
    'toolbar',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
  }

  public function testToolbarItems() {
    $assert_session = $this->assertSession();

    // Login as translations manager.
    $this->drupalLogin($this->rootUser);
    $basepath = \Drupal::request()->getBasePath();

    // Ensure we have a first-level item in the toolbar.
    $assert_session->linkExists('Translation');

    // Let's navigate through configuration.
    $this->clickLink('Configuration');

    // And there is a link for Lingotek translation in the Regional and Language section.
    $result = $this->xpath('//h3[text()="Regional and language"]/following-sibling::*//a[@href="' . $basepath . '/admin/lingotek"]/text()');
    $this->assertEqual(1, count($result), 'There is a link in Regional Language to the dashboard.');

    $this->clickLink('Lingotek Translation');

    // Assert there are tabs.
    $result = $this->xpath('//nav[contains(@class,"tabs")]/ul[contains(@class,"primary")]/li[contains(@class,"is-active")]/a[@href="' . $basepath . '/admin/lingotek"]/text()');
    $this->assertEqual(1, count($result), 'There is an active tab for the Dashboard.');
    $result = $this->xpath('//nav[contains(@class,"tabs")]/ul[contains(@class,"primary")]/li/a[@href="' . $basepath . '/admin/lingotek/manage"]/text()');
    $this->assertEqual(1, count($result), 'There is a tab for Content Bulk management.');
    $result = $this->xpath('//nav[contains(@class,"tabs")]/ul[contains(@class,"primary")]/li/a[@href="' . $basepath . '/admin/lingotek/config/manage"]/text()');
    $this->assertEqual(1, count($result), 'There is a tab for Config Bulk management.');
    $result = $this->xpath('//nav[contains(@class,"tabs")]/ul[contains(@class,"primary")]/li/a[@href="' . $basepath . '/admin/lingotek/settings"]/text()');
    $this->assertEqual(1, count($result), 'There is a tab for Settings.');

    $settings = $this->getDrupalSettings();
    // The toolbar module defines a route '/toolbar/subtrees/{hash}' that
    // returns JSON for the rendered subtrees. This hash is provided to the
    // client in drupalSettings.
    $response = $this->drupalGet('/toolbar/subtrees/' . $settings['toolbar']['subtreesHash']);
    $this->assertSession()->statusCodeEquals('200');
    $response = json_decode($response, TRUE);
    $this->assertEqual($response[0]['command'], 'setToolbarSubtrees', 'Subtrees response uses the correct command.');
    $this->assertTrue(array_key_exists('lingotek-config-dashboard', $response[0]['subtrees']), 'There is a subtree for Lingotek config.');

    $html = $response[0]['subtrees']['lingotek-config-dashboard'];
    // Assert there are links in the toolbar menu.
    $this->assertTrue(FALSE !== strpos($html, '<a href="' . $basepath . '/admin/lingotek/manage" title="Manage content translations using Lingotek cloud-based localization" id="toolbar-link-lingotek-manage" class="toolbar-icon toolbar-icon-lingotek-manage" data-drupal-link-system-path="admin/lingotek/manage">Content</a>'),
      'There is an expanded item for the Content bulk management.');
    $this->assertTrue(FALSE !== strpos($html, '<a href="' . $basepath . '/admin/lingotek/manage/node" id="toolbar-link-lingotek-manage-node" class="toolbar-icon toolbar-icon-lingotek-manage-content:lingotek-manage-node" title="" data-drupal-link-system-path="admin/lingotek/manage/node">Content</a>'),
      'There is an item for the Nodes in Content bulk management.');
    $this->assertTrue(FALSE !== strpos($html, '<a href="' . $basepath . '/admin/lingotek/config/manage" title="Manage config translations using Lingotek cloud-based localization" id="toolbar-link-lingotek-manage_config" class="toolbar-icon toolbar-icon-lingotek-manage-config" data-drupal-link-system-path="admin/lingotek/config/manage">Config</a>'),
      'There is an item for Config bulk management.');
    $this->assertTrue(FALSE !== strpos($html, '<a href="' . $basepath . '/admin/lingotek/settings" title="Lingotek configuration" id="toolbar-link-lingotek-settings" class="toolbar-icon toolbar-icon-lingotek-settings" data-drupal-link-system-path="admin/lingotek/settings">Settings</a>'),
      'There is an item for Lingotek Settings.');

    $html = $response[0]['subtrees']['system-admin_config'];
    $this->assertTrue(FALSE !== strpos($html, '<a href="' . $basepath . '/admin/lingotek" title="Convenient cloud-based localization and translation by Lingotek" id="toolbar-link-lingotek-dashboard" class="toolbar-icon toolbar-icon-lingotek-config-dashboard" data-drupal-link-system-path="admin/lingotek">Lingotek Translation</a>'),
      'There is an item for Lingotek in the config area.');
  }

}
