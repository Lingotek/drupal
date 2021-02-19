<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests the bulk management form when the group module is enabled.
 *
 * @group lingotek
 */
class LingotekNodeBulkFormWithGroupModuleTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'node',
    'group',
    'gnode',
    'lingotek_group_test',
  ];

  /**
   * A node used for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * Groups used for testing.
   *
   * @var \Drupal\group\Entity\GroupInterface[]
   */
  protected $groups;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_tasks_block', [
      'region' => 'content',
      'weight' => -10,
    ]);
    $this->drupalPlaceBlock('local_actions_block', [
      'region' => 'content',
      'weight' => -2,
    ]);

    $type = NodeType::load('article');
    $field = node_add_body_field($type);

    // Assign display settings for the 'default' and 'teaser' view modes.
    $entity_form_display = EntityFormDisplay::load('node' . '.' . $type->id() . '.' . 'default');
    if (!$entity_form_display) {
      $entity_form_display = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $type->id(),
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $entity_form_display->setComponent('body', [
      'type' => 'text_textarea_with_summary',
    ])
      ->save();
    $display = EntityViewDisplay::load('node' . '.' . $type->id() . '.' . 'default');
    if (!$display) {
      $display = EntityViewDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $type->id(),
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $display->setComponent('body', [
      'label' => 'hidden',
      'type' => 'text_default',
    ])
      ->save();

    $this->drupalGet('/admin/structure/types/manage/article/form-display');

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

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

    $this->configureGroups();
  }

  /**
   * Tests that the bulk management group filtering exists for nodes.
   */
  public function testGroupFilterExistsForNodes() {
    $this->goToContentBulkManagementForm();

    // Assert there is a select for group.
    $this->assertField('filters[wrapper][group]', 'There is a filter for group');
  }

  /**
   * Tests that the bulk management group filtering exists for nodes.
   */
  public function testGroupFilterDoesntExistForNodesIfNoGnode() {
    /** @var \Drupal\Core\Extension\ModuleInstallerInterface  $moduleInstaller */
    $moduleInstaller = \Drupal::service('module_installer');
    $moduleInstaller->uninstall(['gnode']);

    $this->goToContentBulkManagementForm();

    // Assert there is a select for group.
    $this->assertNoField('filters[wrapper][group]', 'There is not a filter for group');
  }

  /**
   * Tests that the bulk management group filtering doesn't exist for other content entities.
   */
  public function testGroupFilterDoesntExistForNonNodes() {
    $this->configureUsersPerLingotekTranslation();
    $this->goToContentBulkManagementForm('user');

    // Assert there is not a select for group.
    $this->assertNoField('filters[wrapper][group]', 'There is not a filter for group');
  }

  /**
   * Configure users per Lingotek translation.
   */
  protected function configureUsersPerLingotekTranslation() {
    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('user', 'user')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('user', 'user', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'user[user][enabled]' => 1,
      'user[user][profiles]' => 'automatic',
      'user[user][fields][changed]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], 'lingoteksettings-tab-content-form');
  }

  /**
   * Tests that the bulk management group filtering works correctly.
   */
  public function testGroupFilter() {
    $assert_session = $this->assertSession();

    $nodes = [];
    // Create some nodes and relate them with groups.
    for ($i = 1; $i < 15; $i++) {
      $group = 1;
      if ($i % 2 == 0) {
        $group = 2;
      }
      elseif ($i % 3 == 0) {
        $group = 3;
      }

      $edit = [];
      $edit['title[0][value]'] = new FormattableMarkup('Llamas are cool @i at Group @group', [
        '@group' => $this->groups[$group],
        '@i' => $i,
      ]);
      $edit['body[0][value]'] = $edit['title[0][value]'];
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $this->relateNodeToGroup($i, $group, $edit['title[0][value]']);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // Assert there is a pager.
    $assert_session->linkByHrefExists('?page=1');

    // After we filter by first group, there is no pager and the rows selected
    // are the ones expected.
    $edit = [
      'filters[wrapper][group]' => '1',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([1, 5, 7, 11, 13] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $j . ' at Group My Product 1.0');
    }
    $assert_session->linkByHrefNotExists('?page=1');
    $assert_session->linkNotExists('Llamas are cool 2 at Group My Product 2.0');

    // After we filter by second group, there is no pager and the rows selected
    // are the ones expected.
    $edit = [
      'filters[wrapper][group]' => '2',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([2, 4, 6, 8, 10, 12, 14] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $j . ' at Group My Product 2.0');
    }
    $assert_session->linkNotExists('Page 2');
    $assert_session->linkNotExists('Llamas are cool 1 at Group My Product 1.0');

    // After we filter by third group, there is no pager and the rows selected
    // are the ones expected.
    $edit = [
      'filters[wrapper][group]' => '3',
    ];
    $this->drupalPostForm(NULL, $edit, 'edit-filters-actions-submit');
    foreach ([3, 9] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $j . ' at Group My Product 2.4');
    }
    $assert_session->linkByHrefNotExists('?page=1');
    $assert_session->linkNotExists('Llamas are cool 5 at Group My Product 1.0');

    // After we reset, we get back to having a pager and all the content.
    $this->drupalPostForm(NULL, [], 'Reset');
    foreach ([1, 5, 7] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $j . ' at Group My Product 1.0');
    }
    foreach ([2, 4, 6, 8, 10] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $j . ' at Group My Product 2.0');
    }
    foreach ([3, 9] as $j) {
      $assert_session->linkExists('Llamas are cool ' . $j . ' at Group My Product 2.4');
    }
    $assert_session->linkByHrefExists('?page=1');
  }

  /**
   * Add some test groups.
   */
  protected function configureGroups() {
    $this->groups[1] = $this->addGroup('Release', 'My Product 1.0');
    $this->groups[2] = $this->addGroup('Release', 'My Product 2.0');
    $this->groups[3] = $this->addGroup('Release', 'My Product 2.4');
  }

  /**
   * Add a group via the UI.
   *
   * @param string $group_label
   *   The group type label.
   * @param $label
   *   The name of the group.
   *
   * @return string
   *   The name of the group.
   */
  protected function addGroup($group_label, $label) {
    $this->drupalGet('/admin/group');
    $this->clickLink('Add group');

    $edit = ['label[0][value]' => $label];
    $this->drupalPostForm(NULL, $edit, new FormattableMarkup('Create @group and complete your membership', ['@group' => $group_label]));
    $this->drupalPostForm(NULL, [], 'Save group and membership');
    return $label;
  }

  /**
   * Relates a node to a group.
   *
   * @param int $nid
   *   The node id.
   * @param int $gid
   *   The group id.
   * @param string $title
   *   The node title.
   */
  protected function relateNodeToGroup($nid, $gid, $title) {
    $this->drupalGet('/group/' . $gid . '/content/add/group_node%3Aarticle');
    $edit = ['entity_id[0][target_id]' => $title . ' (' . $nid . ')'];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertSession()->titleEquals($title . ' | Drupal');
  }

}
