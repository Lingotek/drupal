<?php

namespace Drupal\lingotek\Tests\Form;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\simpletest\WebTestBase;

/**
 * Test the Drupal language form alters.
 *
 * @group lingotek
 */
class LanguageFormTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['lingotek', 'lingotek_test'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // User to add and remove language.
    $admin_user = $this->drupalCreateUser(array('administer languages', 'access administration pages'));
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests editing a defined language has the right locale.
   */
  public function testEditingLanguage() {
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->drupalGet('/admin/config/regional/language');
    // Click on edit for German.
    $this->clickLink('Edit', 1);

    // Assert that the locale is correct.
    $this->assertFieldByName('lingotek_locale', 'de-DE', 'The Lingotek locale is set correctly.');
  }

  /**
   * Tests adding a custom language with a custom locale.
   */
  public function testAddingCustomLanguage() {
    // Check that there is a select for locales.
    $this->drupalGet('admin/config/regional/language/add');
    $this->assertField('lingotek_locale', 'There is a field for adding the Lingotek locale.');

    // Assert that the locale is empty.
    $this->assertFieldByName('lingotek_locale', '', 'The Lingotek locale is empty by default.');

    $edit = [
      'predefined_langcode' => 'custom',
      'langcode' => 'es-DE',
      'label' => 'Spanish (Germany)',
      'direction' => 'ltr',
      'lingotek_locale' => 'es-ES',
    ];
    $this->drupalPostForm(NULL, $edit, 'Add custom language');
    $this->assertText('The language Spanish (Germany) has been created and can now be used.');

    // Ensure the language is created and with the right locale.
    $language = ConfigurableLanguage::load('es-DE');
    $this->assertEqual('es_ES', $language->getThirdPartySetting('lingotek', 'locale'), 'The Lingotek locale has been saved successfully.');

    // Ensure the locale and langcode are correctly mapped.
    /** @var LanguageLocaleMapperInterface $locale_mapper */
    $locale_mapper = \Drupal::service('lingotek.language_locale_mapper');
    $this->assertEqual('es_ES', $locale_mapper->getLocaleForLangcode('es-DE'), 'The language locale mapper correctly guesses the locale.');
    $this->assertEqual('es-DE', $locale_mapper->getConfigurableLanguageForLocale('es_ES')->getId(), 'The language locale mapper correctly guesses the langcode.');
  }

  /**
   * Tests editing a custom language with a custom locale.
   */
  public function testEditingCustomLanguage() {
    ConfigurableLanguage::create(['id' => 'de-at', 'label' => 'German (AT)'])->save();
    $this->drupalGet('/admin/config/regional/language');

    // Click on edit for German (AT).
    $this->clickLink('Edit', 1);

    // Assert that the locale is correct.
    $this->assertFieldByName('lingotek_locale', 'de-AT', 'The Lingotek locale is set to the right language.');

    // Edit the locale.
    $edit = ['lingotek_locale' => 'de-DE'];
    $this->drupalPostForm(NULL, $edit, 'Save language');

    // Click again on edit for German (AT).
    $this->clickLink('Edit', 1);
    // Assert that the locale is correct.
    $this->assertFieldByName('lingotek_locale', 'de-DE', 'The Lingotek locale is set to the right language after editing.');
  }

  /**
   * Tests editing a custom language with a custom locale.
   */
  public function testEditingCustomLanguageWithWrongLocale() {
    ConfigurableLanguage::create(['id' => 'de-at', 'label' => 'German (AT)'])->save();
    $this->drupalGet('/admin/config/regional/language');

    // Click on edit for German (AT).
    $this->clickLink('Edit', 1);

    // Assert that the locale is correct.
    $this->assertFieldByName('lingotek_locale', 'de-AT', 'The Lingotek locale is set to the right language.');

    // Edit the locale.
    $edit = ['lingotek_locale' => 'de-IN'];
    $this->drupalPostForm(NULL, $edit, 'Save language');
    $this->assertText('The Lingotek locale de-IN does not exist.');
  }

}
