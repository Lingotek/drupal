<?php

namespace Drupal\lingotek\Tests;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests the Lingotek dashboard.
 *
 * @group lingotek
 */
class LingotekDashboardTest extends LingotekTestBase {

  /**
   * {@inheritDoc}
   */
  public static $modules = ['block', 'node', 'comment'];

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
    $response = json_decode($request, TRUE);

    $italian_language = ConfigurableLanguage::load('it');
    /** @var \Drupal\language\ConfigurableLanguageInterface $italian_language */
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
    $response = json_decode($request, TRUE);

    $arabic_language = ConfigurableLanguage::load('ar');
    /** @var \Drupal\language\ConfigurableLanguageInterface $italian_language */
    $this->assertNotNull($arabic_language, 'Arabic language has been added.');
    $this->assertIdentical('Arabic', $arabic_language->getName());
    $this->assertIdentical(ConfigurableLanguage::DIRECTION_RTL, $arabic_language->getDirection());

    // @ToDo: The native language is not saved.
  }

  /**
   * Test that arabic (somehow a special language) can be added.
   */
  public function testDashboardCanAddArabicLanguage() {
    // Login as admin.
    $this->drupalLogin($this->rootUser);

    $post = [
      'code' => 'ar',
      'language' => 'Arabic',
      'native' => 'العربية',
      'direction' => 'RTL',
    ];
    $request = $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);
    $response = json_decode($request, TRUE);

    $arabic_language = ConfigurableLanguage::load('ar');
    /** @var \Drupal\language\ConfigurableLanguageInterface $italian_language */
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
    /** @var \Drupal\language\ConfigurableLanguageInterface $esEsLanguage */
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
    /** @var \Drupal\language\ConfigurableLanguageInterface $esArLanguage */
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
    /** @var \Drupal\language\ConfigurableLanguageInterface $esArLanguage */
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
    /** @var \Drupal\language\ConfigurableLanguageInterface $esEsLanguage */
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
   * Tests that we can disable languages in the dashboard.
   */
  public function testDisableLanguage() {
    // Add a language.
    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish (Spain)',
      'native' => 'Español (España)',
      'direction' => '',
    ];
    // We use curlExec in this test because drupalGet and drupalPost are not
    // reliable after doing DELETE requests, as the curl connection is reused
    // but not properly cleared. See https://www.drupal.org/node/2868666.
    $request = $this->curlExec([
      CURLOPT_URL => $this->buildUrl('/admin/lingotek/dashboard_endpoint', []),
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => $this->serializePostValues($post),
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
      ],
    ]);
    $response = json_decode($request, TRUE);
    $this->verbose(var_export($response, TRUE));

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    /** @var \Drupal\Core\Language\LanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $languages = $language_manager->getLanguages();
    $this->assertIdentical(2, count($languages));

    // Check the properties of the language.
    $request = $this->curlExec([
      CURLOPT_URL => \Drupal::url('lingotek.dashboard_endpoint', ['code' => 'es_ES'], ['absolute' => TRUE]),
      CURLOPT_HTTPGET => TRUE,
      CURLOPT_CUSTOMREQUEST => NULL,
      CURLOPT_NOBODY => FALSE,
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
      ],
    ]);
    $response = json_decode($request, TRUE);
    $this->assertIdentical('GET', $response['method']);
    $this->assertIdentical('es', $response['xcode']);
    $this->assertIdentical('es_ES', $response['locale']);
    $this->assertIdentical(1, $response['active']);
    $this->assertIdentical(1, $response['enabled']);

    $language = ConfigurableLanguage::load('es');
    $this->assertIdentical($language->getThirdPartySetting('lingotek', 'disabled', NULL), FALSE, 'The Spanish language is enabled');

    $request = $this->curlExec([
      CURLOPT_URL => \Drupal::url('lingotek.dashboard_endpoint', [], ['absolute' => TRUE]),
      CURLOPT_HTTPGET => FALSE,
      CURLOPT_CUSTOMREQUEST => 'DELETE',
      CURLOPT_POSTFIELDS => $this->serializePostValues(['code' => 'es_ES']),
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
      ],
    ]);
    $response = json_decode($request, TRUE);
    $this->verbose(var_export($response, TRUE));
    $this->assertIdentical('DELETE', $response['method']);
    $this->assertIdentical('es', $response['language']);
    $this->assertIdentical('Language disabled: es_ES', $response['message']);

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $languages = $language_manager->getLanguages();
    $this->assertIdentical(2, count($languages), 'Spanish language is disabled, but not deleted.');

    $language = ConfigurableLanguage::load('es');
    $this->assertIdentical($language->getThirdPartySetting('lingotek', 'disabled', NULL), TRUE, 'The Spanish language is disabled');

    // Check the properties of the language.
    $request = $this->curlExec([
      CURLOPT_URL => \Drupal::url('lingotek.dashboard_endpoint', ['code' => 'es_ES'], ['absolute' => TRUE]),
      CURLOPT_HTTPGET => TRUE,
      CURLOPT_CUSTOMREQUEST => NULL,
      CURLOPT_NOBODY => FALSE,
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
      ],
    ]);
    $response = json_decode($request, TRUE);
    $this->assertIdentical('GET', $response['method']);
    $this->assertIdentical('es', $response['xcode']);
    $this->assertIdentical('es_ES', $response['locale']);
    $this->assertIdentical(0, $response['active']);
    $this->assertIdentical(1, $response['enabled']);

    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish (Spain)',
      'native' => 'Español',
      'direction' => '',
    ];
    $response = $this->drupalPost('/admin/lingotek/dashboard_endpoint', 'application/json', $post);
    $response = json_decode($response, TRUE);
    $this->verbose(var_export($response, TRUE));

    // Check the properties of the language.
    $request = $this->curlExec([
      CURLOPT_URL => \Drupal::url('lingotek.dashboard_endpoint', ['code' => 'es_ES'], ['absolute' => TRUE]),
      CURLOPT_HTTPGET => TRUE,
      CURLOPT_CUSTOMREQUEST => NULL,
      CURLOPT_NOBODY => FALSE,
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
      ],
    ]);
    $response = json_decode($request, TRUE);
    $this->assertIdentical('GET', $response['method']);
    $this->assertIdentical('es', $response['xcode']);
    $this->assertIdentical('es_ES', $response['locale']);
    $this->assertIdentical(1, $response['active']);
    $this->assertIdentical(1, $response['enabled']);

    $languages = $language_manager->getLanguages();
    $this->assertIdentical(2, count($languages), 'Spanish language is enabled again, no new languages added.');

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    $language = ConfigurableLanguage::load('es');
    $this->assertIdentical($language->getThirdPartySetting('lingotek', 'disabled', NULL), FALSE, 'The Spanish language is enabled');
  }

  /**
   * Tests that disabled language appear as disabled in stats.
   */
  public function testDisabledLanguageInStats() {
    // Add a language.
    $post = [
      'code' => 'es_ES',
      'language' => 'Spanish (Spain)',
      'native' => 'Español (España)',
      'direction' => '',
    ];
    // We use curlExec in this test because drupalGet and drupalPost are not
    // reliable after doing DELETE requests, as the curl connection is reused
    // but not properly cleared. See https://www.drupal.org/node/2868666.
    $request = $this->curlExec([
      CURLOPT_URL => $this->buildUrl('/admin/lingotek/dashboard_endpoint', []),
      CURLOPT_POST => TRUE,
      CURLOPT_POSTFIELDS => $this->serializePostValues($post),
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
      ],
    ]);
    $response = json_decode($request, TRUE);
    $this->verbose(var_export($response, TRUE));

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    /** @var LanguageManagerInterface $language_manager */
    $language_manager = \Drupal::service('language_manager');
    $languages = $language_manager->getLanguages();
    $this->assertIdentical(2, count($languages));

    // Check the stats.
    $request = $this->curlExec([
      CURLOPT_URL => \Drupal::url('lingotek.dashboard_endpoint', [], ['absolute' => TRUE]),
      CURLOPT_HTTPGET => TRUE,
      CURLOPT_CUSTOMREQUEST => NULL,
      CURLOPT_NOBODY => FALSE,
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
      ],
    ]);
    $response = json_decode($request, TRUE);
    $this->verbose(var_export($response, TRUE));
    $this->assertIdentical('GET', $response['method']);
    $this->assertIdentical(2, $response['count']);
    $this->assertIdentical('en', $response['languages']['en_US']['xcode']);
    $this->assertIdentical(1, $response['languages']['en_US']['active']);
    $this->assertIdentical(1, $response['languages']['en_US']['enabled']);
    $this->assertIdentical('es', $response['languages']['es_ES']['xcode']);
    $this->assertIdentical(1, $response['languages']['es_ES']['active']);
    $this->assertIdentical(1, $response['languages']['es_ES']['enabled']);

    // Disable Spanish.
    $request = $this->curlExec([
      CURLOPT_URL => \Drupal::url('lingotek.dashboard_endpoint', [], ['absolute' => TRUE]),
      CURLOPT_HTTPGET => FALSE,
      CURLOPT_CUSTOMREQUEST => 'DELETE',
      CURLOPT_POSTFIELDS => $this->serializePostValues(['code' => 'es_ES']),
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
      ],
    ]);
    $response = json_decode($request, TRUE);
    $this->verbose(var_export($response, TRUE));
    $this->assertIdentical('DELETE', $response['method']);
    $this->assertIdentical('es', $response['language']);
    $this->assertIdentical('Language disabled: es_ES', $response['message']);

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();

    // Check the stats.
    $request = $this->curlExec([
      CURLOPT_URL => \Drupal::url('lingotek.dashboard_endpoint', [], ['absolute' => TRUE]),
      CURLOPT_HTTPGET => TRUE,
      CURLOPT_CUSTOMREQUEST => NULL,
      CURLOPT_NOBODY => FALSE,
      CURLOPT_HTTPHEADER => [
        'Accept: application/json',
      ],
    ]);
    $response = json_decode($request, TRUE);
    $this->verbose(var_export($response, TRUE));
    $this->assertIdentical('GET', $response['method']);
    $this->assertIdentical(2, $response['count']);
    $this->assertIdentical('en', $response['languages']['en_US']['xcode']);
    $this->assertIdentical(1, $response['languages']['en_US']['active']);
    $this->assertIdentical(1, $response['languages']['en_US']['enabled']);
    $this->assertIdentical('es', $response['languages']['es_ES']['xcode']);
    $this->assertIdentical(0, $response['languages']['es_ES']['active']);
    $this->assertIdentical(1, $response['languages']['es_ES']['enabled']);
  }

  /**
   * Tests that there is a message when there are UI translations available.
   */
  public function testTranslationsAvailable() {
    // Add a language.
    ConfigurableLanguage::createFromLangcode('es')->setThirdPartySetting('lingotek', 'locale', 'es_MX')->save();

    // One language added, there are missing translations.
    $this->drupalGet('admin/lingotek');
    $this->assertRaw(t('Missing translations for: @languages. See the <a href=":updates">Available translation updates</a> page for more information.', ['@languages' => t('Spanish'), ':updates' => \Drupal::url('locale.translate_status')]), 'Missing translations message');

    // Override Drupal core translation status as 'up-to-date'.
    $status = locale_translation_get_status();
    $status['drupal']['es'] = new \stdClass();
    $status['drupal']['es']->type = 'current';
    \Drupal::keyValue('locale.translation_status')->set('drupal', $status['drupal']);

    // There are no missing translations, translations are current.
    $this->drupalGet('admin/lingotek');
    $this->assertNoRaw(t('Missing translations for: @languages. See the <a href=":updates">Available translation updates</a> page for more information.', ['@languages' => t('Spanish'), ':updates' => \Drupal::url('locale.translate_status')]), 'No missing translations message with current translations');

    // Set lingotek module to have a local translation available.
    $status = locale_translation_get_status();
    $status['lingotek']['es'] = new \stdClass();
    $status['lingotek']['es']->type = 'local';
    \Drupal::keyValue('locale.translation_status')->set('lingotek', $status['lingotek']);

    // There are no missing translations, translations are local.
    $this->drupalGet('admin/lingotek');
    $this->assertNoRaw(t('Missing translations for: @languages. See the <a href=":updates">Available translation updates</a> page for more information.', ['@languages' => t('Spanish'), ':updates' => \Drupal::url('locale.translate_status')]), 'No missing translations message with local translations');
  }

}
