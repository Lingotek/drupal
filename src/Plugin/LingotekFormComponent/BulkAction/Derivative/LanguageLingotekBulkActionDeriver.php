<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LanguageLingotekBulkActionDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;


  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, LingotekConfigurationServiceInterface $lingotek_configuration) {
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language_manager;
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  /**
   * @{inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('lingotek.configuration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $definitions = parent::getDerivativeDefinitions($base_plugin_definition);
    if (empty($definitions)) {
      $plugin_derivatives = [];
      $target_languages = $this->languageManager->getLanguages();
      $target_languages = array_filter($target_languages, function (LanguageInterface $language) {
        $configLanguage = $this->entityTypeManager->getStorage('configurable_language')->load($language->getId());
        return $this->lingotekConfiguration->isLanguageEnabled($configLanguage);
      });
      foreach ($target_languages as $langcode => $language) {
        $plugin_derivatives[$langcode] = [
            'langcode' => $langcode,
            'language' => $language->getName(),
          ] + $base_plugin_definition;
      }
      $this->derivatives = $plugin_derivatives;
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
