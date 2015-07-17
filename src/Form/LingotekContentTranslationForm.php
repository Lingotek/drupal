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
use Drupal\Core\Url;

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
    $entity_langcode = $entity->language()->getId();
    $additional_links = array();

    foreach ($languages as $langcode => $language) {
      $locale = LingotekLocale::convertDrupal2Lingotek($langcode);
      $option = array_shift($overview['#rows']);
      if ($langcode == $entity_langcode) {
        // Buttons for the ENTITY SOURCE LANGUAGE
        // We disable the checkbox for this row.
        $form['languages'][$langcode] = array(
          '#type' => 'checkbox',
          '#disabled' => TRUE,
        );

        // Check-Progress button if the source upload status is PENDING.
        if ($source_status === Lingotek::STATUS_PENDING && !empty($doc_id)) {
          $path = '/admin/lingotek/entity/check_upload/' . $doc_id;
          $this->addOperationLink($entity, $option, 'Check Upload Status', $path, $language);
        }
        // Upload button if the status is EDITED or non-existent.
        elseif ($source_status === Lingotek::STATUS_EDITED || $source_status === NULL) {
          $path = '/admin/lingotek/batch/uploadSingle/' . $entity_type . '/' . $entity->id();
          $this->addOperationLink($entity, $option, 'Upload', $path, $language);
        }
      }
      else {
        // Buttons for the ENTITY TARGET LANGUAGE

        $target_status = $lte->getTargetStatus($locale);

        // Add-Targets button if languages haven't been added, or if target status is UNTRACKED.
        if ($source_status === Lingotek::STATUS_CURRENT && !empty($doc_id) && (!isset($target_status) || $target_status === Lingotek::STATUS_UNTRACKED)) {
          $path = '/admin/lingotek/entity/add_target/' . $doc_id . '/' . $locale;
          $this->addOperationLink($entity, $option, 'Request translation', $path, $language);
        }
        // Check-Progress button if the source upload status is PENDING.
        elseif ($target_status === Lingotek::STATUS_PENDING && $source_status === Lingotek::STATUS_CURRENT) {
          $this->removeOperationLink($option, 'Add'); //maintain core functionality
          $path = '/admin/lingotek/entity/check_target/' . $doc_id . '/' . $locale;
          $this->addOperationLink($entity, $option, 'Check translation status', $path, $language);
          $status_check_needed = TRUE;
        }
        // Download button if translations are READY or CURRENT.
        elseif ($target_status !== NULL && $source_status === Lingotek::STATUS_CURRENT) {
          $this->removeOperationLink($option, 'Add'); //maintain core functionality
          $path = '/admin/lingotek/entity/download/' . $doc_id . '/' . $locale;
          $this->addOperationLink($entity, $option, 'Download completed translation', $path, $language);
          $path = '/admin/lingotek/workbench/' . $doc_id . '/' . $locale;
          $this->addOperationLink($entity, $option, 'Edit', $path, $language);
          $targets_ready = TRUE;
        }
      }

      $form['languages']['#options'][$langcode] = $option;
    }
    $form['actions']['#type'] = 'actions';

    if ($status_check_needed) {
      $form['actions']['request'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Check Progress'),
        '#submit' => array('::submitForm'),
        '#button_type' => 'primary',
      );
    }
    elseif ($targets_ready) {
      $form['actions']['request'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Download selected translations'),
        '#submit' => array('::submitForm'),
        '#button_type' => 'primary',
      );
    }
    $form['fieldset']['entity'] = array(
      '#type' => 'value',
      '#value' => $entity,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
    $entity = $form_values['entity'];
    $selected_langcodes = $form_values['languages'];
    $locales = array();
    foreach ($selected_langcodes as $langcode => $selected) {
      if ($selected) {
        $locales[] = LingotekLocale::convertDrupal2Lingotek($langcode);
      }
    }

  }

  protected function getOperationColumnId(ContentEntityInterface $entity, array $option) {
    // For now, just return the known column id for operations
    if (count($option) == 5) {
      // Source-language column present.
      return 4;
    }
    else {
      // Source-language column absent.
      return 3;
    }
  }

  /*
   * Add an operation to the list of available operations for each language.
   */

  protected function addOperationLink(ContentEntityInterface $entity, array &$option, $name, $path, LanguageInterface $language) {
    $operation_col = $this->getOperationColumnId($entity, $option);

    if (!isset($option[$operation_col]['data']['#links'])) {
      $option[$operation_col]['data']['#links'] = array();
    }
    if (strpos($path, '/admin/lingotek/batch/') === 0) {
       $path = str_replace('/admin/lingotek/batch/', '', $path);
       list($action, $entity_type, $entity_id) = explode('/', $path);
       $url = Url::fromRoute('lingotek.batch', array('action' => $action, 'entity_type' => $entity_type, 'entity_id' => $entity_id));
    }
    elseif (strpos($path, '/admin/lingotek/entity/check_upload/') === 0) {
       $doc_id = str_replace('/admin/lingotek/entity/check_upload/', '', $path);
       $url = Url::fromRoute('lingotek.entity.check_upload', array('doc_id' => $doc_id));
    }
    elseif (strpos($path, '/admin/lingotek/entity/add_target/') === 0) {
       $path = str_replace('/admin/lingotek/entity/add_target/', '', $path);
       list($doc_id, $locale) = explode('/', $path);
       $url = Url::fromRoute('lingotek.entity.add_target', array('doc_id' => $doc_id, 'locale' => $locale));
    }
    elseif (strpos($path, '/admin/lingotek/entity/check_target/') === 0) {
       $path = str_replace('/admin/lingotek/entity/check_target/', '', $path);
       list($doc_id, $locale) = explode('/', $path);
       $url = Url::fromRoute('lingotek.entity.check_target', array('doc_id' => $doc_id, 'locale' => $locale));
    }
    elseif (strpos($path, '/admin/lingotek/entity/download/') === 0) {
       $path = str_replace('/admin/lingotek/entity/download/', '', $path);
       list($doc_id, $locale) = explode('/', $path);
       $url = Url::fromRoute('lingotek.entity.download', array('doc_id' => $doc_id, 'locale' => $locale));
    }
    elseif (strpos($path, '/admin/lingotek/workbench/') === 0) {
       $path = str_replace('/admin/lingotek/workbench/', '', $path);
       list($doc_id, $locale) = explode('/', $path);
       $url = Url::fromRoute('lingotek.workbench', array('doc_id' => $doc_id, 'locale' => $locale));
    }
    else {
       die("failed to get known operation in addOperationLink: $path");
    }
    $option[$operation_col]['data']['#links'][strtolower($name)] = array(
      'title' => $name,
      'language' => $language,
      'url' => $url,
    );
  }

  protected function removeOperationLink(array &$option, $name) {
    $operation_col = $this->getOperationColumnId($entity, $option);

    unset($option[$operation_col]['data']['#links'][strtolower($name)]);
  }

}
