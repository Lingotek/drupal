<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTabTestNewTableForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabTestNewTableForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_test_new_table_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entityDefs = \Drupal::entityManager()->getDefinitions();
    $bundles = \Drupal::entityManager()->getAllBundleInfo();
    $fieldDefs = \Drupal::entityManager()->getFieldMap();
    $query = \Drupal::entityQuery('node');
    $profiles = $this->L->get('profile');
    $pullDownProfiles = array();

    $contentTypes = array();

    // Only use node types the user has access to.
    foreach (\Drupal::entityManager()->getStorage('node_type')->loadMultiple() as $type) {
      if (\Drupal::entityManager()->getAccessControlHandler('node')->createAccess($type->id())) {
        $contentTypes[$type->id()] = $type;
      }
    }
    
    foreach($profiles as $profile){
      $pullDownProfiles[] = $profile['name'];
    }
    $menuItems = array('Good', 'Bad', 'Ugly');
    $entities = array('block_content', 'block', 'page');
    $fields = array('Body', 'Title');

    $header = array(
      t('Entity'),
      t('Profile'),
      t('Fields'),
    );
    $table = array(
      '#type' => 'table',
      //'#header' => $header,
      '#empty' => t('No Entries'),
    );

    foreach ($entities as $entity) {
      $row = array();
      $name = array('#markup' => $entity);
      $row['entity'] = $name;
      $select = array(
        '#type' => 'select',
        '#options' => $pullDownProfiles,
      );
      $row['profile'] = $select;
      $checkboxRow = array();
      
      foreach ($fields as $field) {
        $checkbox = array(
          '#type' => 'checkbox',
          '#title' => $field,
        );
        $checkboxRow[$field] = $checkbox;
      }
      $row['fields'] = $checkboxRow;
      $table[$entity] = $row;
    }

    $form['wrap'] = array(
      '#type' => 'details',
      '#title' => 'Behold the form!',
    );

    $form['wrap']['table'] = $table;
    
    //Wrap the button in details
    unset($form['actions']);
    $form['wrap']['actions']['#type'] = 'actions';
    $form['wrap']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('New Form Submit!');

    $formValues = $form_state->getValues();
    $tableValues = $formValues['table'];
    dpm($tableValues);
  }

}
