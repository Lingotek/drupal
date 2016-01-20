<?php


namespace Drupal\lingotek;


use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;

class LanguageLocaleMapper implements LanguageLocaleMapperInterface {

  /**
   * The entity query factory service.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * Constructs a new LingotekConfigTranslationService object.
   *
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query factory.
   */
  public function __construct(QueryFactory $entity_query) {
    $this->entityQuery = $entity_query;
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigurableLanguageForLocale($locale) {
    $drupal_language = NULL;
    $id = $this->entityQuery->get('configurable_language')
      ->condition('third_party_settings.lingotek.locale', $locale)
      ->execute();
    if (!empty($id)) {
      $drupal_language = ConfigurableLanguage::load(reset($id));
    }
    else{
      $drupal_language = ConfigurableLanguage::load(LingotekLocale::convertLingotek2Drupal($locale));
    }
    return $drupal_language;
  }

  /**
   * {@inheritDoc}
   */
  public function getLocaleForLangcode($langcode) {
    /** @var ConfigurableLanguageInterface $config_language */
    $config_language = ConfigurableLanguage::load($langcode);
    $locale = NULL;
    if ($config_language) {
      $locale = $config_language->getThirdPartySetting('lingotek', 'locale', LingotekLocale::convertDrupal2Lingotek($langcode));
    }
    return $locale;
  }

}
