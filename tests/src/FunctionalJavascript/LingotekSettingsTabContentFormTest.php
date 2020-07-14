<?php

namespace Drupal\Tests\lingotek\FunctionalJavascript;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * @group lingotek
 */
class LingotekSettingsTabContentFormTest extends LingotekFunctionalJavascriptTestBase {

  public static $modules = ['block', 'node', 'field_ui', 'image'];

  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', [
      'region' => 'content',
      'weight' => -5,
    ]);
    $this->drupalPlaceBlock('local_actions_block', [
      'region' => 'content',
      'weight' => -10,
    ]);

    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')
      ->setThirdPartySetting('lingotek', 'locale', 'es_MX')
      ->save();

    // Create Article node types.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $this->createImageField('field_image', 'article');
    $this->createImageField('user_picture', 'user', 'user');
    $this->createTextField('field_text', 'article');

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);
    ContentLanguageSettings::loadByEntityTypeBundle('user', 'user')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('user', 'user', TRUE);

  }

  public function testWhenEnabledNodeArticleDefaultsAreSet() {
    $this->drupalGet('/admin/lingotek/settings');

    $page = $this->getSession()->getPage();
    $contentTabDetails = $page->find('css', '#edit-parent-details');
    $contentTabDetails->click();
    $nodeTabDetails = $page->find('css', '#edit-entity-node');
    $nodeTabDetails->click();

    $this->assertNoFieldChecked('edit-node-article-enabled');
    $this->assertNoFieldChecked('edit-node-article-fields-title');
    $this->assertNoFieldChecked('edit-node-article-fields-body');
    $this->assertNoFieldChecked('edit-node-article-fields-field-text');
    $this->assertNoFieldChecked('edit-node-article-fields-field-image');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-title');

    $fieldEnabled = $page->find('css', '#edit-node-article-enabled');
    $fieldEnabled->click();

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertFieldChecked('edit-node-article-enabled');
    $this->assertFieldChecked('edit-node-article-fields-title');
    $this->assertFieldChecked('edit-node-article-fields-body');
    $this->assertFieldChecked('edit-node-article-fields-field-text');
    $this->assertFieldChecked('edit-node-article-fields-field-image');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-title');

    $this->drupalPostForm(NULL, [], 'Save', [], 'lingoteksettings-tab-content-form');

    $this->assertSession()
      ->elementTextContains('css', '.messages.messages--status', 'The configuration options have been saved.');

    $this->assertFieldChecked('edit-node-article-enabled');
    $this->assertFieldChecked('edit-node-article-fields-title');
    $this->assertFieldChecked('edit-node-article-fields-body');
    $this->assertFieldChecked('edit-node-article-fields-field-text');
    $this->assertFieldChecked('edit-node-article-fields-field-image');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-title');
  }

  public function testWhenDisabledAndEnabledBackNodeArticleFieldsAreKept() {
    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'article' => [
          'profiles' => 'manual',
          'fields' => [
            'title' => 1,
            'uid' => 1,
            'field_image' => ['alt'],
          ],
        ],
      ],
    ]);

    $this->drupalGet('/admin/lingotek/settings');

    $page = $this->getSession()->getPage();
    $contentTabDetails = $page->find('css', '#edit-parent-details');
    $contentTabDetails->click();
    $nodeTabDetails = $page->find('css', '#edit-entity-node');
    $nodeTabDetails->click();

    $this->assertFieldChecked('edit-node-article-enabled');
    $this->assertFieldChecked('edit-node-article-fields-title');
    $this->assertNoFieldChecked('edit-node-article-fields-body');
    $this->assertFieldChecked('edit-node-article-fields-uid');
    $this->assertNoFieldChecked('edit-node-article-fields-field-text');
    $this->assertFieldChecked('edit-node-article-fields-field-image');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-title');

    $fieldEnabled = $page->find('css', '#edit-node-article-enabled');
    $fieldEnabled->click();

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertNoFieldChecked('edit-node-article-enabled');

    $fieldEnabled = $page->find('css', '#edit-node-article-enabled');
    $fieldEnabled->click();

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertFieldChecked('edit-node-article-enabled');
    $this->assertFieldChecked('edit-node-article-fields-title');
    // We marked body and field_text and kept the others as they were.
    $this->assertFieldChecked('edit-node-article-fields-body');
    $this->assertFieldChecked('edit-node-article-fields-uid');
    $this->assertFieldChecked('edit-node-article-fields-field-text');
    $this->assertFieldChecked('edit-node-article-fields-field-image');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-title');
  }

  public function testFieldPropertiesDisabledIfFieldDisabled() {
    $this->drupalGet('/admin/lingotek/settings');

    $page = $this->getSession()->getPage();
    $contentTabDetails = $page->find('css', '#edit-parent-details');
    $contentTabDetails->click();
    $nodeTabDetails = $page->find('css', '#edit-entity-node');
    $nodeTabDetails->click();

    $this->assertNoFieldChecked('edit-node-article-enabled');
    $this->assertNoFieldChecked('edit-node-article-fields-field-image');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-title');

    $imageCheckbox = $page->find('css', '#edit-node-article-fields-field-image');
    $imageCheckbox->click();

    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->assertFieldChecked('edit-node-article-fields-field-image');
    $this->assertNoFieldChecked('edit-node-article-fields-field-imageproperties-file');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-alt');
    $this->assertFieldChecked('edit-node-article-fields-field-imageproperties-title');
  }

}
