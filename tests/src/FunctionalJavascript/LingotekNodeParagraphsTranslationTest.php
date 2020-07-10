<?php

namespace Drupal\Tests\lingotek\FunctionalJavascript;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * @group lingotek
 * @group legacy
 */
class LingotekNodeParagraphsTranslationTest extends LingotekFunctionalJavascriptTestBase {

  use ContentModerationTestTrait;

  protected $paragraphsTranslatable = FALSE;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['block', 'content_moderation', 'workflows', 'node', 'image', 'comment', 'paragraphs', 'lingotek_paragraphs_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the actions and title block.
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();
    ConfigurableLanguage::createFromLangcode('es-ar')->setThirdPartySetting('lingotek', 'locale', 'es_AR')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'paragraphed_content_demo')->setLanguageAlterable(TRUE)->save();
    ContentLanguageSettings::loadByEntityTypeBundle('paragraph', 'image_text')->setLanguageAlterable(TRUE)->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'paragraphed_content_demo', TRUE);

    if ($this->paragraphsTranslatable) {
      $this->setParagraphFieldsTranslatability();
    }

    // Enable content moderation for articles.
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'paragraphed_content_demo');
    $workflow->save();

    $this->saveLingotekContentTranslationSettings([
      'node' => [
        'paragraphed_content_demo' => [
          'profiles' => 'automatic',
          'fields' => [
            'title' => 1,
            'field_paragraphs_demo' => 1,
          ],
          'moderation' => [
            'upload_status' => 'published',
            'download_transition' => 'publish',
          ],
        ],
      ],
      'paragraph' => [
        'image_text' => [
          'fields' => [
            'field_image_demo' => ['title', 'alt'],
            'field_text_demo' => 1,
          ],
        ],
      ],
    ]);

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs');
  }

  /**
   * Tests that a node can be translated.
   */
  public function testNodeWithParagraphsTranslation() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $messages_locator = '.messages--status';

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');
    $page->pressButton('field_paragraphs_demo_image_text_add_more');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('css', '[name="field_paragraphs_demo[0][subform][field_text_demo][0][value]"]');
    $page->pressButton('field_paragraphs_demo_image_text_add_more');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('css', '[name="field_paragraphs_demo[1][subform][field_text_demo][0][value]"]');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool';
    $edit['moderation_state[0][state]'] = 'published';
    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEqual($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEqual($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $toggle = $page->find('css', 'li.dropbutton-toggle button');
    $toggle->click();
    $dropButton = $page->find('css', 'li.check-upload-status.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, 'The import for node Llamas are cool is complete.');

    // Request translation.
    $toggle = $page->findAll('css', 'li.dropbutton-toggle button')[1];
    $toggle->click();
    $dropButton = $page->find('css', 'li.request-translation.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, "Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $toggle = $page->findAll('css', 'li.dropbutton-toggle button')[1];
    $toggle->click();
    $dropButton = $page->find('css', 'li.check-translation-status.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, 'The es_AR translation for node Llamas are cool is ready for download.');

    // Download translation.
    $toggle = $page->findAll('css', 'li.dropbutton-toggle button')[1];
    $toggle->click();
    $dropButton = $page->find('css', 'li.download-completed-translation.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, 'The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $assert_session->pageTextContains('Las llamas son chulas');
    $assert_session->pageTextContains('Las llamas son muy chulas');
  }

  /**
   * Paragraphs don't have a title, so we should disallow filtering by it.
   */
  public function testParagraphIsRemovedFromTranslationIfSourceIsRemoved() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $messages_locator = '.messages--status';

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs_multiple_before_removal');

    // Add paragraphed content.
    $this->drupalGet('node/add/paragraphed_content_demo');
    $page->pressButton('field_paragraphs_demo_image_text_add_more');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('css', '[name="field_paragraphs_demo[0][subform][field_text_demo][0][value]"]');
    $page->pressButton('field_paragraphs_demo_image_text_add_more');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('css', '[name="field_paragraphs_demo[1][subform][field_text_demo][0][value]"]');
    $page->pressButton('field_paragraphs_demo_image_text_add_more');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('css', '[name="field_paragraphs_demo[2][subform][field_text_demo][0][value]"]');

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time';
    $edit['field_paragraphs_demo[1][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the second time';
    $edit['field_paragraphs_demo[2][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the third time';
    $edit['moderation_state[0][state]'] = 'published';

    $this->drupalPostForm(NULL, $edit, t('Save'));

    // Check that only the configured fields have been uploaded, including metatags.
    $data = json_decode(\Drupal::state()->get('lingotek.uploaded_content', '[]'), TRUE);
    $this->assertEqual($data['title'][0]['value'], 'Llamas are cool');
    $this->assertEqual($data['field_paragraphs_demo'][0]['field_text_demo'][0]['value'], 'Llamas are very cool for the first time');
    $this->assertEqual($data['field_paragraphs_demo'][1]['field_text_demo'][0]['value'], 'Llamas are very cool for the second time');
    $this->assertEqual($data['field_paragraphs_demo'][2]['field_text_demo'][0]['value'], 'Llamas are very cool for the third time');

    // Check that the url used was the right one.
    $uploaded_url = \Drupal::state()->get('lingotek.uploaded_url');
    $this->assertIdentical(\Drupal::request()->getUriForPath('/node/1'), $uploaded_url, 'The node url was used.');

    // Check that the profile used was the right one.
    $used_profile = \Drupal::state()->get('lingotek.used_profile');
    $this->assertIdentical('automatic', $used_profile, 'The automatic profile was used.');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically uploaded, so let's check
    // the upload status.
    $toggle = $page->find('css', 'li.dropbutton-toggle button');
    $toggle->click();
    $dropButton = $page->find('css', 'li.check-upload-status.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, 'The import for node Llamas are cool is complete.');

    // Request translation.
    $toggle = $page->findAll('css', 'li.dropbutton-toggle button')[1];
    $toggle->click();
    $dropButton = $page->find('css', 'li.request-translation.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, "Locale 'es_AR' was added as a translation target for node Llamas are cool.");

    // Check translation status.
    $toggle = $page->findAll('css', 'li.dropbutton-toggle button')[1];
    $toggle->click();
    $dropButton = $page->find('css', 'li.check-translation-status.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, 'The es_AR translation for node Llamas are cool is ready for download.');

    // Download translation.
    $toggle = $page->findAll('css', 'li.dropbutton-toggle button')[1];
    $toggle->click();
    $dropButton = $page->find('css', 'li.download-completed-translation.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, 'The translation of node Llamas are cool into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas');
    $assert_session->pageTextContains('Las llamas son chulas');
    $assert_session->pageTextContains('Las llamas son muy chulas por primera vez');
    $assert_session->pageTextContains('Las llamas son muy chulas por segunda vez');
    $assert_session->pageTextContains('Las llamas son muy chulas por tercera vez');

    // This is a hack for avoiding writing different lingotek endpoint mocks.
    \Drupal::state()->set('lingotek.uploaded_content_type', 'node+paragraphs_multiple_after_removal');

    $this->drupalGet('node/1/edit');

    $page->pressButton('field_paragraphs_demo_1_remove');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('named', ['id_or_name', 'field_paragraphs_demo_1_confirm_remove']);
    $page->pressButton('field_paragraphs_demo_1_confirm_remove');
    $assert_session->assertWaitOnAjaxRequest();

    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool EDITED';
    $edit['langcode[0][value]'] = 'en';
    $edit['field_paragraphs_demo[0][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the first time EDITED';
    $edit['field_paragraphs_demo[2][subform][field_text_demo][0][value]'] = 'Llamas are very cool for the third time EDITED';
    $this->drupalPostForm(NULL, $edit, t('Save (this translation)'));

    $assert_session->pageTextContains('Llamas are cool EDITED');
    $assert_session->pageTextContains('Llamas are very cool for the first time EDITED');
    $assert_session->pageTextNotContains('Llamas are very cool for the second time EDITED');
    $assert_session->pageTextContains('Llamas are very cool for the third time EDITED');

    // Check that the translate tab is in the node.
    $this->drupalGet('node/1');
    $this->clickLink('Translate');

    // The document should have been automatically updated, so let's check
    // the upload status.
    $toggle = $page->find('css', 'li.dropbutton-toggle button');
    $toggle->click();
    $dropButton = $page->find('css', 'li.check-upload-status.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, 'The import for node Llamas are cool EDITED is complete.');

    // Check translation status.
    $toggle = $page->findAll('css', 'li.dropbutton-toggle button')[1];
    $toggle->click();
    $dropButton = $page->find('css', 'li.check-translation-status.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, 'The es_AR translation for node Llamas are cool EDITED is ready for download.');

    // Download translation.
    $toggle = $page->findAll('css', 'li.dropbutton-toggle button')[1];
    $toggle->click();
    $dropButton = $page->find('css', 'li.download-completed-translation.dropbutton-action a');
    $dropButton->click();

    // This is a batch process, wait to finish.
    $assert_session->waitForElementVisible('css', $messages_locator);

    $assert_session->elementTextContains('css', $messages_locator, 'The translation of node Llamas are cool EDITED into es_AR has been downloaded.');

    // The content is translated and published.
    $this->clickLink('Las llamas son chulas EDITADO');
    $assert_session->pageTextContains('Las llamas son chulas EDITADO');
    $assert_session->pageTextContains('Las llamas son muy chulas por primera vez EDITADO.');
    $assert_session->pageTextNotContains('Las llamas son muy chulas por segunda vez EDITADO.');
    $assert_session->pageTextContains('Las llamas son muy chulas por tercera vez EDITADO.');
    $assert_session->pageTextNotContains('Las llamas son muy chulas por tercera vez.');

    $paragraphs = $page->findAll('css', '.paragraph');
    $this->assertCount(2, $paragraphs);
  }

  public function testEditingAfterNodeWithParagraphsTranslation() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $messages_locator = '.messages--status';

    $this->testNodeWithParagraphsTranslation();

    $this->drupalGet('es-ar/node/1/edit');
    $assert_session->fieldValueEquals('field_paragraphs_demo[0][subform][field_text_demo][0][value]', 'Las llamas son muy chulas');

    $this->drupalGet('node/1/edit');
    $assert_session->fieldValueEquals('field_paragraphs_demo[0][subform][field_text_demo][0][value]', 'Llamas are very cool');

    $this->drupalPostForm(NULL, NULL, 'Remove');
    $assert_session->waitForElementVisible('css', 'field_paragraphs_demo_0_confirm_remove', 1000);
    $this->drupalPostForm(NULL, NULL, 'Confirm removal');
    $assert_session->waitForElementRemoved('css', 'field_paragraphs_demo_0_confirm_remove', 1000);

    $this->drupalPostForm(NULL, NULL, 'Save (this translation)');
    $assert_session->waitForElementVisible('css', $messages_locator);
    $assert_session->pageTextContains('Paragraphed article Llamas are cool has been updated.');
  }

  protected function setParagraphFieldsTranslatability(): void {
    $edit = [];
    $edit['settings[node][paragraphed_content_demo][fields][field_paragraphs_demo]'] = 1;
    $edit['settings[paragraph][image_text][fields][field_text_demo]'] = 1;
    $this->drupalPostForm('/admin/config/regional/content-language', $edit, 'Save configuration');
    $this->assertSession()->responseContains('Settings successfully updated.');
  }

}
