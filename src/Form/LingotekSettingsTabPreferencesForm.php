<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsPreferencesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabPreferencesForm extends LingotekConfigFormBase {
  protected $advanced_taxonomy_terms_value = 0;
  protected $show_translate_tabs_value = 0;
  protected $advanced_parsing_value = 0;
  protected $lang_switcher_value = 0;
  protected $top_level_value = 0;
  protected $lang_switcher;
  protected $lang_switcher_region;
  protected $lang_regions;
  protected $lang_region_selected;
  protected $default_region = 'sidebar_first';

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_preferences_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->retrieveLanguageSwitcher();
    $this->retrieveCheckboxValues();
    $this->retrieveAdminMenu();
    
    $form['prefs'] = array(
      '#type' => 'details',
      '#title' => t('Preferences'),
    );

    $form['prefs']['lang_switcher'] = array(
      '#type' => 'checkbox',
      '#title' => 'Enable the default language switcher',
      '#default_value' => $this->lang_switcher_value,
    );

    $form['prefs']['lang_switcher_select'] = array(
      '#type' => 'select',
      '#description' => t('The region where the switcher will be displayed. <p> <p> Note: The default language switcher block is only shown if at least two languages are enabled and language negotiation is set to <i>URL</i> or <i>Session</i>. Go to ') . \Drupal::l(t('Language detection and selection'), Url::fromRoute('language.negotiation')) . t(' to change this.'),
      '#options' => $this->lang_regions,
      '#default_value' => $this->lang_region_selected == 'none' ? $this->default_region : $this->lang_region_selected,
      '#states' => array(
        'visible' => array(
          ':input[name="lang_switcher"]' => array('checked' => TRUE),
        ),
      ),
    );

    $form['prefs']['advanced_taxonomy_terms'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable advanced handling of taxonomy terms'),
      '#description' => t('This option is used to handle translation of custom fields assigned to taxonomy terms.'),
      '#default_value' => $this->advanced_taxonomy_terms_value,
    );

    $form['prefs']['hide_top_level'] = array(
      '#type' => 'checkbox',
      '#title' => t('Hide top-level menu item'),
      '#description' => t('When hidden, the module can still be accessed under <i>Configuration > Regional and language</i>. <p> Note: It will take a few seconds to save if this setting is changed.'),
      '#default_value' => $this->top_level_value,
    );

    $form['prefs']['always_show_translate_tabs'] = array(
      '#type' => 'checkbox',
      '#title' => t('Always show non-Lingotek translate tabs'),
      '#description' => t('If checked, edit-form tabs for both Content Translation and Entity Translation will not be hidden, even if the entity is managed by Lingotek.'),
      '#default_value' => $this->show_translate_tabs_value,
    );

    $form['prefs']['advanced_parsing'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable advanced features'),
      '#description' => t('Some features may not be available without an Enterprise license for the Lingotek TMS. Call 801.331.7777 for details.'),
      '#default_value' => $this->advanced_parsing_value,
    );
    
    $form['prefs']['actions']['#type'] = 'actions';
    $form['prefs']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_values = $form_state->getValues();
  
    $this->saveAdminMenu($form_values);
    $this->saveLanguageSwitcherSettings($form_values);
    $this->L->set('preference.advanced_taxonomy_terms', $form_values['advanced_taxonomy_terms']);
    $this->L->set('preference.always_show_translate_tabs', $form_values['always_show_translate_tabs']);
    $this->L->set('preference.advanced_parsing', $form_values['advanced_parsing']);
    parent::submitForm($form, $form_state);
  }

  protected function retrieveCheckboxValues(){
    $choices = $this->L->get('preference');
    
    // Choices from Lingotek object
    if ($choices) {
      if ($choices['advanced_parsing'] == 1) {
        $this->advanced_parsing_value = 1;
      }
      if ($choices['advanced_taxonomy_terms'] == 1) {
        $this->advanced_taxonomy_terms_value = 1;
      }
      if ($choices['always_show_translate_tabs'] == 1) {
        $this->show_translate_tabs_value = 1;
      }
    }

    // Choices from non-Lingotek objects
    $this->lang_switcher_value = $this->lang_switcher->status(); 
  }

  protected function retrieveLanguageSwitcher() {
    $config = $this->config('system.theme');
    $theme_default = $config->get('default');
    $this->lang_regions = system_region_list($theme_default, REGIONS_VISIBLE);
    $this->lang_switcher = \Drupal::entityManager()->getStorage('block')->load('languageswitcher');
    $this->lang_region_selected = $this->lang_switcher->getRegion();
  }

  protected function saveLanguageSwitcherSettings($form_values) {
    $this->lang_switcher->setRegion($form_values['lang_switcher_select']);
    if ($form_values['lang_switcher']) {
      $this->lang_switcher->enable();
    }
    else {
      $this->lang_switcher->disable();
    }
    $this->lang_switcher->save();
  }

  protected function retrieveAdminMenu() {
    $menu_tree = \Drupal::menuTree();
    $menu_link_manager = $menu_tree->menuLinkManager;
    $admin_menu = $menu_link_manager->getDefinition('lingotek.dashboard');

    // Will be opposite from enabled value since we're hiding the menu item
    if($admin_menu['enabled']) {
      $this->top_level_value = 0;
    }
    else {
      $this->top_level_value = 1;
    }
  }

  protected function saveAdminMenu($form_values) {
    $updated_values;
    $menu_tree = \Drupal::menuTree();
    $menu_link_manager = $menu_tree->menuLinkManager;

    // Only run if there's been a change to avoid clearing the cache if we don't have to
    if ($this->top_level_value != $form_values['hide_top_level']) {
      if ($form_values['hide_top_level']) {
        $updated_values = array(
          'enabled' => 0,
        );
      }
      else {
        $updated_values = array(
          'enabled' => 1,
        );
      }

      $menu_link_manager->updateDefinition('lingotek.dashboard', $updated_values);
    
      if ($updated_values['enabled']) {
        $menu_link_manager->resetLink('lingotek.dashboard');
      }
    drupal_flush_all_caches();
    }
  }

}
