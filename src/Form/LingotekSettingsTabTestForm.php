<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTestForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekLocale;
use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabTestForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_test_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $build = NULL) {

    $container = \Drupal::getContainer();

    // $entity = $build['#entity'];
    // $entity_type = $entity->getEntityTypeId();
    // $lte = \Drupal\lingotek\LingotekTranslatableEntity::load(\Drupal::getContainer(), $entity);
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Nodes!');
  }

}