<?php

namespace Drupal\Tests\lingotek\Functional\FieldFormatters;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\lingotek\Lingotek;
use Drupal\node\Entity\Node;
use Drupal\Tests\lingotek\Functional\LingotekTestBase;

/**
 * Tests the Lingotek translation statuses field formatter.
 *
 * @group lingotek
 */
class LingotekTranslationStatusesFormatterTest extends LingotekTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'lingotek_visitable_metadata_statuses'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create Article node types.
    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();
    ConfigurableLanguage::createFromLangcode('de')->setThirdPartySetting('lingotek', 'locale', 'de_DE')->save();

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
   * Tests the Lingotek translation statuses field formatter.
   */
  public function testLingotekSourceStatusFormatter() {
    $basepath = \Drupal::request()->getBasePath();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);
    $this->assertSession()->addressEquals('/node/1');

    $this->drupalGet('/metadata/1');
    $this->assertSession()->responseNotContains('Lingotek translation status');

    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/add_target/dummy-document-hash-id/de_DE?destination=" . $basepath . "/metadata/1' and @class='language-icon target-request' and @title='German - Request translation' and text()='DE']");
    $this->assertEqual(count($link), 1, 'Link exists.');

    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=" . $basepath . "/metadata/1' and @class='language-icon target-request' and @title='Spanish - Request translation' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Link exists.');
  }

  public function testStatusForMissingLanguage() {
    $basepath = \Drupal::request()->getBasePath();

    // Create a node.
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['lingotek_translation_management[lingotek_translation_profile]'] = 'automatic';
    $this->saveAndPublishNodeForm($edit);
    $this->assertSession()->addressEquals('/node/1');

    $node = Node::load(1);
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $service */
    $service = \Drupal::service('lingotek.content_translation');
    $service->setTargetStatus($node, 'nb_NO', Lingotek::STATUS_READY);

    $this->drupalGet('/metadata/1');
    $this->assertSession()->responseNotContains('Lingotek translation status');

    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/add_target/dummy-document-hash-id/de_DE?destination=" . $basepath . "/metadata/1' and @class='language-icon target-request' and @title='German - Request translation' and text()='DE']");
    $this->assertEqual(count($link), 1, 'Link exists.');

    $link = $this->xpath("//a[@href='$basepath/admin/lingotek/entity/add_target/dummy-document-hash-id/es_MX?destination=" . $basepath . "/metadata/1' and @class='language-icon target-request' and @title='Spanish - Request translation' and text()='ES']");
    $this->assertEqual(count($link), 1, 'Link exists.');
  }

}
