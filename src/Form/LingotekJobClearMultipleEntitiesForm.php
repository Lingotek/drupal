<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form for bulk clear of Job ID to content entities.
 *
 * @package Drupal\lingotek\Form
 */
class LingotekJobClearMultipleEntitiesForm extends LingotekJobAssignToMultipleEntitiesForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek.clear_job_content_multiple_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    unset($form['job_id']);
    $form['actions']['submit']['#value'] = $this->t('Clear Job ID');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setValue('job_id', '');
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function postStatusMessage() {
    $this->messenger->addStatus('Job ID was cleared successfully.');
  }

}
