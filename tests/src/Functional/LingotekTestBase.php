<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\lingotek\Lingotek;
use Drupal\Tests\BrowserTestBase;
use Drupal\workflows\Entity\Workflow;
use GuzzleHttp\Cookie\CookieJar;

/**
 * Base class for Lingotek test. Performs authorization of the account.
 */
abstract class LingotekTestBase extends BrowserTestBase {

  use LingotekManagementTestTrait;

  /**
   * The cookie jar holding the testing session cookies for Guzzle requests.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * The Guzzle HTTP client.
   *
   * @var \GuzzleHttp\Cookie\CookieJar
   */
  protected $cookies;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['lingotek', 'lingotek_test'];

  /**
   * Minimal Lingotek translation manager user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $translationManagerUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Login as admin.
    $this->drupalLogin($this->rootUser);
    $this->connectToLingotek();

    $this->client = $this->getHttpClient();

    $this->createTranslationManagerUser();
  }

  /**
   * Creates a translation manager role and a user with the minimal
   * Lingotek translation management permissions.
   */
  protected function createTranslationManagerUser() {
    $this->translationManagerUser = $this->drupalCreateUser([
      'assign lingotek translation profiles',
      'manage lingotek translations',
      'access administration pages',
    ]);
  }

  /**
   * Create a new image field.
   *
   * @param string $name
   *   The name of the new field (all lowercase).
   * @param string $type_name
   *   The bundle that this field will be added to.
   * @param string $entity_type_id
   *   The entity type that this field will be added to. Defaults to 'node'.
   * @param array $storage_settings
   *   A list of field storage settings that will be added to the defaults.
   * @param array $field_settings
   *   A list of instance settings that will be added to the instance defaults.
   * @param array $widget_settings
   *   A list of widget settings that will be added to the widget defaults.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The field config.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createImageField($name, $type_name, $entity_type_id = 'node', array $storage_settings = [], array $field_settings = [], array $widget_settings = []) {
    entity_create('field_storage_config', [
      'field_name' => $name,
      'entity_type' => $entity_type_id,
      'type' => 'image',
      'settings' => $storage_settings,
      'cardinality' => !empty($storage_settings['cardinality']) ? $storage_settings['cardinality'] : 1,
    ])->save();

    $field_config = entity_create('field_config', [
      'field_name' => $name,
      'label' => $name,
      'entity_type' => $entity_type_id,
      'bundle' => $type_name,
      'required' => !empty($field_settings['required']),
      'settings' => $field_settings,
    ]);
    $field_config->save();

    entity_get_form_display($entity_type_id, $type_name, 'default')
      ->setComponent($name, [
        'type' => 'image_image',
        'settings' => $widget_settings,
      ])
      ->save();

    entity_get_display($entity_type_id, $type_name, 'default')
      ->setComponent($name)
      ->save();

    return $field_config;

  }

  /**
   * Connects to Lingotek.
   */
  protected function connectToLingotek() {
    $this->drupalGet('admin/lingotek/setup/account');
    $this->clickLink('Connect Lingotek Account');
    $this->drupalPostForm(NULL, ['community' => 'test_community'], 'Next');
    $this->drupalPostForm(NULL, [
      'project' => 'test_project',
      'vault' => 'test_vault',
    ], 'Save configuration');
  }

  /**
   * Go to the content bulk management form.
   *
   * @param string $entity_type_id
   *   Entity type ID we want to manage in bulk. By default is node.
   *
   * @param string $prefix
   *   The prefix if we want to visit the page in a different locale.
   */
  protected function goToContentBulkManagementForm($entity_type_id = 'node', $prefix = NULL) {
    $this->drupalGet($this->getContentBulkManagementFormUrl($entity_type_id, $prefix));
  }

  protected function getDestination($entity_type_id = 'node', $prefix = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    return '?destination=' . $basepath . $this->getContentBulkManagementFormUrl($entity_type_id, $prefix);
  }

  /**
   * Get the content bulk management url.
   *
   * @param string $entity_type_id
   *   Entity type ID we want to manage in bulk. By default is node.
   *
   * @return string
   *   The url.
   */
  protected function getContentBulkManagementFormUrl($entity_type_id = 'node', $prefix = NULL) {
    return ($prefix === NULL ? '' : '/' . $prefix) . '/admin/lingotek/manage/' . $entity_type_id;
  }

  /**
   * Go to the config bulk management form and filter one kind of configuration.
   *
   * @param string $filter
   *   Config name of the filter to apply. By default is NULL and will use the
   *   current one.
   */
  protected function goToConfigBulkManagementForm($filter = NULL) {
    $this->drupalGet('admin/lingotek/config/manage');

    if ($filter !== NULL) {
      $edit = ['filters[wrapper][bundle]' => $filter];
      $this->drupalPostForm(NULL, $edit, t('Filter'));
    }
  }

