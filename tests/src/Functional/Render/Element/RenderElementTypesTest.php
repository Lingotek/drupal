<?php

namespace Drupal\Tests\lingotek\Functional\Render\Element;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the markup of lingotek render element types passed to \Drupal::service('renderer')->render().
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
  protected function setUp(): void {
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
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    $this->applyEntityUpdates();
    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $this->saveLingotekContentTranslationSettingsForNodeTypes(['article'], 'manual');
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
    $this->drupalGet('/lingotek_form_test/lingotek_source_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-untracked" title="Upload"><a href="' . $basepath . '/admin/lingotek/entity/upload/node/1?destination=' . $basepath . '/lingotek_form_test/lingotek_source_status/node/1">EN</a></span>');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_IMPORTING);
    $translation_service->setDocumentId($entity, 'test-document-id');
    $this->drupalGet('/lingotek_form_test/lingotek_source_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-importing" title="Source importing"><a href="' . $basepath . '/admin/lingotek/entity/check_upload/test-document-id?destination=' . $basepath . '/lingotek_form_test/lingotek_source_status/node/1">EN</a></span>');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
    $this->drupalGet('/lingotek_form_test/lingotek_source_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-current" title="Source uploaded">EN</span>');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_DISABLED);
    $this->drupalGet('/lingotek_form_test/lingotek_source_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-disabled" title="Disabled, cannot request translation">EN</span>');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_EDITED);
    $this->drupalGet('/lingotek_form_test/lingotek_source_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-edited" title="Re-upload (content has changed since last upload)"><a href="' . $basepath . '/admin/lingotek/entity/update/test-document-id?destination=' . $basepath . '/lingotek_form_test/lingotek_source_status/node/1">EN</a></span>');

    $translation_service->setSourceStatus($entity, Lingotek::STATUS_ERROR);
    $this->drupalGet('/lingotek_form_test/lingotek_source_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $this->assertSession()->responseContains('<span class="language-icon source-error" title="Error"><a href="' . $basepath . '/admin/lingotek/entity/update/test-document-id?destination=' . $basepath . '/lingotek_form_test/lingotek_source_status/node/1">EN</a></span>');
  }

  /**
   * Tests #type 'lingotek_source_statuses'.
   */
  public function testLingotekTargetStatuses() {
    $basepath = \Drupal::request()->getBasePath();

    /** @var \Drupal\node\NodeInterface $entity */
    $entity = Node::create(['id' => 1, 'title' => 'Llamas are cool', 'type' => 'article']);
    $entity->save();

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $this->drupalGet('/lingotek_form_test/lingotek_translation_statuses/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    // Assert there are no language icons as there's nothing to display yet.
    $this->assertSession()->elementNotExists('xpath', '//div[contains(@class, "region-content")]/span[contains(@class, "language-icon")]');
    $this->assertSession()->elementNotExists('xpath', '//div[contains(@class, "region-content")]/a[contains(@class, "language-icon")]');

    $translation_service->setTargetStatus($entity, 'es', Lingotek::STATUS_UNTRACKED);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_statuses/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $link = $this->xpath("//span[@class='language-icon target-untracked' and @title='Spanish - Translation exists, but it is not being tracked by Lingotek' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Span exists.');

    $translation_service->setDocumentId($entity, 'test-document-id');

    $translation_service->setTargetStatus($entity, 'es', Lingotek::STATUS_REQUEST);
    $translation_service->setTargetStatus($entity, 'de', Lingotek::STATUS_PENDING);
    $translation_service->setTargetStatus($entity, 'ca', Lingotek::STATUS_READY);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_statuses/node/1');

    $this->assertTargetAction("Check translation status",
      "$basepath/admin/lingotek/entity/check_target/test-document-id/de_DE?destination=$basepath/lingotek_form_test/lingotek_translation_statuses/node/1"
      );
    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/de_DE"
    );
    $this->assertTargetAction("Request translation",
      "$basepath/admin/lingotek/entity/add_target/test-document-id/es_ES?destination=$basepath/lingotek_form_test/lingotek_translation_statuses/node/1"
    );
    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/ca_ES"
    );
    $this->assertTargetAction("Download translation",
      "$basepath/admin/lingotek/entity/download/test-document-id/ca_ES?destination=$basepath/lingotek_form_test/lingotek_translation_statuses/node/1"
    );

    $this->assertSession()->responseContains('lingotek/css/base.css');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/download/test-document-id/ca_ES?destination=" . $basepath . "/lingotek_form_test/lingotek_translation_statuses/node/1' and @class='language-icon target-ready' and @title='Catalan - Ready for Download' and text()='CA']");
    $this->assertEqual(count($link), 1, 'Link exists.');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/check_target/test-document-id/de_DE?destination=" . $basepath . "/lingotek_form_test/lingotek_translation_statuses/node/1' and @class='language-icon target-pending' and @title='German - In-progress' and text()='DE']");
    $this->assertEqual(count($link), 1, 'Link exists.');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/add_target/test-document-id/es_ES?destination=" . $basepath . "/lingotek_form_test/lingotek_translation_statuses/node/1' and @class='language-icon target-request' and @title='Spanish - Request translation' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Link exists.');

    $translation_service->setTargetStatus($entity, 'es', Lingotek::STATUS_INTERMEDIATE);
    $translation_service->setTargetStatus($entity, 'de', Lingotek::STATUS_CURRENT);
    $translation_service->setTargetStatus($entity, 'ca', Lingotek::STATUS_EDITED);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_statuses/node/1');

    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/de_DE"
    );
    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/es_ES"
    );
    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/ca_ES"
    );

    $this->assertSession()->responseContains('lingotek/css/base.css');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/test-document-id/ca_ES' and @target='_blank' and @class='language-icon target-edited' and @title='Catalan - Not current' and text()='CA']");
    $this->assertEqual(count($link), 1, 'Link exists.');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/test-document-id/de_DE' and @target='_blank' and @class='language-icon target-current' and @title='German - Current' and text()='DE']");
    $this->assertEqual(count($link), 1, 'Link exists.');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/test-document-id/es_ES' and @target='_blank' and @class='language-icon target-intermediate' and @title='Spanish - In-progress (interim translation downloaded)' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Link exists.');

    $translation_service->setTargetStatus($entity, 'es', Lingotek::STATUS_ERROR);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_statuses/node/1');

    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/de_DE"
    );
    $this->assertTargetAction("Retry request",
      "$basepath/admin/lingotek/entity/add_target/test-document-id/es_ES?destination=$basepath/lingotek_form_test/lingotek_translation_statuses/node/1"
    );
    $this->assertTargetAction("Retry download",
      "$basepath/admin/lingotek/entity/download/test-document-id/es_ES?destination=$basepath/lingotek_form_test/lingotek_translation_statuses/node/1"
    );

    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/ca_ES"
    );

    $this->assertSession()->responseContains('lingotek/css/base.css');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/download/test-document-id/es_ES?destination=" . $basepath . "/lingotek_form_test/lingotek_translation_statuses/node/1' and @class='language-icon target-error' and @title='Spanish - Error' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Link exists.');

    $translation_service->setTargetStatus($entity, 'es', Lingotek::STATUS_DISABLED);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_statuses/node/1');

    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/de_DE"
    );
    $this->assertNoTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/es_ES"
    );
    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/ca_ES"
    );

    $this->assertSession()->responseContains('lingotek/css/base.css');
    $link = $this->xpath("//span[@class='language-icon target-disabled' and @title='Spanish - Disabled' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Span exists.');
  }

  /**
   * Tests #type 'lingotek_source_status'.
   */
  public function testLingotekTargetStatus() {
    $basepath = \Drupal::request()->getBasePath();

    /** @var \Drupal\node\NodeInterface $entity */
    $entity = Node::create(['id' => 1, 'title' => 'Llamas are cool', 'type' => 'article']);
    $entity->save();

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');
    $this->assertSession()->responseNotContains('lingotek/css/base.css');
    // Assert there are no language icons as there's nothing to display yet.
    $this->assertSession()->elementNotExists('xpath', '//div[contains(@class, "region-content")]/span[contains(@class, "language-icon")]');
    $this->assertSession()->elementNotExists('xpath', '//div[contains(@class, "region-content")]/a[contains(@class, "language-icon")]');

    $translation_service->setTargetStatus($entity, 'es', Lingotek::STATUS_UNTRACKED);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');
    $this->assertSession()->responseContains('lingotek/css/base.css');
    $link = $this->xpath("//span[@class='language-icon target-untracked' and @title='Spanish - Translation exists, but it is not being tracked by Lingotek' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Span exists.');

    $translation_service->setDocumentId($entity, 'test-document-id');

    $translation_service->setTargetStatus($entity, 'es', Lingotek::STATUS_REQUEST);
    $translation_service->setTargetStatus($entity, 'de', Lingotek::STATUS_PENDING);
    $translation_service->setTargetStatus($entity, 'ca', Lingotek::STATUS_READY);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');

    $this->assertTargetAction("Check translation status",
      "$basepath/admin/lingotek/entity/check_target/test-document-id/de_DE?destination=$basepath/lingotek_form_test/lingotek_translation_status/node/1"
    );
    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/de_DE"
    );
    $this->assertTargetAction("Request translation",
      "$basepath/admin/lingotek/entity/add_target/test-document-id/es_ES?destination=$basepath/lingotek_form_test/lingotek_translation_status/node/1"
    );
    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/ca_ES"
    );
    $this->assertTargetAction("Download translation",
      "$basepath/admin/lingotek/entity/download/test-document-id/ca_ES?destination=$basepath/lingotek_form_test/lingotek_translation_status/node/1"
    );

    $this->assertSession()->responseContains('lingotek/css/base.css');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/download/test-document-id/ca_ES?destination=" . $basepath . "/lingotek_form_test/lingotek_translation_status/node/1' and @class='language-icon target-ready' and @title='Catalan - Ready for Download' and text()='CA']");
    $this->assertEqual(count($link), 1, 'Link exists.');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/check_target/test-document-id/de_DE?destination=" . $basepath . "/lingotek_form_test/lingotek_translation_status/node/1' and @class='language-icon target-pending' and @title='German - In-progress' and text()='DE']");
    $this->assertEqual(count($link), 1, 'Link exists.');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/add_target/test-document-id/es_ES?destination=" . $basepath . "/lingotek_form_test/lingotek_translation_status/node/1' and @class='language-icon target-request' and @title='Spanish - Request translation' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Link exists.');

    $translation_service->setTargetStatus($entity, 'es', Lingotek::STATUS_INTERMEDIATE);
    $translation_service->setTargetStatus($entity, 'de', Lingotek::STATUS_CURRENT);
    $translation_service->setTargetStatus($entity, 'ca', Lingotek::STATUS_EDITED);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');

    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/de_DE"
    );
    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/es_ES"
    );
    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/ca_ES"
    );

    $this->assertSession()->responseContains('lingotek/css/base.css');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/test-document-id/ca_ES' and @target='_blank' and @class='language-icon target-edited' and @title='Catalan - Not current' and text()='CA']");
    $this->assertEqual(count($link), 1, 'Link exists.');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/test-document-id/de_DE' and @target='_blank' and @class='language-icon target-current' and @title='German - Current' and text()='DE']");
    $this->assertEqual(count($link), 1, 'Link exists.');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/workbench/test-document-id/es_ES' and @target='_blank' and @class='language-icon target-intermediate' and @title='Spanish - In-progress (interim translation downloaded)' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Link exists.');

    $translation_service->setTargetStatus($entity, 'es', Lingotek::STATUS_ERROR);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');

    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/de_DE"
    );
    $this->assertTargetAction("Retry request",
      "$basepath/admin/lingotek/entity/add_target/test-document-id/es_ES?destination=$basepath/lingotek_form_test/lingotek_translation_status/node/1"
    );
    $this->assertTargetAction("Retry download",
      "$basepath/admin/lingotek/entity/download/test-document-id/es_ES?destination=$basepath/lingotek_form_test/lingotek_translation_status/node/1"
    );

    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/ca_ES"
    );

    $this->assertSession()->responseContains('lingotek/css/base.css');
    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/download/test-document-id/es_ES?destination=" . $basepath . "/lingotek_form_test/lingotek_translation_status/node/1' and @class='language-icon target-error' and @title='Spanish - Error' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Link exists.');

    $translation_service->setTargetStatus($entity, 'es', Lingotek::STATUS_DISABLED);
    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');

    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/de_DE"
    );
    $this->assertNoTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/es_ES"
    );
    $this->assertTargetAction("Open in Lingotek Workbench",
      "$basepath/admin/lingotek/workbench/test-document-id/ca_ES"
    );

    $this->assertSession()->responseContains('lingotek/css/base.css');
    $link = $this->xpath("//span[@class='language-icon target-disabled' and @title='Spanish - Disabled' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Span exists.');
  }

  protected function assertTargetAction($text, $url) {
    // Should be better with ul[@class="lingotek-target-actions"], but somehow doesn't work.
    $link = $this->xpath('//ul//li//a[@href="' . $url . '" and text()="' . $text . '"]');
    $this->assertCount(1, $link, 'Action exists.');
  }

  protected function assertNoTargetAction($text, $url) {
    // Should be better with ul[@class="lingotek-target-actions"], but somehow doesn't work.
    $link = $this->xpath('//ul//li//a[@href="' . $url . '" and text()="' . $text . '"]');
    $this->assertCount(0, $link, 'Action exists.');
  }

}
