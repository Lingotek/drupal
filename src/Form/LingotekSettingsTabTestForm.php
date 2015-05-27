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
    
    $entities = \Drupal::entityManager()->getDefinitions();
    $content_entities = array ();
    $profiles = array('Automatic', 'Manual', 'Disabled');
    $profiles_select = array(
      '#type' => 'select',
      '#options' => $profiles,
    );

    foreach($entities as $entity) {
      if ($entity instanceof \Drupal\Core\Entity\ContentEntityType) {
        $key = array_search ($entity, $entities);
        $content_entities[$key] = $entity;
      }
    }

    $form['nodes'] = array(
      '#type' => 'details',
      '#title' => t('Helpey Helperton'),
      '#group' => 'settings',
    );

    $header = array(
      'content_type' => t('Content Type'), 
      'translation_profile' => t('Translation Profile'), 
      'fields' => t('Fields'),
    );

    $options = array();
    $pull_downs = array();

    foreach ($content_entities as $entity) {
      $key = array_search ($entity, $content_entities);
      $options[$key] = array(
        'content_type' => $key, 
        'translation_profile' => 'profile',
        'fields' => 'yo, fields',
      );
    }

    $form['nodes']['mytable'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
    );

    $form['nodes']['actions'] = array('#type' => 'actions');
    $form['nodes']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Nodes!');
  }

}