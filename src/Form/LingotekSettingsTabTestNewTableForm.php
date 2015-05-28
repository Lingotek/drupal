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

    $menuItems = array('Good', 'Bad', 'Ugly');

    $header = array(
      t('Checkbox'),
      t('Select'),
      t('Radio'),
    );

    $rows = array(
      array(
        t('1'),
        t('2'),
        t('3'),
      ),
      array(
        t('4'),
        t('5'),
        t('6'),
      ),
      array(
        t('7'),
        t('8'),
        t('9'),
      ),
    );

    $test = array('#type' => 'select');

    $testCheckbox1 = array(
      '#type' => 'checkbox',
      '#title' => t('Checkbox1'),
    );

    $testSelect1 = array(
      '#type' => 'select',
      '#title' => t('Select1'),
      '#options' => $menuItems,
    );

    $testRadio1 = array(
      '#type' => 'radios',
      '#options' => array('Radio1'),
    );

    $row1 = array($testCheckbox1, $testSelect1, $testRadio1);

    $testCheckbox2 = array(
      '#type' => 'checkbox',
      '#title' => t('Checkbox2'),
    );

    $testSelect2 = array(
      '#type' => 'select',
      '#title' => t('Select2'),
      '#options' => $menuItems,
    );

     $testRadio2 = array(
      '#type' => 'radios',
      '#options' => array('Radio2'),
    );

    $row2 = array($testCheckbox2, $testSelect2, $testRadio2);

    $testCheckbox3 = array(
      '#type' => 'checkbox',
      '#title' => t('Checkbox3'),
    );

    $testSelect3 = array(
      '#type' => 'select',
      '#title' => t('Select3'),
      '#options' => $menuItems,
    );

     $testRadio3 = array(
      '#type' => 'radios',
      '#options' => array('Radio3'),
    );

    $row3 = array($testCheckbox3, $testSelect3, $testRadio3);

    $table = array(
      '#type' => 'table',
      '#header' => $header,
      '#empty' => t('No Entries'),
    );

    $table['row1'] = $row1;
    $table['row2'] = $row2;
    $table['row3'] = $row3;

    

    $form['table'] = $table;

     return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Logging!');
  }

}
