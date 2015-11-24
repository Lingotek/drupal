<?php

namespace Drupal\lingotek;

use Drupal\language\ConfigurableLanguageInterface;

interface LanguageLocaleMapperInterface {

  /**
   * Gets the Drupal language for the given Lingotek locale.
   *
   * @param string $locale
   *   The Lingotek locale.
   *
   * @return ConfigurableLanguageInterface|NULL
   *   The Drupal language created for this locale, or NULL if there is none.
   */
  public function getConfigurableLanguageForLocale($locale);

  /**
   * Gets the Lingotek locale for the given Drupal langcode.
   *
   * @param string $langcode
   *   The Drupal langcode.
   *
   * @return ConfigurableLanguageInterface|NULL
   *   The Lingotek locale.
   */
  public function getLocaleForLangcode($langcode);

}
