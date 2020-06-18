<?php

namespace Drupal\Tests\lingotek\Functional\FieldFormatters;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek source status field formatter.
 *
 * @group lingotek
 */
class LingotekSourceStatusFormatterTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'lingotek_visitable_metadata'];

  /**
   * {@inheritdoc}
   */
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
   * Tests that if debug is not enabled, metadata tab is not available.
   */
  public function testLingotekSourceStatusFormatter() {
    $assert_session = $this->assertSession();
    $basepath = \Drupal::request()->getBasePath();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);
    $assert_session->addressEquals('/node/1');

    $this->drupalGet('/metadata/1');
    $assert_session->responseContains('<span class="language-icon source-importing" title="Source importing"><a href="' . $basepath . '/admin/lingotek/entity/check_upload/dummy-document-hash-id?destination=' . $basepath . '/metadata/1">EN</a></span>');
  }

}
