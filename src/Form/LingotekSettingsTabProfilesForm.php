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
  protected $profile_index;
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
    $this->profile_index = 0;

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
      $row['profile_actions'] = $this->retrieveActions($profile, $count);
      $table[$profile['name']] = $row;

      $this->profile_index++;
    }

    $form['config_parent'] = array(
      '#type' => 'details',
      '#title' => $this->t('Translation Profiles'),
    );

    $form['config_parent']['table'] = $table;
    $form['config_parent']['add_profile'] = $this->retrieveAddProfileLink();

    return $form;
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

  protected function retrieveActions($profile, $count) {
    $edit_link;

    if ($profile['id'] == Lingotek::PROFILE_DISABLED) {
      $edit_link = array(
        '#markup' => $this->t('Not Editable'),
      );
    }
    else {
      $edit_link = array(
        '#type' => 'link',
        '#ajax' => array(
          'class' => 'use-ajax',
        ),
        'content' => array(
          '#theme' => 'links',
        ),
      );

      $edit_link['content']['#links']['profile_form'] = array(
        'title' => 'Edit',
        'url' => Url::fromRoute('lingotek.settings_profile', [
          'profile_choice' => $profile, 
          'profile_index' => $this->profile_index,
          'profile_usage' => $count
        ]),
        'attributes' => array(
          'class' => 'use-ajax',
          'data-accepts' => 'application/vnd.drupal-modal',
          'data-dialog-options' => Json::encode(array(
            'width' => 900,
            'height' => 700,
          )),
        ),
      );
    }

    return $edit_link;
  }

  protected function retrieveAddProfileLink() {
    $edit_link = array(
      '#type' => 'link',
      '#ajax' => array(
        'class' => 'use-ajax',
      ),
      'content' => array(
        '#theme' => 'links',
      ),
    );

    $edit_link['content']['#links']['profile_form'] = array(
      'title' => 'Add New Profile',
      'url' => Url::fromRoute('lingotek.settings_profile'),
      'attributes' => array(
        'class' => 'use-ajax',
        'data-accepts' => 'application/vnd.drupal-modal',
        'data-dialog-options' => Json::encode(array(
          'width' => 900,
          'height' => 700,
        )),
      ),
    );
    return $edit_link;
  }

}
