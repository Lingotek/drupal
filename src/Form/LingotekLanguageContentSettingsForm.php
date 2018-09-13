<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Alters the Drupal language content settings form.
 *
 * @package Drupal\lingotek\Form
 */
class LingotekLanguageContentSettingsForm {

  use StringTranslationTrait;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * Constructs a new LingotekConfigTranslationService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(EntityTypeBundleInfoInterface $entity_bundle_info) {
    $this->entityBundleInfo = $entity_bundle_info;
  }

  /**
   * Alters the Drupal language content settings form for removing the lingotek
   * fields that we don't want to be enabled for translation.
   *
   * @param array $form
   *   The form definition array for the language content settings.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function form(array &$form, FormStateInterface $form_state) {
    $entity_types = $form['entity_types']['#options'];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      $bundles = $this->entityBundleInfo->getBundleInfo($entity_type_id);
      foreach ($bundles as $bundle => $bundle_info) {
        if (isset($form['settings'][$entity_type_id][$bundle]['fields'])) {
          $bundle_fields = $form['settings'][$entity_type_id][$bundle]['fields'];
          $keys = ['lingotek_metadata', 'lingotek_translation_source'];
          foreach ($keys as $key) {
            if (array_key_exists($key, $bundle_fields)) {
              unset($form['settings'][$entity_type_id][$bundle]['fields'][$key]);
            }
          }
        }
      }
    }
  }

}
