<?php

/**
 * @file
 * Contains \Drupal\lingotek\Tests\LingotekDashboardTest.
 */

namespace Drupal\lingotek\Tests;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the Lingotek dashboard.
 *
 * @group lingotek
 */
class LingotekDashboardTest extends LingotekTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['node', 'comment'];

  /**
   * Test that a language can be added.
   */
  public function testDashboardCanAddLanguage() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $post = [
      'code' => 'it_IT',
      'language' => 'Italian',
      'native' => 'Italiano',
      'direction' => '',
    ];
    $request = $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);
    $response = json_decode($request, true);

    $italian_language = ConfigurableLanguage::load('it');
    /** @var $italian_language ConfigurableLanguageInterface */
    $this->assertNotNull($italian_language, 'Italian language has been added.');
    $this->assertIdentical('Italian', $italian_language->getName());
    $this->assertIdentical(ConfigurableLanguage::DIRECTION_LTR, $italian_language->getDirection());

    // @ToDo: The native language is not saved.
    // $config_translation = \Drupal::languageManager()->getLanguageConfigOverride('it', $italian_language->id());

    $this->assertIdentical('it', $response['xcode']);
    $this->assertIdentical('it_IT', $response['locale']);
    $this->assertIdentical(1, $response['active']);
    $this->assertIdentical(1, $response['enabled']);
    $this->assertIdentical(0, $response['source']['total']);
    $this->assertIdentical(0, $response['target']['total']);
  }

  /**
   * Test that a language can be added.
   */
  public function testDashboardCanAddRTLLanguage() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $post = [
      'code' => 'ar_AE',
      'language' => 'Arabic',
      'native' => 'العربية',
      'direction' => 'RTL',
    ];
    $request = $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);
    $response = json_decode($request, true);

    $arabic_language = ConfigurableLanguage::load('ar');
    /** @var $italian_language ConfigurableLanguageInterface */
    $this->assertNotNull($arabic_language, 'Arabic language has been added.');
    $this->assertIdentical('Arabic', $arabic_language->getName());
    $this->assertIdentical(ConfigurableLanguage::DIRECTION_RTL, $arabic_language->getDirection());

    // @ToDo: The native language is not saved.
  }

  /**
   * Test that different locales from same language can be added.
   */
  public function testDashboardAddLanguageAndThenLocale() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish (Spain)',
      'native' => 'Español',
      'direction' => '',
    ];
    $request = $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);
    $response = json_decode($request, TRUE);

    $esEsLanguage = ConfigurableLanguage::load('es');
    /** @var $esEsLanguage ConfigurableLanguageInterface */
    $this->assertNotNull($esEsLanguage, 'Spanish (Spain) language has been added.');
    $this->assertIdentical('Spanish (Spain)', $esEsLanguage->getName());
    $this->assertIdentical(ConfigurableLanguage::DIRECTION_LTR, $esEsLanguage->getDirection());

    // The language must be returned in the dashboard.
    $request = $this->drupalGet('/admin/lingotek/dashboard_endpoint');
    $response = json_decode($request, TRUE);
    $returned_languages = array_keys($response['languages']);
    $this->assertIdentical(['en_US', 'es_ES'], $returned_languages);

    $post = [
      'code' => 'es_AR',
      'language' => 'Spanish (Argentina)',
      'native' => 'Español',
      'direction' => '',
    ];
    $request = $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);
    $response = json_decode($request, TRUE);

    $esArLanguage = ConfigurableLanguage::load('es-ar');
    /** @var $esArLanguage ConfigurableLanguageInterface */
    $this->assertNotNull($esArLanguage, 'Spanish (Argentina) language has been added.');
    $this->assertIdentical('Spanish (Argentina)', $esArLanguage->getName());
    $this->assertIdentical(ConfigurableLanguage::DIRECTION_LTR, $esArLanguage->getDirection());

    // The language must be returned in the dashboard.
    $request = $this->drupalGet('/admin/lingotek/dashboard_endpoint');
    $response = json_decode($request, TRUE);
    $returned_languages = array_keys($response['languages']);
    $this->assertIdentical(['en_US', 'es_AR', 'es_ES'], $returned_languages);
  }

  /**
   * Test that different locales from same language can be added.
   */
  public function testDashboardAddLocaleAndThenLanguage() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);


    $post = [
      'code' => 'es_AR',
      'language' => 'Spanish (Argentina)',
      'native' => 'Español',
      'direction' => '',
    ];
    $request = $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);
    $response = json_decode($request, TRUE);

    $esArLanguage = ConfigurableLanguage::load('es');
    /** @var $esArLanguage ConfigurableLanguageInterface */
    $this->assertNotNull($esArLanguage, 'Spanish (Argentina) language has been added.');
    $this->assertIdentical('Spanish (Argentina)', $esArLanguage->getName());
    $this->assertIdentical(ConfigurableLanguage::DIRECTION_LTR, $esArLanguage->getDirection());

    // The language must be returned in the dashboard.
    $request = $this->drupalGet('/admin/lingotek/dashboard_endpoint');
    $response = json_decode($request, TRUE);
    $returned_languages = array_keys($response['languages']);
    $this->assertIdentical(['en_US', 'es_AR'], $returned_languages);


    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish (Spain)',
      'native' => 'Español',
      'direction' => '',
    ];
    $request = $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);
    $response = json_decode($request, TRUE);

    $esEsLanguage = ConfigurableLanguage::load('es-es');
    /** @var $esEsLanguage ConfigurableLanguageInterface */
    $this->assertNotNull($esEsLanguage, 'Spanish (Spain) language has been added.');
    $this->assertIdentical('Spanish (Spain)', $esEsLanguage->getName());
    $this->assertIdentical(ConfigurableLanguage::DIRECTION_LTR, $esEsLanguage->getDirection());

    // The language must be returned in the dashboard.
    $request = $this->drupalGet('/admin/lingotek/dashboard_endpoint');
    $response = json_decode($request, TRUE);
    $returned_languages = array_keys($response['languages']);
    $this->assertIdentical(['en_US', 'es_AR', 'es_ES'], $returned_languages);

  }

  /**
   * Tests that we can delete languages in the dashboard.
   */
  public function testDeleteLanguage() {
    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->save();

    /** @var LanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $languages = $language_manager->getLanguages();
    $this->assertIdentical(2, count($languages));

    $response = $this->curlExec(array(
      CURLOPT_URL => \Drupal::url('lingotek.dashboard_endpoint', array(), array('absolute' => TRUE)),
      CURLOPT_CUSTOMREQUEST => 'DELETE',
      CURLOPT_POSTFIELDS => 'code=es_ES',
      CURLOPT_HTTPHEADER => array(
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
      ),
    ));
    $response = json_decode($response, TRUE);
    $this->assertIdentical('DELETE', $response['method']);
    $this->assertIdentical('es', $response['language']);
    $this->assertIdentical('Language removed: es_ES', $response['message']);

    $language_manager->reset();
    $languages = $language_manager->getLanguages();
    $this->assertIdentical(1, count($languages));
  }

  /**
   * Tests that there is a message when there are UI translations available.
   */
  public function testTranslationsAvailable() {
    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // Override Drupal core translation status as 'up-to-date'.
    $status = locale_translation_get_status();
    $status['drupal']['es'] = new \stdClass();
    $status['drupal']['es']->type = 'current';
    \Drupal::state()->set('locale.translation_status', $status);

    // One language added, there are missing translations.
    $this->drupalGet('admin/lingotek');
    $this->assertNoRaw(t('Missing translations for: @languages. See the <a href=":updates">Available translation updates</a> page for more information.', array('@languages' => t('Spanish'), ':updates' => \Drupal::url('locale.translate_status'))), 'Missing translations message');

    // Set lingotek module to have a local translation available.
    $status = locale_translation_get_status();
    $status['lingotek']['es'] = new \stdClass();
    $status['lingotek']['es']->type = 'local';
    \Drupal::state()->set('locale.translation_status', $status);

    // There are missing translations.
    $this->drupalGet('admin/lingotek');
    $this->assertNoRaw(t('Missing translations for: @languages. See the <a href=":updates">Available translation updates</a> page for more information.', array('@languages' => t('Spanish'), ':updates' => \Drupal::url('locale.translate_status'))), 'Translations message visible');
  }

}
