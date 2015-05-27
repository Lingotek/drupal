<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsLoggingForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\LanguageNegotiatorInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationSelected;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabTestNegForm extends LingotekConfigFormBase { 

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_test_neg_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $profiles = $this->L->get('profile');
    $entities = \Drupal::entityManager()->getDefinitions();
    $profile_types = array();
    $rows = array();

    foreach ($profiles as $profile) {
      $profile_types[] = $profile['name'];

    }

    $form = array(
      //'#type' => 'table',  
      '#responsive' => TRUE,
      '#header' => array('Content Type', 'Translation Profile', 'Fields'),
      '#empty' => 'No entries',
    );

    foreach($entities as $entity) {
      if ($entity instanceof \Drupal\Core\Entity\ContentEntityType) {
        $key = array_search ($entity, $entities);
        $form[$key] = $entity;
      }
    }

    foreach ($form as $type => $type_value) {
      $this->configureFormTable($form, $type, $type_value, $profile_types);
    }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Save settings'),  
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Logging!');
  }

  protected function configureFormTable(array &$form, $type, $type_value, $profile_types)  {

    if (!is_array($type_value)){
      return;
    }

    $table_form = array(
      '#title' => $this->t('The title'),
      '#tree' => TRUE,
      '#description' => $this->t('the description'),
      '#entity_info' => array(),
      '#show_operations' => FALSE,
    );

    $table_form['title'][$type] = array('#markup' => $type);

    $table_form['profiles'][$type] = array(
      '#type' => 'select',
      '#options' => $profile_types,
    );

    $table_form['enabled'][$type] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $enabled,
    );

    $form[$type] = $table_form;

  }

}
