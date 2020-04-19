<?php

namespace Drupal\lingotek;

use Drupal\Core\DependencyInjection\DeprecatedServicePropertyTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\language\Entity\ConfigurableLanguage;

class LanguageLocaleMapper implements LanguageLocaleMapperInterface {

  use DeprecatedServicePropertyTrait;

  /**
   * {@inheritdoc}
   */
  protected $deprecatedProperties = ['entityQuery' => 'entity.query'];

  /**
   * Alows to access deprecated/removed properties.
   *
   * This method must be public.
   */
  public function __get($name) {
    if (isset($this->deprecatedProperties[$name])) {
      $service_name = $this->deprecatedProperties[$name];
      $class_name = static::class;
      @trigger_error("The property $name ($service_name service) is deprecated in $class_name and will be removed before Lingotek 9.x-1.0", E_USER_DEPRECATED);
      return NULL;
    }
  }

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LingotekConfigTranslationService object.
   *
   * @param $entity_query
   *   (deprecated) The entity query factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct($entity_query, EntityTypeManagerInterface $entity_type_manager = NULL) {
    if (get_class($entity_query) === '\Drupal\Core\Entity\Query\QueryFactory') {
      @trigger_error('The entity.query service is deprecated. Pass the entity_type.manager service to LingotekProfileUsage::__construct instead. It is required before Lingotek 9.x-1.0. See https://www.drupal.org/node/2549139.', E_USER_DEPRECATED);
    }
    if (!$entity_type_manager) {
      @trigger_error('The entity_type.manager service must be passed to LingotekProfileUsage::__construct, it is required before Lingotek 9.x-1.0. See https://www.drupal.org/node/2549139.', E_USER_DEPRECATED);
      $entity_type_manager = \Drupal::service('entity_type.manager');
    }
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
