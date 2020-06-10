<?php

namespace Drupal\Tests\lingotek\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;

/**
 * Tests changing a profile using the bulk management form.
 *
 * @group lingotek
 */
class LingotekNodeBulkProfileTest extends LingotekTestBase {

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
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
  }

  /**
   * Tests that the translation profiles can be updated with the bulk actions.
   */
  public function testChangeTranslationProfileBulk() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create three nodes.
    $nodes = [];
    // Create a node.
    for ($i = 1; $i < 4; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);
    $this->assertLingotekUploadLink(3);
    $edit = [
      'table[1]' => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());
    $this->assertIdentical('en_US', \Drupal::state()->get('lingotek.uploaded_locale'));

    // I can check current status.
    $this->assertLingotekCheckSourceStatusLink();
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there are three nodes with the Manual Profile
    $manual_profile = $this->xpath("//td[contains(text(), 'Manual')]");
    $this->assertEqual(count($manual_profile), 3, 'There are three nodes with the Manual Profile set.');

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:automatic',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 3, 'There are three nodes with the Automatic Profile set.');

    $edit = [
      'table[2]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:manual',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there is one node with the Manual Profile
    // Check that there are two nodes with the Automatic Profile
    $manual_profile = $this->xpath("//td[contains(text(), 'Manual')]");
    $this->assertEqual(count($manual_profile), 1, 'There is one node with the Manual Profile set.');
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 2, 'There are two nodes with the Automatic Profile set.');

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:disabled',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there are three nodes with the Disabled Profile
    $disabled_profile = $this->xpath("//td[contains(text(), 'Disabled')]");
    $this->assertEqual(count($disabled_profile), 3, 'There are three nodes with the Disabled Profile set.');

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    $this->goToContentBulkManagementForm();
    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'es'));
    }
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'es'));
    }

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'es'));
    }

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'es'));
    }

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'es'));
    }

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslation('es', 'node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'es'));
    }

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:automatic',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 3, 'There are three nodes with the Automatic Profile set.');

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('lingotek_content_metadata')->resetCache();

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));
    }

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('lingotek_content_metadata')->resetCache();

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_IMPORTING, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));
    }

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckUpload('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('lingotek_content_metadata')->resetCache();

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));
    }

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForRequestTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('lingotek_content_metadata')->resetCache();

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_PENDING, $content_translation_service->getTargetStatus($node, 'es'));
    }

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCheckTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('lingotek_content_metadata')->resetCache();

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));
    }

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForDownloadTranslations('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('lingotek_content_metadata')->resetCache();

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));
    }

    // Edit the nodes.
    for ($i = 1; $i < 4; $i++) {
      $edit = [];
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = Lingotek::PROFILE_DISABLED;
      $this->saveAndKeepPublishedNodeForm($edit, $i);
    }
    $this->goToContentBulkManagementForm();

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('lingotek_content_metadata')->resetCache();

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_DISABLED, $content_translation_service->getTargetStatus($node, 'es'));
    }

    // Edit the nodes.
    for ($i = 1; $i < 4; $i++) {
      $edit = [];
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndKeepPublishedNodeForm($edit, $i);
    }
    $this->goToContentBulkManagementForm();

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('lingotek_content_metadata')->resetCache();

    for ($i = 1; $i < 4; $i++) {
      $node = Node::load($i);
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
      $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getTargetStatus($node, 'en'));
      $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));
    }
  }

  /**
   * Tests that the translation profiles can be updated with the bulk actions after
   * cancelling.
   */
  public function testChangeTranslationProfileBulkAfterCancelling() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    // Create three nodes.
    $nodes = [];
    for ($i = 1; $i < 4; $i++) {
      $edit = [];
      $edit['title[0][value]'] = 'Llamas are cool ' . $i;
      $edit['body[0][value]'] = 'Llamas are very cool ' . $i;
      $edit['langcode[0][value]'] = 'en';
      $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'manual';
      $this->saveAndPublishNodeForm($edit);
      $nodes[$i] = $edit;
    }

    $this->goToContentBulkManagementForm();

    // I can init the upload of content.
    $this->assertLingotekUploadLink(1);
    $this->assertLingotekUploadLink(2);
    $this->assertLingotekUploadLink(3);
    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => $this->getBulkOperationNameForCancel('node'),
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    $key = $this->getBulkSelectionKey('en', 1);
    $edit = [
      $key => TRUE,
      'table[2]' => TRUE,
      'table[3]' => TRUE,
      $this->getBulkOperationFormName() => 'change_profile:automatic',
    ];
    $this->drupalPostForm(NULL, $edit, $this->getApplyActionsButtonLabel());

    // Check that there are three nodes with the Automatic Profile
    $automatic_profile = $this->xpath("//td[contains(text(), 'Automatic')]");
    $this->assertEqual(count($automatic_profile), 3, 'There are three nodes with the Automatic Profile set.');
  }

}
