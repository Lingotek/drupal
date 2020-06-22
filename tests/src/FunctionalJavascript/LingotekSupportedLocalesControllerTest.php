<?php

namespace Drupal\Tests\lingotek\FunctionalJavascript;

/**
 * @group lingotek
 */
class LingotekSupportedLocalesControllerTest extends LingotekFunctionalJavascriptTestBase {

  /**
   * Tests that the supported locales are rendered.
   */
  public function testFilterSupportedLocales() {
    $this->drupalGet('/admin/lingotek/supported-locales');

    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // There are three languages.
    $assert->elementsCount('css', 'table.locales-listing-table tbody tr', 3);

    // And the text filter is empty.
    $field_locator = 'input.locales-filter-text';
    $filter_field = $assert->waitForElementVisible('css', $field_locator);
    $filter_field->focus();
    $search = $filter_field->getValue();

    $this->assertEmpty($search);

    // Filter German languages.
    $assert->waitForElementVisible('css', $field_locator)->setValue('German');
    $trs = $page->findAll('css', 'table.locales-listing-table tbody tr');
    $this->assertCount(3, $trs);

    // There are two German languages.
    $trs = $this->filterVisibleElements($trs);
    $this->assertCount(2, $trs);

    // Filter Austrian languages.
    $assert->waitForElementVisible('css', $field_locator)->setValue('Austria');
    $trs = $page->findAll('css', 'table.locales-listing-table tbody tr');
    $this->assertCount(3, $trs);

    // There is only one language from Austria.
    $trs = $this->filterVisibleElements($trs);
    $this->assertCount(1, $trs);
  }

  /**
   * Removes any non-visible elements from the passed array.
   *
   * @param array $elements
   *
   * @return array
   */
  protected function filterVisibleElements($elements) {
    $elements = array_filter($elements, function ($element) {
      return $element->isVisible();
    });
    return $elements;
  }

}
