<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsCommunityForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure text display settings for this page.
 */
class LingotekSettingsCommunityForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.setup_community_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $communities = $this->settings->get('account.communities');

    $form['lingotek_user_directions_1'] = array(
      '#markup' => '<p>Your account is associated with multiple Lingotek communities.</p>
      <p>Select the community to associate this site with:</p>');
    $community_options = array();
    foreach ($communities as $id => $name) {
      $community_options[$id] = $name . ' (' . $id . ')';
    }

    $form['lingotek_site_community'] = array(
      '#title' => t('Community'),
      '#type' => 'select',
      '#options' => $community_options,
      '#required' => TRUE,
    );

    $form['lingotek_communities'] = array(
      '#type' => 'hidden',
      '#value' => json_encode($communities)
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Next')
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->settings->set('account.community', $form_state['values']['community'])->save();

    parent::submitForm($form, $form_state);
  }
}
