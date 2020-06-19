<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Special treatment for Paragraphs in bulk form.
 *
 * @package Drupal\lingotek\Form
 */
class LingotekParagraphsBulkForm {

  use StringTranslationTrait;

  /**
   * Adds the parent entity for a paragraph as the first column.
   *
   * If there are nested paragraphs, it recurses until the parent it's not a
   * paragraph itself.
   *
   * @param array &$form
   *   The form definition array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function form(array &$form, FormStateInterface $form_state) {
    $build_info = $form_state->getBuildInfo();
    if ($form['#form_id'] === 'lingotek_management' && $build_info && isset($build_info['form_id']) && ($build_info['form_id'] === 'lingotek_management')) {
      $formObject = $build_info['callback_object'];
      if ($formObject->getEntityTypeId() === 'paragraph') {
        $pids = array_keys($form['table']['#options']);
        /** @var \Drupal\paragraphs\Entity\Paragraph[] $paragraphs */
        $paragraphs = Paragraph::loadMultiple($pids);
        $form['table']['#header'] = ['parent' => $this->t('Parent')] + $form['table']['#header'];
        foreach ($paragraphs as $id => $paragraph) {
          /** @var \Drupal\Core\Entity\EntityInterface $parent */
          $parent = $paragraph;
          do {
            $parent = $parent->getParentEntity();
          } while ($parent !== NULL && $parent->getEntityTypeId() === 'paragraph');
          $form['table']['#options'][$id]['parent'] = $parent !== NULL ? $parent->toLink() : '';
        }
      }
    }
  }

}
