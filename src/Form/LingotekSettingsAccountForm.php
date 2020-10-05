<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Lingotek
 */
class LingotekSettingsAccountForm extends LingotekConfigFormBase {

  /**
     * {@inheritdoc}
     */
  public function getFormID() {
    return 'lingotek.account_form';
  }

  /**
 * * {@inheritdoc}
     */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);

    $form['account']['connected'] = [];
    $form['account']['login_id'] = [
    '#type'        => 'textfield',
    '#title'       => t('Login'),
    '#description' => t('The login used to connect with the Lingotek service.'),
    '#value'       => $this->config('lingotek.settings')->get('account.login_id'),
    '#disabled'    => TRUE,
    ];
    $form['account']['access_token'] = [
    '#type'        => 'textfield',
    '#title'       => t('Access Token'),
    '#description' => t('The token currently useed when communicating with the Lingotek service.'),
    '#value'       => $this->config('lingotek.settings')->get('account.access_token'),
    '#disabled'    => TRUE,
    ];

    // Provide new button to continue
    $form['actions']['submit']['#value'] = t('Next');

    return $form;

  }

  /**
     * {@inheritdoc}
     */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Everything is saved in the previous step, so redirect to community form.
    $form_state->setRedirect('lingotek.setup_community');
  }

}
