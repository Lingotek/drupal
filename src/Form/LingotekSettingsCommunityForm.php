<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsCommunityForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\lingotek\Form\LingotekConfigFormBase;
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
		$community_id = $this->L->get('default.community');
    $communities  = $this->L->getCommunities();

    //dpm($this->L->get());
    
		$form['lingotek_user_directions_1'] = array(
			'#markup' => '<p>' . t('Your account is associated with multiple Lingotek communities.') . '</p>
      <p>' . t('Select the community that you would like associate with this site:') . '</p>');
		$community_options = array();
		foreach ($communities as $id => $name) {
			$community_options[$id] = $name . ' (' . $id . ')';
		}
		asort($community_options);
		$form['community'] = array(
			'#title'         => t('Community'),
			'#type'          => 'select',
			'#options'       => $community_options,
			'#default_value' => $community_id,
			'#required'      => TRUE,
		);

		$form['lingotek_communities'] = array(
			'#type'  => 'hidden',
			'#value' => json_encode($communities)
		);

		// Provide new button to continue
		$form['actions']['submit']['#value'] = t('Next');

		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $this->L->set('default.community', $form_values['community']);
    $this->L->getResources(TRUE); // update resources based on newly selected community
    $form_state->setRedirect('lingotek.setup_defaults');
    parent::submitForm($form, $form_state);
  }

}
