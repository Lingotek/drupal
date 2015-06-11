<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsProfilesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\lingotek\Lingotek;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabProfilesForm extends LingotekConfigFormBase {
  protected $profiles;
  protected $profile_names;
  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_profiles_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->profiles = $this->L->get('profile');

    $header = array(
      $this->t('Profile Name'),
      $this->t('Usage'),
      $this->t('Actions'),
    );

    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No Entries'),
    );
      
    foreach ($this->profiles as $profile) {
      $row = array();
      $row['profile_name'] = array(
        '#markup' => $this->t(ucfirst($profile['name'])),
      );
      $count = $this->retrieveUsage($profile);
      $row['usage'] = array(
        '#markup' => $this->t($count . ' content types'),
      );
      $row['profile_actions'] = $this->retrieveActions($profile);
      $table[$profile['name']] = $row;
    }

    $form['config_parent'] = array(
      '#type' => 'details',
      '#title' => $this->t('Translation Profiles'),
      '#collapsible' => FALSE,
    );

    $form['config_parent']['table'] = $table;

    $form['config_parent']['add_profile'] = array(
      '#markup' => \Drupal::l(t('Add New Profile'), new Url('lingotek.dashboard')),
    );

    $form['foo'] = array(
      '#type' => 'submit',
      '#value' => t('Modalize!'),
      '#ajax' => array(
        'class' => 'use-ajax',
        'data-accepts' => 'application/vnd.drupal-modal',
        'callback' => array($this, 'foo'),
      ),
    );

    $form['category-Devel'] = array(
      '#type' => 'details',
      '#title' => 'Test Modal',
      '#open' => TRUE,
      'content' => array(
        '#theme' => 'links',
        '#links' => array(),
      ),
    );

    $form['category-Devel']['content']['#links']['plugin'] = array(
      'title' => 'devel',
      'url' => Url::fromRoute('lingotek.settings_profile', ['plugin_id' => 'devel']),
      'attributes' => array(
        'class' => array('use-ajax'),
        'data-accepts' => 'application/vnd.drupal-modal',
        'data-dialog-options' => Json::encode(array(
          'width' => 700,
        )),
      ),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  // public function submitForm(array &$form, FormStateInterface $form_state) {
  //   dpm('Profiles!');
  // }

  public function foo(array &$form, array &$form_state) {
    dpm('In foo method!');
    $content = array(
      'content' => array(
        '#markup' => 'My Return',
      ),
    );
    $response = new AjaxResponse();
    $html = drupal_render($content);
    $response->addCommand(new OpenModalDialogCommand('Hi', $html));
    return $response;
  }

  protected function buildProfileForm($profile) {
    $usage_count = $this->retrieveUsage($profile);
    $details = array(
      '#type' => 'details',
      '#title' => $this->t(ucfirst($profile['name']) . ' (Used by ' . $usage_count . ' content types)'),
    );

    return $details;
  }

  protected function retrieveUsage($profile) {
    $count = 0;
    $content_types = $this->L->get('translate.entity');
    
    // Count how many content types are using this $profile
    foreach($content_types as $type_id => $profile_choice) {
      if ($profile_choice == $profile['id']) {
        $count++;
      }
    }
  
    return $count;
  }

  protected function retrieveActions($profile) {
    $edit_link;

    if ($profile['id'] == Lingotek::PROFILE_DISABLED) {
      $edit_link = '';
    }
    else {
      $edit_link = array(
        '#markup' => \Drupal::l(t('Edit'), new Url('lingotek.dashboard')),
      );
    }

    return $edit_link;
  }

}