  /**
   * Asserts if the uploaded data contains the expected number of fields.
   *
   * @param array $data
   *   The uploaded data.
   * @param $count
   *   The expected number of items.
   */
  protected function assertUploadedDataFieldCount(array $data, $count) {
    // We have to add one item because of the metadata we include.
    $this->assertEqual($count + 1, count($data));
  }

  /**
   * Asserts if there are a number of documents with a given status and language
   * label as source.
   *
   * @param string $status
   *   The status we are looking for. Use Lingotek constants.
   * @param string $languageLabel
   *   The language label of the source.
   * @param int $count
   *   The expected number of items.
   * @param string $message
   *   (optional) A message to display with the assertion. Do not translate
   *   messages: use \Drupal\Component\Utility\SafeMarkup::format() to embed
   *   variables in the message text, not t(). If left blank, a default message
   *   will be displayed.
   */
  protected function assertSourceStatusStateCount($status, $languageLabel, $count, $message = '') {
    $statusCssClass = 'source-' . strtolower($status);
    if ($status === Lingotek::STATUS_CURRENT) {
      // There is no link or anchor when the status is current.
      $statusCount = $this->xpath("//span[contains(@class,'language-icon') and contains(@class, '$statusCssClass') and contains(text(), '$languageLabel')]");
    }
    else {
      $statusCount = $this->xpath("//span[contains(@class,'language-icon') and contains(@class, '$statusCssClass')]/a[contains(text(), '$languageLabel')]");
    }
    $this->assertEqual(count($statusCount), $count, $message);
  }

  /**
   * Asserts a given index of the management table shows a given profile.
   *
   * @param int $index
   *   The index of the table to check.
   * @param string|null $profile
   *   The profile to verify.
   */
  protected function assertManagementFormProfile($index, $profile) {
    $elements = $this->xpath("//*[@id='edit-table']/tbody/tr[$index]/td[6]");
    if ($profile === NULL) {
      $this->assertEqual(0, count($elements), "Profile for $index is shown as empty");
    }
    else {
      $shown_profile = $elements[0]->getHtml();
      $this->assertEqual($profile, $shown_profile, "Profile for $index is shown as $profile");
    }
  }

