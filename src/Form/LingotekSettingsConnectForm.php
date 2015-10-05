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
    $host = $this->lingotek->get('account.host');
    $sandbox_host = $this->lingotek->get('account.sandbox_host');
    $auth_path = $this->lingotek->get('account.authorize_path');
    $id = $this->lingotek->get('account.default_client_id');
    $return_uri = new Url('lingotek.setup_account', array('success' => 'true', 'prod' => 'prod'), array('absolute' => TRUE));
    $login = $this->lingotek->get('account.type');

    $lingotek_register_link = $host . '/' . 'lingopoint/portal/requestAccount.action?client_id=' . $id . '&response_type=token&app=' . urlencode($return_uri->toString());
    $lingotek_connect_link = $host . '/' . $auth_path . '?client_id=' . $id . '&response_type=token&redirect_uri=' . urlencode($return_uri->toString());

    $return_uri->setOption('prod', 'sandbox');
    $lingotek_sandbox_link = $sandbox_host . '/' . $auth_path . '?client_id=' . $id . '&response_type=token&redirect_uri=' . urlencode($return_uri->toString());

    $form['new_account'] = ['#type' => 'container'];
    $form['new_account']['intro'] = array(
      '#type' => 'markup',
      '#markup' => $this->t('Get started by clicking the button below to connect your Lingotek account to this Drupal site.') . '<br/>',
    );
    $form['new_account']['submit'] = array(
      '#type' => 'link',
      '#title' => t('Connect New Lingotek Account'),
      '#url' => Url::fromUri($lingotek_register_link),
      '#options' => array(
        'attributes' => array(
          'title' => t('Connect to Lingotek'),
          'class' => array('button', 'action-connect'),
        )
      ),
    );
    $form['connect_account'] = ['#type' => 'container'];
    $form['connect_account']['text'] = ['#markup' => t('Do you already have a Lingotek account?') . ' '];
    $form['connect_account']['link'] = [
      '#type' => 'link',
      '#title' => t('Connect Lingotek Account'),
      '#url' => Url::fromUri($lingotek_connect_link),
    ];
    $form['connect_sandbox'] = ['#type' => 'container'];
    $form['connect_sandbox']  = ['#markup' => t('Do you have a Lingotek sandbox account?') . ' '];
    $form['connect_sandbox']['link'] = [
      '#type' => 'link',
      '#title' => t('Connect Sandbox Account'),
      '#url' => Url::fromUri($lingotek_sandbox_link),
    ];

    $form['#attached']['library'][] = 'lingotek/lingotek.connect';
    $form['#attached']['library'][] = 'lingotek/lingotek.icons';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // do nothing for now
  }
}
