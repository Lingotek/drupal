<?php

namespace Drupal\Tests\lingotek\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;

/**
 * @group lingotek
 */
class LingotekTargetActionsTest extends LingotekFunctionalJavascriptTestBase {

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
    $this->drupalPlaceBlock('page_title_block', ['region' => 'content', 'weight' => -5]);
    $this->drupalPlaceBlock('local_tasks_block', ['region' => 'content', 'weight' => -10]);

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);

    // Add locales.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_ES')->save();
    ConfigurableLanguage::createFromLangcode('it')->setThirdPartySetting('lingotek', 'locale', 'it_IT')->save();

    // Enable translation for the current entity type and ensure the change is
    // picked up.
    ContentLanguageSettings::loadByEntityTypeBundle('node', 'article')
      ->setLanguageAlterable(TRUE)
      ->save();
    \Drupal::service('content_translation.manager')
      ->setEnabled('node', 'article', TRUE);

    $this->saveLingotekContentTranslationSettingsForNodeTypes();
  }

  /**
   * Tests that the supported locales are rendered.
   */
  public function testDropdownTargetStatus() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Add paragraphed content.
    $this->drupalGet('node/add/article');
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $this->drupalPostForm(NULL, $edit, t('Save'));

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translationService = \Drupal::service('lingotek.content_translation');

    $this->drupalGet('/lingotek_form_test/lingotek_translation_status/node/1');

    $buttonLocator = 'button.lingotek-target-dropdown-toggle';
    $listLocator = 'ul.lingotek-target-actions';
    // There are two dropdowns.
    $assert_session->elementsCount('css', $buttonLocator, 2);
    $assert_session->elementsCount('css', $listLocator, 2);

    $lists = $page->findAll('css', $listLocator);
    $visibleLists = $this->filterVisibleElements($lists);
    $this->assertCount(0, $visibleLists);

    $button_field = $assert_session->elementExists('css', $buttonLocator);
    $button_field->click();

    $listShown = $this->waitForVisibleElementCount(1, $listLocator);
    $this->assertTrue($listShown);

    $visibleLists = $this->filterVisibleElements($lists);
    $this->assertCount(1, $visibleLists);
  }

  /**
   * Tests that the supported locales are rendered.
   */
  public function testDropdownTargetStatuses() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Add paragraphed content.
    $this->drupalGet('node/add/article');
    $edit = [];
    $edit['title[0][value]'] = 'Llamas are cool';
    $edit['langcode[0][value]'] = 'en';
    $edit['body[0][value]'] = 'Llamas are very cool';
    $this->drupalPostForm(NULL, $edit, t('Save'));

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translationService = \Drupal::service('lingotek.content_translation');

    $this->drupalGet('/lingotek_form_test/lingotek_translation_statuses/node/1');

    $buttonLocator = 'button.lingotek-target-dropdown-toggle';
    $listLocator = 'ul.lingotek-target-actions';
    // There are two dropdowns.
    $assert_session->elementsCount('css', $buttonLocator, 2);
    $assert_session->elementsCount('css', $listLocator, 2);

    $lists = $page->findAll('css', $listLocator);
    $visibleLists = $this->filterVisibleElements($lists);
    $this->assertCount(0, $visibleLists);

    $button_field = $assert_session->elementExists('css', $buttonLocator);
    $button_field->click();

    $listShown = $this->waitForVisibleElementCount(1, $listLocator);
    $this->assertTrue($listShown);

    $visibleLists = $this->filterVisibleElements($lists);
    $this->assertCount(1, $visibleLists);
  }

  /**
   * Removes any non-visible elements from the passed array.
   *
   * @param \Behat\Mink\Element\NodeElement[] $elements
   *   An array of node elements.
   *
   * @return \Behat\Mink\Element\NodeElement[]
   */
  protected function filterVisibleElements(array $elements) {
    $elements = array_filter($elements, function (NodeElement $element) {
      return $element->isVisible();
    });
    return $elements;
  }

  /**
   * Waits for the specified number of items to be visible.
   *
   * @param int $count
   *   The number of found elements to wait for.
   * @param string|array $locator
   *   The selector locator.
   * @param int $timeout
   *   (Optional) Timeout in milliseconds, defaults to 10000.
   *
   * @return bool
   *   TRUE if the required number was matched, FALSE otherwise.
   */
  protected function waitForVisibleElementCount($count, $locator, $timeout = 10000) {
    $page = $this->getSession()->getPage();

    return $page->waitFor($timeout / 1000, function () use ($count, $page, $locator) {
      $elements = $page->findAll('css', $locator);
      $visible_elements = $this->filterVisibleElements($elements);
      if (count($visible_elements) === $count) {
        return TRUE;
      }
      return FALSE;
    });
  }

}
