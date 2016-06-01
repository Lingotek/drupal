<?php

namespace Drupal\lingotek\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\lingotek\Controller\LingotekControllerBase;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekAccount;
use Drupal\lingotek\Remote\LingotekApi;

class LingotekSettingsController extends LingotekControllerBase {

  public function content() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    $settings_tab = array (
      $this->getLingotekForm('LingotekSettingsTabAccountForm'),
      $this->getLingotekForm('LingotekSettingsTabContentForm'),
      $this->getLingotekForm('LingotekSettingsTabConfigurationForm'),
      $this->getProfileListForm(),
      $this->getLingotekForm('LingotekSettingsTabPreferencesForm'),
      $this->getLingotekForm('LingotekSettingsTabLoggingForm'),
      $this->getLingotekForm('LingotekSettingsTabUtilitiesForm'),
    );

    return $settings_tab;
  }

  /**
   * Gets the profile list form wrapped, so it can be expanded and collapsed.
   *
   * @return array
   *   The form definition.
   */
  public function getProfileListForm() {
    $form_builder = \Drupal::formBuilder();
    $original_form = $form_builder->getForm($this->entityManager()->getListBuilder('lingotek_profile'), new FormState());
    $form['profiles_wrapper'] = array(
      '#type' => 'details',
      '#title' => $this->t('Translation Profiles'),
    );
    $form['profiles_wrapper']['form'] = $original_form;
    // We add the link manually, as we cannot use local actions for that.
    $form['profiles_wrapper']['form']['add_link'] = [
      '#type' => 'link',
      '#title' => t('Add new Translation Profile'),
      '#url' => Url::fromRoute('entity.lingotek_profile.add_form'),
      '#weight' => 50,
      '#ajax' => array(
        'class' => array('use-ajax'),
      ),
      '#attributes' => array(
        'class' => array('use-ajax'),
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode(array(
          'width' => 860,
          'height' => 530,
        )),
      ),
    ];

    return $form;
  }

  public function profileForm() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    $profiles_modal = array(
      $this->getLingotekForm('LingotekSettingsTabProfilesEditForm'),
    );

    return $profiles_modal;
  }

}
