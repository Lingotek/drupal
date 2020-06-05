<?php

namespace Drupal\lingotek;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;

class LanguageLocaleMapper implements LanguageLocaleMapperInterface {

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LingotekConfigTranslationService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigurableLanguageForLocale($locale) {
    $drupal_language = NULL;
    $locale = str_replace("-", "_", $locale);
    $id = $this->entityTypeManager->getStorage('configurable_language')->getQuery()
      ->condition('third_party_settings.lingotek.locale', $locale)
      ->execute();
    if (!empty($id)) {
      $drupal_language = ConfigurableLanguage::load(reset($id));
    }
    else {
      $drupal_language = ConfigurableLanguage::load(LingotekLocale::convertLingotek2Drupal($locale));
    }
    return $drupal_language;
  }

  /**
   * {@inheritDoc}
   */
  public function getLocaleForLangcode($langcode) {
    /** @var \Drupal\language\ConfigurableLanguageInterface $config_language */
    $config_language = ConfigurableLanguage::load($langcode);
    $locale = NULL;
    if ($config_language) {
      $locale = $config_language->getThirdPartySetting('lingotek', 'locale', LingotekLocale::convertDrupal2Lingotek($langcode));
    }
    return $locale;
  }

}
