<?php

namespace Drupal\Tests\lingotek\Functional\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Lingotek;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;
use Drupal\node\Entity\Node;

/**
 * Tests the Lingotek metadata form.
 *
 * @group lingotek
 */
class LingotekMetadataEditFormTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['block', 'node', 'frozenintime'];

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Create Article and Page node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Page']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    ContentLanguageSettings::loadByEntityTypeBundle('node', 'page')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);

    drupal_static_reset();
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
  }

  /**
   * Tests that if debug is not enabled, metadata tab is not available.
   */
  public function testMetadataLocalTaskNotAvailable() {
    $assert_session = $this->assertSession();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);
    $this->assertUrl('/node/1', [], 'Node has been created.');

    // The metadata local task should not be visible.
    $assert_session->linkNotExists(t('Lingotek Metadata'));
  }

  /**
   * Tests that if debug is not enabled, metadata tab is not available.
   */
  public function testMetadataLocalTaskAvailable() {
    $assert_session = $this->assertSession();

    // Enable debug operations.
    $this->drupalPostForm('admin/lingotek/settings', [], 'Enable debug operations');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);
    $this->assertUrl('/node/1', [], 'Node has been created.');

    // The metadata local task should be visible.
    $this->drupalGet('/node/1');
    $assert_session->linkExists(t('Lingotek Metadata'));
  }

  /**
   * Tests that if debug is not enabled, metadata tab is not available.
   */
  public function testMetadataEditForm() {
    $assert_session = $this->assertSession();

    // Enable debug operations.
    $this->drupalPostForm('admin/lingotek/settings', [], 'Enable debug operations');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);

    // The metadata local task should be visible.
    $this->drupalGet('/node/1');
    $this->clickLink(t('Lingotek Metadata'));
    $this->assertUrl('/node/1/metadata', [], 'Metadata local task enables the metadata form.');

    // Assert that the values are correct.
    $this->assertFieldById('edit-lingotek-document-id', 'dummy-document-hash-id');
    $assert_session->optionExists('edit-lingotek-source-status', Lingotek::STATUS_IMPORTING);
    $assert_session->optionExists('edit-en', Lingotek::STATUS_IMPORTING);
    $assert_session->optionExists('edit-es', Lingotek::STATUS_REQUEST);
    $this->assertFieldById('edit-lingotek-job-id', '');
    $timestamp = \Drupal::time()->getRequestTime();
    $this->assertSession()->fieldValueEquals('verbatim_area[verbatim]', <<<JSON
{
    "id": [
        {
            "value": "1"
        }
    ],
    "content_entity_type_id": [
        {
            "value": "node"
        }
    ],
    "content_entity_id": [
        {
            "value": "1"
        }
    ],
    "document_id": [
        {
            "value": "dummy-document-hash-id"
        }
    ],
    "hash": [],
    "profile": [
        {
            "target_id": "automatic"
        }
    ],
    "translation_source": [
        {
            "value": "en"
        }
    ],
    "translation_status": [
        {
            "value": "IMPORTING",
            "language": "en"
        },
        {
            "value": "REQUEST",
            "language": "es"
        }
    ],
    "job_id": [],
    "updated_timestamp": [],
    "uploaded_timestamp": [
        {
            "value": "$timestamp"
        }
    ]
}
JSON
    );
    $this->assertFieldByName('lingotek_translation_management[lingotek_translation_profile]', 'automatic');

    $edit = [
      'lingotek_document_id' => 'another-id',
      'lingotek_source_status' => Lingotek::STATUS_UNTRACKED,
      'en' => Lingotek::STATUS_UNTRACKED,
      'es' => Lingotek::STATUS_READY,
      'lingotek_job_id' => 'a new edited job id',
      'lingotek_translation_management[lingotek_translation_profile]' => 'manual',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save metadata');

    // Assert that the values are correct.
    $this->assertFieldById('edit-lingotek-document-id', 'another-id');
    // ToDo: We should avoid that an upload is triggered, even if using automatic profile.
    // $assert_session->optionExists('edit-lingotek-source-status', Lingotek::STATUS_CURRENT);
    $assert_session->optionExists('edit-lingotek-source-status', Lingotek::STATUS_UNTRACKED);
    $assert_session->optionExists('edit-en', Lingotek::STATUS_UNTRACKED);
    $assert_session->optionExists('edit-es', Lingotek::STATUS_READY);
    $this->assertFieldById('edit-lingotek-job-id', 'a new edited job id');
    $this->assertSession()->fieldValueEquals('verbatim_area[verbatim]', <<<JSON
{
    "id": [
        {
            "value": "1"
        }
    ],
    "content_entity_type_id": [
        {
            "value": "node"
        }
    ],
    "content_entity_id": [
        {
            "value": "1"
        }
    ],
    "document_id": [
        {
            "value": "another-id"
        }
    ],
    "hash": [],
    "profile": [
        {
            "target_id": "manual"
        }
    ],
    "translation_source": [
        {
            "value": "en"
        }
    ],
    "translation_status": [
        {
            "value": "UNTRACKED",
            "language": "en"
        },
        {
            "value": "READY",
            "language": "es"
        }
    ],
    "job_id": [
        {
            "value": "a new edited job id"
        }
    ],
    "updated_timestamp": [],
    "uploaded_timestamp": [
        {
            "value": "$timestamp"
        }
    ]
}
JSON
    );
    $this->assertFieldByName('lingotek_translation_management[lingotek_translation_profile]', 'manual');

    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $configuration_service */
    $configuration_service = \Drupal::service('lingotek.configuration');
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $node = Node::load(1);
    // Assert that the values are correct in the service.
    $this->assertIdentical('another-id', $content_translation_service->getDocumentId($node));
    $this->assertIdentical('manual', $configuration_service->getEntityProfile($node, FALSE)->id());
    // ToDo: We should avoid that an upload is triggered, even if using automatic profile.
    // $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getTargetStatus($node, 'en'));
    $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));

    $metadata = LingotekContentMetadata::load(1);
    $this->assertIdentical('a new edited job id', $metadata->getJobId(), 'Lingotek metadata job id was saved correctly.');
  }

  /**
   * Tests that if debug is not enabled, metadata tab is not available.
   */
  public function testMetadataEditFormWithoutEnablingBundle() {
    $assert_session = $this->assertSession();

    // Enable debug operations.
    $this->drupalPostForm('admin/lingotek/settings', [], 'Enable debug operations');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $this->saveAndPublishNodeForm($edit, 'page');

    // The metadata local task should be visible.
    $this->drupalGet('/node/1');
    $this->clickLink(t('Lingotek Metadata'));
    $this->assertUrl('/node/1/metadata', [], 'Metadata local task enables the metadata form.');

    // Assert that the values are defaults.
    $this->assertFieldById('edit-lingotek-document-id', '');
    $assert_session->optionExists('edit-lingotek-source-status', Lingotek::STATUS_UNTRACKED);
    $assert_session->optionExists('edit-en', Lingotek::STATUS_UNTRACKED);
    $assert_session->optionExists('edit-es', Lingotek::STATUS_UNTRACKED);
    $this->assertFieldById('edit-lingotek-job-id', '');
    $this->assertFieldByName('verbatim_area[verbatim]', 'NULL');

    $edit = [
      'lingotek_document_id' => 'another-id',
      'lingotek_source_status' => Lingotek::STATUS_UNTRACKED,
      'en' => Lingotek::STATUS_UNTRACKED,
      'es' => Lingotek::STATUS_READY,
      'lingotek_job_id' => 'a new edited job id',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save metadata');

    $assert_session->pageTextNotContains('Metadata saved successfully');
    $assert_session->pageTextContains('This entity cannot be managed in Lingotek. Please check your configuration.');
  }

  public function testMetadataEditFormWithoutExistingMetadata() {
    $assert_session = $this->assertSession();

    // Enable debug operations.
    $this->drupalPostForm('admin/lingotek/settings', [], 'Enable debug operations');

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $this->saveAndPublishNodeForm($edit, 'page');

    // Enable support later, after the node is already created.
    $this->saveLingotekContentTranslationSettingsForNodeTypes(['page']);

    // The metadata local task should be visible.
    $this->drupalGet('/node/1');
    $this->clickLink(t('Lingotek Metadata'));
    $this->assertUrl('/node/1/metadata', [], 'Metadata local task enables the metadata form.');

    // Assert that the values are defaults.
    $this->assertFieldById('edit-lingotek-document-id', '');
    $assert_session->optionExists('edit-lingotek-source-status', Lingotek::STATUS_UNTRACKED);
    $assert_session->optionExists('edit-en', Lingotek::STATUS_UNTRACKED);
    $assert_session->optionExists('edit-es', Lingotek::STATUS_UNTRACKED);
    $this->assertFieldById('edit-lingotek-job-id', '');
    $this->assertFieldByName('verbatim_area[verbatim]', 'NULL');

    $edit = [
      'lingotek_document_id' => 'another-id',
      'lingotek_source_status' => Lingotek::STATUS_UNTRACKED,
      'en' => Lingotek::STATUS_UNTRACKED,
      'es' => Lingotek::STATUS_READY,
      'lingotek_job_id' => 'a new edited job id',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save metadata');

    $assert_session->pageTextContains('Metadata saved successfully');

    // Assert that the values are correct.
    $this->assertFieldById('edit-lingotek-document-id', 'another-id');
    // ToDo: We should avoid that an upload is triggered, even if using automatic profile.
    // $assert_session->optionExists('edit-lingotek-source-status', Lingotek::STATUS_CURRENT);
    $assert_session->optionExists('edit-lingotek-source-status', Lingotek::STATUS_UNTRACKED);
    $assert_session->optionExists('edit-en', Lingotek::STATUS_UNTRACKED);
    $assert_session->optionExists('edit-es', Lingotek::STATUS_READY);
    $this->assertFieldById('edit-lingotek-job-id', 'a new edited job id');
    $this->assertSession()->fieldValueEquals('verbatim_area[verbatim]', <<<JSON
{
    "id": [
        {
            "value": "1"
        }
    ],
    "content_entity_type_id": [
        {
            "value": "node"
        }
    ],
    "content_entity_id": [
        {
            "value": "1"
        }
    ],
    "document_id": [
        {
            "value": "another-id"
        }
    ],
    "hash": [],
    "profile": [
        {
            "target_id": "automatic"
        }
    ],
    "translation_source": [
        {
            "value": "en"
        }
    ],
    "translation_status": [
        {
            "value": "UNTRACKED",
            "language": "en"
        },
        {
            "value": "READY",
            "language": "es"
        }
    ],
    "job_id": [
        {
            "value": "a new edited job id"
        }
    ],
    "updated_timestamp": [],
    "uploaded_timestamp": []
}
JSON
    );

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');
    $node = Node::load(1);
    // Assert that the values are correct in the service.
    $this->assertIdentical('another-id', $content_translation_service->getDocumentId($node));
    // ToDo: We should avoid that an upload is triggered, even if using automatic profile.
    // $this->assertIdentical(Lingotek::STATUS_CURRENT, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getSourceStatus($node));
    $this->assertIdentical(Lingotek::STATUS_UNTRACKED, $content_translation_service->getTargetStatus($node, 'en'));
    $this->assertIdentical(Lingotek::STATUS_READY, $content_translation_service->getTargetStatus($node, 'es'));

    $metadata = LingotekContentMetadata::load(1);
    $this->assertIdentical('a new edited job id', $metadata->getJobId(), 'Lingotek metadata job id was saved correctly.');
  }

}
