<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsConnectForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure Lingotek
 */
class LingotekSettingsConnectForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.connect_form';
  }

  /** * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // build the redirecting link for authentication to Lingotek
    $host = $this->L->get('account.host');
    $auth_path = $this->L->get('account.authorize_path');
    $id = $this->L->get('account.default_client_id');
    $return_uri = new Url('lingotek.setup_account', array('success' => 'true'), array('absolute' => TRUE));
    $login = $this->L->get('account.type');

    $form = parent::buildForm($form, $form_state);
    if (!isset($form['#attached'])) {
      $form['#attached'] = array();
    }
    if (!isset($form['#attached']['js'])) {
      $form['#attached']['js'] = array();
    }

    $form['#attached']['js'][] = drupal_get_path('module', 'lingotek') . '/js/connect.js';
    $form['intro'] = array(
      '#type' => 'markup',
      '#markup' => $this->t('Get started by clicking the button below to connect your Lingotek account to this Drupal site.'),
    );
    //$form['actions']['submit']['#value'] = $this->t('Connect Account');
    unset($form['actions']['submit']);

    $lingotek_connect_link = $host . '/' . $auth_path . '?client_id=' . $id . '&response_type=token&redirect_uri=' . urlencode($return_uri->toString());

    $form['actions']['submit'][] = array(
      '#theme' => 'menu_local_action',
      '#link' => array(
        'title' => t('Connect to Lingotek'),
        'href' => $lingotek_connect_link,
        'localized_options' => array(
          'attributes' => array(
            'title' => t('Connect to Lingotek'),
            'class' => array('button--primary'),
          )
        )
      ),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // do nothing for now
  }
}