  /**
   * Create and publish a node.
   *
   * @param array $edit
   *   Field data in an associative array.
   * @param string $bundle
   *   The bundle of the node to be created.
   */
  protected function saveAndPublishNodeForm(array $edit, $bundle = 'article') {
    $path = ($bundle !== NULL) ? "node/add/$bundle" : NULL;
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $entity_definition = \Drupal::entityTypeManager()->getDefinition('node');
      if (\Drupal::moduleHandler()->moduleExists('content_moderation') &&
          \Drupal::service('content_moderation.moderation_information')->shouldModerateEntitiesOfBundle($entity_definition, $bundle)) {
        $edit['moderation_state[0][state]'] = 'published';
        $this->drupalPostForm($path, $edit, t('Save'));
      }
      else {
        $edit['status[value]'] = TRUE;
        $this->drupalPostForm($path, $edit, t('Save'));
      }
    }
    else {
      if (\Drupal::moduleHandler()->moduleExists('content_moderation')) {
        $this->drupalPostForm($path, $edit, t('Save and Publish'));
      }
      else {
        $this->drupalPostForm($path, $edit, t('Save and publish'));
      }
    }
  }

  protected function saveAndUnpublishNodeForm(array $edit, $nid) {
    $path = ($nid !== NULL) ? "node/$nid/edit" : NULL;
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $edit['status[value]'] = FALSE;
      $this->drupalPostForm($path, $edit, t('Save'));
    }
    else {
      $this->drupalPostForm($path, $edit, t('Save and unpublish'));
    }
  }

  protected function saveAsUnpublishedNodeForm(array $edit, $bundle = 'article') {
    $path = ($bundle !== NULL) ? "node/add/$bundle" : NULL;
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $edit['status[value]'] = FALSE;
      $this->drupalPostForm($path, $edit, t('Save'));
    }
    else {
      $this->drupalPostForm($path, $edit, t('Save as unpublished'));
    }
  }

  protected function saveAsRequestReviewNodeForm(array $edit, $bundle = 'article') {
    $path = ($bundle !== NULL) ? "node/add/$bundle" : NULL;
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $edit['moderation_state[0][state]'] = 'needs_review';
      $this->drupalPostForm($path, $edit, t('Save'));
    }
    else {
      $this->drupalPostForm($path, $edit, t('Save and Request Review'));
    }
  }

  protected function editAsRequestReviewNodeForm($path, array $edit) {
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $edit['moderation_state[0][state]'] = 'needs_review';
      $this->drupalPostForm($path, $edit, t('Save'));
    }
    else {
      $this->drupalPostForm($path, $edit, t('Save and Request Review (this translation)'));
    }
  }

  protected function saveAsNewDraftNodeForm(array $edit, $bundle = 'article') {
    $path = ($bundle !== NULL) ? "node/add/$bundle" : NULL;
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $edit['moderation_state[0][state]'] = 'draft';
      $this->drupalPostForm($path, $edit, t('Save'));
    }
    else {
      $this->drupalPostForm($path, $edit, t('Save and Create New Draft'));
    }
  }

  protected function editAsNewDraftNodeForm($path, array $edit) {
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $edit['moderation_state[0][state]'] = 'draft';
      $this->drupalPostForm($path, $edit, t('Save'));
    }
    else {
      $this->drupalPostForm($path, $edit, t('Save and Create New Draft (this translation)'));
    }
  }

  protected function saveAndKeepPublishedNodeForm(array $edit, $nid) {
    $path = ($nid !== NULL) ? "node/$nid/edit" : NULL;
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $edit['status[value]'] = TRUE;
      $this->drupalPostForm($path, $edit, t('Save'));
    }
    else {
      $this->drupalPostForm($path, $edit, t('Save and keep published'));
    }
  }

  protected function saveAndKeepPublishedThisTranslationNodeForm(array $edit, $nid) {
    $path = ($nid !== NULL) ? "node/$nid/edit" : NULL;
    if (floatval(\Drupal::VERSION) >= 8.4) {
      $edit['status[value]'] = TRUE;
      $this->drupalPostForm($path, $edit, t('Save (this translation)'));
    }
    else {
      $this->drupalPostForm($path, $edit, t('Save and keep published (this translation)'));
    }
  }

  /**
   * Configure content moderation.
   *
   * @param string $workflow_id
   *   The workflow id to be configured.
   * @param array $entities_map
   *   The entities and bundles map that wants to be enabled for a given workflow.
   *   Array in the form: [entity_type => [bundle1, bundle2]].
   */
  protected function configureContentModeration($workflow_id, array $entities_map) {
    if (floatval(\Drupal::VERSION) >= 8.4) {
      foreach ($entities_map as $entity_type_id => $bundles) {
        $edit = [];
        foreach ($bundles as $bundle) {
          $edit["bundles[$bundle]"] = $bundle;
        }
        $this->drupalPostForm("/admin/config/workflow/workflows/manage/$workflow_id/type/$entity_type_id", $edit, 'Save');
      }
    }
    else {
      if (isset($entities_map['node'])) {
        foreach ($entities_map['node'] as $bundle) {
          $this->drupalPostForm("/admin/structure/types/manage/$bundle/moderation", ['workflow' => $workflow_id], t('Save'));
        }
      }
    }
  }

  /**
   * Assert that there is a link to the workbench in a new tab.
   *
   * @param string $document_id
   *   The document id.
   * @param string $langcode
   *   The language code.
   * @param string $locale
   *   The Lingotek locale.
   *
   * @deprecated Use ::assertLingotekWorkbenchLink instead.
   */
  protected function assertLinkToWorkbenchInNewTabInSinglePage($document_id, $langcode, $locale) {
    $this->assertLingotekWorkbenchLink($locale, $document_id);
  }

  /**
   * Assert that a content target has the given status.
   *
   * @param string $language
   *   The target language.
   * @param string $status
   *   The status.
   */
  protected function assertTargetStatus($language, $status) {
    $status_target = $this->xpath("//a[contains(@class,'language-icon') and contains(@class,'target-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
    // If not found, maybe it didn't have a link.
    if (count($status_target) === 1) {
      $this->assertEqual(count($status_target), 1, 'The target ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
    }
    else {
      $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'target-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
      $this->assertEqual(count($status_target), 1, 'The target ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
    }
  }

  /**
   * Assert that a content target has not the given status.
   *
   * @param string $language
   *   The target language.
   * @param string $status
   *   The status.
   */
  protected function assertNoTargetStatus($language, $status) {
    $status_target = $this->xpath("//a[contains(@class,'language-icon') and contains(@class,'target-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
    $this->assertEqual(count($status_target), 0, 'The target ' . strtoupper($language) . ' has not been marked with status ' . strtolower($status) . '.');
  }

  /**
   * Assert that a content source has the given status.
   *
   * @param string $language
   *   The target language.
   * @param string $status
   *   The status.
   */
  protected function assertSourceStatus($language, $status) {
    $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and ./a[contains(text(), '" . strtoupper($language) . "')]]");
    // If not found, maybe it didn't have a link.
    if (count($status_target) === 1) {
      $this->assertEqual(count($status_target), 1, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
    }
    else {
      $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
      $this->assertEqual(count($status_target), 1, 'The source ' . strtoupper($language) . ' has been marked with status ' . strtolower($status) . '.');
    }
  }

  /**
   * Assert that a content source has not the given status.
   *
   * @param string $language
   *   The target language.
   * @param string $status
   *   The status.
   */
  protected function assertNoSourceStatus($language, $status) {
    $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and ./a[contains(text(), '" . strtoupper($language) . "')]]");
    // If not found, maybe it didn't have a link.
    if (count($status_target) === 0) {
      $this->assertEqual(count($status_target), 0, 'The source ' . strtoupper($language) . ' has not been marked with status ' . strtolower($status) . '.');
    }
    else {
      $status_target = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'source-" . strtolower($status) . "')  and contains(text(), '" . strtoupper($language) . "')]");
      $this->assertEqual(count($status_target), 0, 'The source ' . strtoupper($language) . ' has not been marked with status ' . strtolower($status) . '.');
    }
  }

  /**
   * Assert that a content target has not been marked as error.
   *
   * @param string $label
   *   The label of the row.
   * @param string $language
   *   The target language.
   * @param string $locale
   *   The target locale.
   */
  protected function assertNoTargetError($label, $language, $locale) {
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'target-error')  and ./a[contains(text(), '" . strtoupper($language) . "')]]");
    $this->assertEqual(count($source_error), 0, 'The target ' . strtoupper($language) . ' has not been marked as error.');
    $this->assertNoText($label . ' ' . $locale . ' translation download failed. Please try again.');
  }

  /**
   * Assert that a config target has not been marked as error.
   *
   * @param string $label
   *   The label of the row.
   * @param string $language
   *   The target language.
   * @param string $locale
   *   The target locale.
   */
  protected function assertNoConfigTargetError($label, $language, $locale) {
    $source_error = $this->xpath("//span[contains(@class,'language-icon') and contains(@class,'target-error')  and ./a[contains(text(), '" . strtoupper($language) . "')]]");
    $this->assertEqual(count($source_error), 0, 'The target ' . strtoupper($language) . ' has not been marked as error.');
    $this->assertNoText($label . ' ' . $locale . ' translation download failed. Please try again.');
  }

  /**
   * Obtain the HTTP client and set the cookies.
   *
   * @return \GuzzleHttp\Client
   *   The client with BrowserTestBase configuration.
   */
  protected function getHttpClient() {
    // Similar code is also employed to test CSRF tokens.
    // @see \Drupal\Tests\system\Functional\CsrfRequestHeaderTest::testRouteAccess()
    $domain = parse_url($this->getUrl(), PHP_URL_HOST);
    $session_id = $this->getSession()->getCookie($this->getSessionName());
    $this->cookies = CookieJar::fromArray([$this->getSessionName() => $session_id], $domain);
    return $this->getSession()->getDriver()->getClient()->getClient();
  }

  /**
   * Save Lingotek content translation settings.
   *
   * Example:
   * @code
   *   $this->saveLingotekContentTranslationSettings([
   *     'node' => [
   *       'article' => [
   *         'profiles' => 'automatic',
   *         'fields' => [
   *           'title' => 1,
   *           'body' => 1,
   *         ],
   *         'moderation' => [
   *           'upload_status' => 'draft',
   *           'download_transition' => 'request_review',
   *         ],
   *       ],
   *    ],
   *     'paragraph' => [
   *       'image_text' => [
   *         'fields' => [
   *           'field_image_demo' => ['title', 'alt'],
   *           'field_text_demo' => 1,
   *         ],
   *       ],
   *     ],
   *   ]);
   * @endcode
   *
   * @param array $settings
   *   The settings we want to save.
   */
  protected function saveLingotekContentTranslationSettings($settings) {
    $edit = [];
    foreach ($settings as $entity_type => $entity_type_settings) {
      foreach ($entity_type_settings as $bundle_id => $bundle_settings) {
        $edit[$entity_type . '[' . $bundle_id . '][enabled]'] = 1;
        if (isset($bundle_settings['profiles']) && $bundle_settings['profiles'] !== NULL) {
          $edit[$entity_type . '[' . $bundle_id . '][profiles]'] = $bundle_settings['profiles'];
        }
        foreach ($bundle_settings['fields'] as $field_id => $field_properties) {
          $edit[$entity_type . '[' . $bundle_id . '][fields][' . $field_id . ']'] = 1;
          if (is_array($field_properties)) {
            foreach ($field_properties as $field_property) {
              $edit[$entity_type . '[' . $bundle_id . '][fields][' . $field_id . ':properties][' . $field_property . ']'] = $field_property;
            }
          }
        }
        if (isset($bundle_settings['moderation']) && $bundle_settings['moderation'] !== NULL) {
          $edit[$entity_type . '[' . $bundle_id . '][moderation][upload_status]'] = $bundle_settings['moderation']['upload_status'];
          $edit[$entity_type . '[' . $bundle_id . '][moderation][download_transition]'] = $bundle_settings['moderation']['download_transition'];
        }
      }
    }
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], 'lingoteksettings-tab-content-form');
  }

  /**
   * Save Lingotek translation settings for node types.
   *
   * Example:
   * @code
   *      $this->saveLingotekContentTranslationSettingsForNodeTypes(
   *        ['article', 'page'], manual);
   * @endcode
   *
   * @param array $node_types
   *   The node types we want to enable.
   * @param string $profile
   *   The profile id we want to use.
   */
  protected function saveLingotekContentTranslationSettingsForNodeTypes($node_types = ['article'], $profile = 'automatic') {
    $settings = [];
    foreach ($node_types as $node_type) {
      $settings['node'][$node_type] = [
        'profiles' => $profile,
        'fields' => [
          'title' => 1,
          'body' => 1,
        ],
      ];
    }
    $this->saveLingotekContentTranslationSettings($settings);
  }

  /**
   * Save Lingotek configuration translation settings.
   *
   * Example:
   * @code
   *      $this->saveLingotekConfigTranslationSettings([
   *        'node_type' => 'manual',
   *        'node_fields' => 'automatic',
   *      ]);
   * @endcode
   *
   * @param array $settings
   *   The settings we want to save.
   */
  protected function saveLingotekConfigTranslationSettings($settings) {
    // ToDo: Remove this when 8.5.x is not supported anymore.
    $this->drupalGet('admin/lingotek/settings');

    $edit = [];
    foreach ($settings as $entity_type => $profile) {
      $edit['table[' . $entity_type . '][enabled]'] = 1;
      $edit['table[' . $entity_type . '][profile]'] = $profile;
    }
    // ToDo: Remove this when 8.5.x is not supported anymore and replace with
    // $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], 'lingoteksettings-tab-configuration-form');
    $this->submitForm($edit, 'Save', 'lingoteksettings-tab-configuration-form');
  }

  /**
   * Creates the editorial workflow.
   *
   * @deprecated ToDo: Remove when 8.5.x is unsupported.
   * Copied from trait ContentModerationTestTrait in 8.6.x.
   *
   * @return \Drupal\workflows\Entity\Workflow
   *   The editorial workflow entity.
   */
  protected function createEditorialWorkflow() {
    if ($workflow = Workflow::load('editorial') === NULL) {
      $workflow = Workflow::create([
        'type' => 'content_moderation',
        'id' => 'editorial',
        'label' => 'Editorial',
        'type_settings' => [
          'states' => [
            'archived' => [
              'label' => 'Archived',
              'weight' => 5,
              'published' => FALSE,
              'default_revision' => TRUE,
            ],
            'draft' => [
              'label' => 'Draft',
              'published' => FALSE,
              'default_revision' => FALSE,
              'weight' => -5,
            ],
            'published' => [
              'label' => 'Published',
              'published' => TRUE,
              'default_revision' => TRUE,
              'weight' => 0,
            ],
          ],
          'transitions' => [
            'archive' => [
              'label' => 'Archive',
              'from' => ['published'],
              'to' => 'archived',
              'weight' => 2,
            ],
            'archived_draft' => [
              'label' => 'Restore to Draft',
              'from' => ['archived'],
              'to' => 'draft',
              'weight' => 3,
            ],
            'archived_published' => [
              'label' => 'Restore',
              'from' => ['archived'],
              'to' => 'published',
              'weight' => 4,
            ],
            'create_new_draft' => [
              'label' => 'Create New Draft',
              'to' => 'draft',
              'weight' => 0,
              'from' => [
                'draft',
                'published',
              ],
            ],
            'publish' => [
              'label' => 'Publish',
              'to' => 'published',
              'weight' => 1,
              'from' => [
                'draft',
                'published',
              ],
            ],
          ],
        ],
      ]);
      $workflow->save();
    }
    return $workflow;
  }

}
