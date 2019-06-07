<?php

namespace Drupal\lingotek\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for bulk management of job filtered content.
 */
class LingotekJobManagementConfigForm extends LingotekConfigManagementForm {

  /**
   * The job ID
   *
   * @var string
   */
  protected $jobId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_job_config_management';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $job_id = NULL) {
    $this->jobId = $job_id;
    $temp_store = $this->getFilterTempStore();
    $temp_store->set('job', $job_id);
    $form = parent::buildForm($form, $form_state);
    $form['filters']['wrapper']['job']['#access'] = FALSE;
    $form['filters']['wrapper']['job']['#default_value'] = $this->jobId;
    $form['options']['job_id']['#access'] = FALSE;
    $form['options']['job_id']['#default_value'] = $this->jobId;

    return $form;
  }

  protected function getFilterTempStore() {
    $key = new FormattableMarkup('lingotek.job_config_management_@job.filter', ['@job' => $this->jobId]);
    return $this->tempStoreFactory->get($key);
  }

}
