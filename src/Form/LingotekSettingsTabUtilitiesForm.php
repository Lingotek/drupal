<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsTabUtilitiesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\LingotekLocale;
use Drupal\lingotek\LingotekLog;
use Drupal\lingotek\Form\LingotekConfigFormBase;
use Drupal\lingotek\LingotekSync;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabUtilitiesForm extends LingotekConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_utilities_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $form['utilities'] = array(
      '#type' => 'details',
      '#title' => $this->t('Utilities'),
    );

    $form['utilities']['multilingual_title'] = array(
      '#markup' => '<H4>' . $this->t('Multilingual Preparation Utilities' . '</H4>'),
    );

    $cleanup_function_headers = array(
      'title' => array('data' => t('Utility')),
      'desc' => array('data' => t('Description')),
    );

    $cleanup_functions = array();

    $cleanup_functions['lingotek_batch_identify_translations'] = array(
      'title' => t('<span style="white-space: nowrap">Identify existing translations</span>'),
      'desc' => t('Identifies existing node translations currently untracked by the Lingotek module. The translation status for all newly discovered translations will be set to <i>current</i>.'),
    );

    $cleanup_functions['lingotek_cleanup_field_languages_for_nodes'] = array(
      'title' => t('Prepare nodes'),
      'desc' => t('Sets all <i>language neutral</i> nodes (and underlying fields and path aliases) to be @lang.', array('@lang' => \Drupal::languageManager()->getDefaultLanguage()->getName())),
    );

    $cleanup_functions['lingotek_cleanup_notify_entity_translation'] = array(
      'title' => t('Sync with Entity Translation'),
      'desc' => t('Reports all translations managed by Lingotek to the Entity Translation module.'),
    );

    $cleanup_functions['lingotek_cleanup_field_languages_for_comments'] = array(
      'title' => t('Prepare comments'),
      'desc' => t('Sets all <i>language neutral</i> comments (and underlying fields) to be @lang.', array('@lang' => \Drupal::languageManager()->getDefaultLanguage()->getName())),
    );

    $cleanup_functions['lingotek_cleanup_field_languages_for_taxonomy_terms'] = array(
      'title' => t('Prepare taxonomy terms with custom fields'),
      'desc' => t('Sets all <i>language neutral</i> taxonomy terms managed by Lingotek (and underlying fields) to be @lang.', array('@lang' => \Drupal::languageManager()->getDefaultLanguage()->getName())),
    );

    $cleanup_functions['lingotek_admin_prepare_blocks'] = array(
      'title' => t('Prepare blocks'),
      'desc' => t('Update all blocks to be translatable in the <i>Languages</i> settings.'),
    );

    $cleanup_functions['lingotek_admin_prepare_taxonomies'] = array(
      'title' => t('Prepare taxonomy'),
      'desc' => t('Update all taxonomy vocabularies that are not currently enabled for multilingual to use translation mode <i>Localize</i> in the Multilingual Options. (Note: Translation mode <i>Localize</i> does not support translation of custom fields within taxonomy terms.)'),
    );

    $cleanup_functions['lingotek_admin_prepare_menus'] = array(
      'title' => t('Prepare menus'),
      'desc' => t('Update all menus to use <i>Translate and Localize</i> in the Multilingual Options, and update all menu links to be <i>Language neutral</i>.'),
    );

    $cleanup_functions['lingotek_add_missing_locales'] = array(
      'title' => t('Add missing locales'),
      'desc' => t('Fills in any missing locale values to the <i>languages</i> table.'),
    );

    $form['utilities']['grid'] = array(
      '#type' => 'tableselect',
      '#header' => $cleanup_function_headers,
      '#options' => $cleanup_functions,
    );

    $form['utilities']['actions']['run_button'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Run selected utilities'),
      '#button_type' => 'primary',
      '#submit' => array('::runSelectedUtilities'),
    );

    $lingotek_table = array(
      '#type' => 'table',
      '#empty' => $this->t('No Entries'),
    );

    // Refresh resources via API row
    $api_refresh_row = array();
    $api_refresh_row['refresh_description'] = array(
      '#markup' => '<H5>' . $this->t('Refresh Project, Workflow, and Vault Information') . '</H5>' . '<p>' . $this->t('This module locally caches the available projects, workflows, and vaults. Use this utility whenever you need to pull down names for any newly created projects, workflows, or vaults from the Lingotek Translation Management System.') . '</p>',
    );
    $api_refresh_row['actions']['refresh_button'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Refresh'),
      '#button_type' => 'primary',
      '#submit' => array('::refreshResources'),
    );

    // Disassociate All Translations row
    $disassociate_row = array();
    $disassociate_row['disassociate_description'] = array(
      '#markup' => '<H5>' . $this->t('Disassociate All Translations (use with caution)') . '</H5>' . '<p>' . $this->t('Should only be used to change the Lingotek project or TM vault associated with the node’s translation. Option to disassociate node translations on Lingotek’s servers from the copies downloaded to Drupal. Additional translation using Lingotek will require re-uploading the node’s content to restart the translation process.') . '</p>',
    );
    $disassociate_row['actions']['disassociate_button'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Disassociate'),
      '#button_type' => 'primary',
      '#submit' => array('::disassociateAllTranslations'),
    );

    $lingotek_table['api_refresh'] = $api_refresh_row;
    $lingotek_table['disassociate'] = $disassociate_row;
    
    $form['utilities']['utilities_title'] = array(
      '#markup' => '<br><br><H4>' . $this->t('Lingotek Utilities' . '</H4>'),
    );
    $form['utilities']['lingotek_table'] = $lingotek_table;

    return $form;
  }

  public function refreshResources() {
    $resources = $this->L->getResources(TRUE);
    $this->L->set('account.resources', $resources);
    drupal_set_message($this->t('Project, workflow, and vault information have been refreshed.'));
  }

  public function disassociateAllTranslations() {
    LingotekSync::disassociateAllEntities();
    LingotekSync::disassociateAllSets();
    drupal_set_message($this->t('All translations have been disassociated.'));
  }

  public function runSelectedUtilities(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();

    foreach($form_values['grid'] as $cleanup_function) {
      if(!$cleanup_function) {
        continue;
      }
      $this->{$cleanup_function}();
    }
  }

  protected function lingotek_batch_identify_translations(){
    $existing_languages = \Drupal::languageManager()->getLanguages();
    $managed_entity_types = $this->retrieveManagedEntityTypes();

    $nodes = \Drupal::entityManager()->getStorage('node');

    // I. Identify field-based translations and set statuses
    $fields_to_test_for_translations = array('body', 'comment');
    foreach($managed_entity_types as $entity_type => $entity_type_details) {
      foreach($existing_languages as $langcode => $language_details) {
        $lingotek_locale = LingotekLocale::convertDrupal2Lingotek($langcode);
        
      }
    }

  }

  protected function lingotek_cleanup_field_languages_for_nodes(){
    dpm('nodes');
  }

  protected function lingotek_cleanup_notify_entity_translation(){
    dpm('entity');
  }

  protected function lingotek_cleanup_field_languages_for_comments(){
    dpm('comments');
  }

  protected function lingotek_cleanup_field_languages_for_taxonomy_terms(){
    dpm('taxonomy_terms');
  }

  protected function lingotek_admin_prepare_blocks(){
    dpm('blocks');
  }
  
  protected function lingotek_admin_prepare_taxonomies(){
    dpm('taxonomies');
  }

  protected function lingotek_admin_prepare_menus(){
    dpm('menus');
  }

  protected function lingotek_add_missing_locales(){
    dpm('locales');
  }

  protected function retrieveManagedEntityTypes() {
    $entity_types = \Drupal::entityManager()->getDefinitions();
    $whitelist = array('node', 'comment', 'taxonomy_term');
    $whitelist = array_flip($whitelist);
    $enabled_types = array_intersect_key($entity_types, $whitelist);

    return $enabled_types;
  }

}
