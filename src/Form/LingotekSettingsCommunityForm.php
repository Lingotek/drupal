<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\lingotek\Lingotek;

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
    $community_id = $this->lingotek->get('default.community');
    $communities = $this->lingotek->getCommunities();

    // dpm($this->lingotek->get());

    $form['lingotek_user_directions_1'] = [
    '#markup' => '<p>' . t('Your account is associated with multiple Lingotek communities.') . '</p>
      <p>' . t('Select the community that you would like associate with this site:') . '</p>',
];
    $community_options = [];
    foreach ($communities as $id => $name) {
      $community_options[$id] = $name . ' (' . $id . ')';
    }
    asort($community_options);
    $form['community'] = [
    '#title'         => t('Community'),
    '#type'          => 'select',
    '#options'       => $community_options,
    '#default_value' => $community_id,
    '#required'      => TRUE,
    ];

    $form['lingotek_communities'] = [
    '#type'  => 'hidden',
    '#value' => json_encode($communities),
    ];

    // Provide new button to continue
    $form['actions']['submit']['#value'] = t('Next');

    return $form;
  }

  /**
     * {@inheritdoc}
     */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $this->lingotek->set('default.community', $form_values['community']);
    // update resources based on newly selected community
    $this->lingotek->getResources(TRUE);
    $form_state->setRedirect('lingotek.setup_defaults');
    parent::submitForm($form, $form_state);
  }

}
