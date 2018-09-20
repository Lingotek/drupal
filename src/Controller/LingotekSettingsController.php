<?php

namespace Drupal\lingotek\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\lingotek\Form\LingotekIntelligenceMetadataForm;
use Drupal\lingotek\Form\LingotekSettingsTabAccountForm;
use Drupal\lingotek\Form\LingotekSettingsTabConfigurationForm;
use Drupal\lingotek\Form\LingotekSettingsTabContentForm;
use Drupal\lingotek\Form\LingotekSettingsTabIntegrationsForm;
use Drupal\lingotek\Form\LingotekSettingsTabPreferencesForm;
use Drupal\lingotek\Form\LingotekSettingsTabProfilesEditForm;
use Drupal\lingotek\Form\LingotekSettingsTabUtilitiesForm;

class LingotekSettingsController extends LingotekControllerBase {

  public function content() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    $settings_tab = [
      $this->formBuilder->getForm(LingotekSettingsTabAccountForm::class),
      $this->formBuilder->getForm(LingotekSettingsTabContentForm::class),
      $this->formBuilder->getForm(LingotekSettingsTabConfigurationForm::class),
      $this->getProfileListForm(),
      $this->getIntelligenceMetadataForm(),
      $this->formBuilder->getForm(LingotekSettingsTabPreferencesForm::class),
      $this->getIntegrationsSettingsForm(),
      $this->formBuilder->getForm(LingotekSettingsTabUtilitiesForm::class),
    ];

    return $settings_tab;
  }

  /**
   * Gets the profile list form wrapped, so it can be expanded and collapsed.
   *
   * @return array
   *   The form definition.
   */
  public function getProfileListForm() {
    $original_form = $this->formBuilder->getForm($this->entityTypeManager()->getListBuilder('lingotek_profile'), new FormState());
    $form['profiles_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Translation Profiles'),
    ];
    $form['profiles_wrapper']['form'] = $original_form;
    // We add the link manually, as we cannot use local actions for that.
    $form['profiles_wrapper']['form']['add_link'] = [
      '#type' => 'link',
      '#title' => t('Add new Translation Profile'),
      '#url' => Url::fromRoute('entity.lingotek_profile.add_form'),
      '#weight' => 50,
      '#ajax' => [
        'class' => ['use-ajax'],
      ],
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => 860,
          'height' => 530,
        ]),
      ],
    ];

    return $form;
  }

  public function getIntegrationsSettingsForm() {
    $original_form = $this->formBuilder->getForm(LingotekSettingsTabIntegrationsForm::class);

    if (isset($original_form['contrib'])) {
      $form['integrations_wrapper'] = [
        '#type' => 'details',
        '#title' => $this->t('Integrations Settings'),
      ];
      $form['integrations_wrapper']['form'] = $original_form;
      return $form;
    }
    else {
      return NULL;
    }
  }

  public function profileForm() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }

    $profiles_modal = [
      $this->formBuilder->getForm(LingotekSettingsTabProfilesEditForm::class),
    ];

    return $profiles_modal;
  }

  public function getIntelligenceMetadataForm() {
    $form = $this->formBuilder->getForm(LingotekIntelligenceMetadataForm::class, $this->request, \Drupal::service('lingotek.intelligence_config'));
    return $form;
  }

}
