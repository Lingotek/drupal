<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekProfileDeleteForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for lingotek profiles edition.
 */
class LingotekProfileDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('lingotek.settings');
  }

  /**
   * @inheritDoc
   */
  public function delete(array $form, FormStateInterface $form_state) {
    parent::delete($form, $form_state);
    $form_state->setRedirect('lingotek.settings');
  }


}