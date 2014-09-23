<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekContentTranslationForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;

define('COL_LANGUAGE', 0);
define('COL_TRANSLATION', 1);
define('COL_STATUS', 2);
define('COL_OPERATIONS', 3);

class LingotekContentTranslationForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek.content_translation_form';
  }

  /**
   * {@inheritdoc}
   */
  function buildForm(array $form, FormStateInterface $form_state, array $build = NULL) {

    $entity = $build['#entity'];
    $entity_type = $entity->getEntityTypeId();
    $lte = \Drupal\lingotek\LingotekTranslatableEntity::load(\Drupal::getContainer(), $entity);
    $doc_id = $lte->getDocId();
    $source_status = $lte->getSourceStatus();

    $form_state->set('entity', $entity);
    $overview = $build['content_translation_overview'];
    $form['#title'] = $this->t('Translations of @title', array('@title' => $build['#entity']->label()));

    $form['languages'] = array(
      '#type' => 'tableselect',
      '#header' => $overview['#header'],
      '#options' => array(),
    );

    $languages = \Drupal::languageManager()->getLanguages();
    $entity_langcode = $entity->language()->id;
    $additional_links = array();

    foreach ($languages as $langcode => $language) {
      $option = array_shift($overview['#rows']);
      if ($langcode == $entity_langcode) {
        // This is the source object so we disable the checkbox for this row.
        $form['languages'][$langcode] = array(
          '#type' => 'checkbox',
          '#disabled' => TRUE,
        );
        $path = '/admin/lingotek/batch/uploadSingle/' . $entity_type . '/' . $entity->id();
        $this->addOperationLink($option, 'Upload', $path, $language);
      }
      else {
        // TODO: Add the download operation if entity uploaded and language
        // is enabled for Lingotek translation.
      }

      $form['languages']['#options'][$langcode] = $option;
    }
    $form['actions']['#type'] = 'actions';

    $form['actions']['request'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Download selected translations'),
      '#validate' => array(array($this, 'validateForm')),
      '#submit' => array(array($this, 'submitForm')),
      '#button_type' => 'primary',
      '#disabled' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /*
   * Add an operation to the list of available operations for each language.
   */

  protected function addOperationLink(array &$option, $name, $path, LanguageInterface $language) {
    if (!isset($option[COL_OPERATIONS]['data']['#links'])) {
      $option[COL_OPERATIONS]['data']['#links'] = array();
    }
    $option[COL_OPERATIONS]['data']['#links'][strtolower($name)] = array(
      'title' => $name,
      'language' => $language,
      'href' => $path,
    );
  }

}
