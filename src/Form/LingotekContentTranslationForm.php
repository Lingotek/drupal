<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekContentTranslationForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekLocale;
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
    $status_check_needed = ($source_status == Lingotek::STATUS_PENDING) ? TRUE : FALSE;
    $targets_ready = FALSE;


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

        // Check-Progress button if the source upload status is PENDING.
        if ($source_status == Lingotek::STATUS_PENDING &&!empty($doc_id)) {
          $path = '/admin/lingotek/entity/check_upload/' . $doc_id;
          $this->addOperationLink($option, 'Check Upload Status', $path, $language);
        }
        // Upload button if the status is EDITED or non-existent.
        elseif ($source_status != Lingotek::STATUS_PENDING) {
          $path = '/admin/lingotek/batch/uploadSingle/' . $entity_type . '/' . $entity->id();
          $this->addOperationLink($option, 'Upload', $path, $language);
        }
      }
      else {
        $this->removeOperationLink($option, 'Add');

        // Add-Targets button if languages haven't been added.
        $target_status = $lte->getTargetStatus(LingotekLocale::convertDrupal2Lingotek($langcode));
        if (empty($target_status)) {
          $path = '/admin/lingotek/entity/addLanguageSingle/' . $entity_type . '/' . $entity->id();
        }
        // Download button if translations are READY or CURRENT.
        elseif ($target_status != Lingotek::STATUS_PENDING) {
          $path = '/admin/lingotek/batch/downloadSingle/' . $entity_type . '/' . $entity->id();
          $this->addOperationLink($option, 'Download', $path, $language);
          $targets_ready = TRUE;
        }
        // Check-Progress button if the source upload status is PENDING.
        else {
          $path = '/admin/lingotek/batch/checkTargetStatus/' . $entity_type . '/' . $entity->id();
          $this->addOperationLink($option, 'Check Status', $path, $language);
          $status_check_needed = TRUE;
        }
      }

      $form['languages']['#options'][$langcode] = $option;
    }
    $form['actions']['#type'] = 'actions';

    if ($status_check_needed) {
      $form['actions']['request'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Check Progress'),
        '#submit' => array(array($this, 'submitForm')),
        '#button_type' => 'primary',
      );
    }
    elseif ($targets_ready) {
      $form['actions']['request'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Download selected translations'),
        '#submit' => array(array($this, 'submitForm')),
        '#button_type' => 'primary',
      );
    }
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

  protected function removeOperationLink(array &$option, $name) {
    unset($option[COL_OPERATIONS]['data']['#links'][strtolower($name)]);
  }

}
