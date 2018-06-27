<?php

namespace Drupal\Tests\lingotek\Functional\Render\Element;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the markup of lingotek render element types passed to drupal_render().
 *
 * @group lingotek
 */
class RenderElementTypesTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'block', 'node', 'lingotek_form_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();
    ConfigurableLanguage::createFromLangcode('ca')->setThirdPartySetting('lingotek', 'locale', 'ca_ES')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'article', TRUE);

    drupal_static_reset();
    \Drupal::entityManager()->clearCachedDefinitions();
    \Drupal::service('entity.definition_update_manager')->applyUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $edit = [
      'node[article][enabled]' => 1,
      'node[article][profiles]' => 'manual',
      'node[article][fields][title]' => 1,
      'node[article][fields][body]' => 1,
    ];
    $this->drupalPostForm('admin/lingotek/settings', $edit, 'Save', [], [], 'lingoteksettings-tab-content-form');
  }

  /**
   * Tests #type 'lingotek_source_status'.
   */
  public function testLingotekSourceStatus() {
    $basepath = \Drupal::request()->getBasePath();

    /** @var \Drupal\node\NodeInterface $entity */
    $entity = Node::create(['id' => 1, 'title' => 'Llamas are cool', 'type' => 'article']);
    $entity->save();

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_UNTRACKED);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-untracked" title="Upload"><a href="' . $basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/lingotek_form_test/lingotek_translation_status/node/1">EN</a></span>');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
    $translation_service->setDocumentId($entity, 'test-document-id');
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-importing" title="Source importing"><a href="' . $basepath . '/admin/lingotek/entity/check_upload/test-document-id?destination=' . $basepath . '/lingotek_form_test/lingotek_translation_status/node/1">EN</a></span>');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-current" title="Source uploaded">EN</span>');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_DISABLED);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-disabled" title="Disabled, cannot request translation">EN</span>');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_EDITED);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-edited" title="Re-upload (content has changed since last upload)"><a href="' . $basepath . '/admin/lingotek/entity/update/test-document-id?destination=' . $basepath . '/lingotek_form_test/lingotek_translation_status/node/1">EN</a></span>');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_ERROR);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-error" title="Error"><a href="' . $basepath . '/admin/lingotek/entity/update/test-document-id?destination=' . $basepath . '/lingotek_form_test/lingotek_translation_status/node/1">EN</a></span>');
  }

}
