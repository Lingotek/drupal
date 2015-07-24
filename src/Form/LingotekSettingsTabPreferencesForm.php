<?php

/**
 * @file
 * Contains \Drupal\lingotek\Form\LingotekSettingsPreferencesForm.
 */

namespace Drupal\lingotek\Form;

use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Component\Utility\String;
use Drupal\lingotek\Form\LingotekConfigFormBase;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabPreferencesForm extends LingotekConfigFormBase {
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
      '#default_value' => $this->L->get('preference.advanced_taxonomy_terms'),
    );

    $form['prefs']['hide_top_level'] = array(
      '#type' => 'checkbox',
      '#title' => t('Hide top-level menu item'),
      '#description' => t('When hidden, the module can still be accessed under <i>Configuration > Regional and language</i>. <p> Note: It will take a few seconds to save if this setting is changed.'),
      '#default_value' => $this->top_level_value,
    );

    $form['prefs']['show_language_labels'] = array(
      '#type' => 'checkbox',
      '#title' => t('Show language label on node pages'),
      '#description' => t('If checked, language labels will be displayed for nodes that have the \'language selection\' field set to be visible.'),
      '#default_value' => $this->L->get('preference.show_language_labels'),
    );

    $form['prefs']['always_show_translate_tabs'] = array(
      '#type' => 'checkbox',
      '#title' => t('Always show non-Lingotek translate tabs'),
      '#description' => t('If checked, edit-form tabs for both Content Translation and Entity Translation will not be hidden, even if the entity is managed by Lingotek.'),
      '#default_value' => $this->L->get('preference.always_show_translate_tabs'),
    );

    $form['prefs']['allow_local_editing'] = array(
      '#prefix' => '<div style="margin-left: 20px;">',
      '#suffix' => '</div>',
      '#type' => 'checkbox',
      '#title' => t('Allow local editing of Lingotek translations'),
      '#description' => t('If checked, local editing of translations managed by Lingotek will be allowed. (Note: any changes made may be overwritten if the translation is downloaded from Lingotek again.)'),
      '#default_value' => $this->L->get('preference.allow_local_editing'),
      '#states' => array(
        'visible' => array(
          ':input[name="always_show_translate_tabs"]' => array('checked' => TRUE),
        ),
      ),
    );

    $form['prefs']['language_specific_profiles'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable language-specific profiles'),
      '#description' => t('If checked, languages enabled for Lingotek translation will not automatically be queued for all content. Instead, languages enabled for Lingotek will be added to the available languages for profiles but will be disabled by default on profiles that have existing content. (Note: this cannot be unchecked if language-specific settings are in use.)'),
      '#default_value' => $this->L->get('preference.language_specific_profiles'),
    );

    $form['prefs']['delete_tms_documents_upon_disassociation'] = array(
      '#type' => 'checkbox',
      '#title' => t('Delete documents from Lingotek TMS when disassociating'),
      '#description' => t('Your documents will remain in your Drupal site but will be deleted from the Lingotek TMS if this option is checked.'),
      '#default_value' => $this->L->get('preference.delete_tms_documents_upon_disassociation'),
    );

    $form['prefs']['advanced_parsing'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable advanced features'),
      '#description' => t('Some features may not be available without an ' . \Drupal::l(t('Enterprise License'), Url::fromUri('http://www.lingotek.com')) . ' for the Lingotek TMS. Call 801.331.7777 for details.'),
      '#default_value' => $this->L->get('preference.advanced_parsing'),
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
    $this->saveShowLanguageFields($form_values);
    $this->saveAlwaysShowTranslateTabs($form_values);
    $this->L->set('preference.language_specific_profiles', $form_values['language_specific_profiles']);
    $this->L->set('preference.advanced_taxonomy_terms', $form_values['advanced_taxonomy_terms']);
    $this->L->set('preference.advanced_parsing', $form_values['advanced_parsing']);
    $this->L->set('preference.delete_tms_documents_upon_disassociation', $form_values['delete_tms_documents_upon_disassociation']);
    parent::submitForm($form, $form_state);
  }

  protected function retrieveLanguageSwitcher() {
    $theme_default = $this->config('system.theme')->get('default');
    $this->lang_regions = system_region_list($theme_default, REGIONS_VISIBLE);
    $ids = \Drupal::entityQuery('block')
      ->condition('plugin', 'language_block:language_interface')
      ->condition('theme', $theme_default)
      ->execute();
    if ($ids) {
      // We just take the first language switcher.
      $this->lang_switcher = \Drupal::entityManager()->getStorage('block')->load(reset($ids));
      $this->lang_switcher_value = $this->lang_switcher->status();
      $this->lang_region_selected = $this->lang_switcher->getRegion();
    }
    else {
      $this->lang_switcher_value = 0;
      $this->lang_region_selected = $this->default_region;
    }
  }

  protected function saveLanguageSwitcherSettings($form_values) {
    // If the website doesn't have a language switcher yet, don't act on it.
    if ($this->lang_switcher) {
      $this->lang_switcher->setRegion($form_values['lang_switcher_select']);
      if ($form_values['lang_switcher']) {
        $this->lang_switcher->enable();
      }
      else {
        $this->lang_switcher->disable();
      }
      $this->lang_switcher->save();
    }
    else {
      // If the user selects the checkbox, and no language switcher exists yet, create one.
      if ($form_values['lang_switcher']) {
        $config = $this->config('system.theme');
        $theme_default = $config->get('default');
        $this->lang_switcher = \Drupal::entityManager()->getStorage('block')->create(array('plugin' => 'language_block:language_interface', 'theme' => $theme_default));
        $this->lang_switcher->setRegion($form_values['lang_switcher_select']);
        $this->lang_switcher->enable();
        $this->lang_switcher->set('id', 'languageswitcher');
        $this->lang_switcher->save();
      }
    }
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

  protected function saveAlwaysShowTranslateTabs($form_values) {
    $this->L->set('preference.always_show_translate_tabs', $form_values['always_show_translate_tabs']);
    if ($form_values['always_show_translate_tabs']) {
      $this->L->set('preference.allow_local_editing', $form_values['allow_local_editing']);
    }
    else {
      $this->L->set('preference.allow_local_editing', 0);
    }
  }

  protected function saveShowLanguageFields($form_values) {
    // Only save if there's a change to the show_language_labels choice
    if ($this->L->get('preference.show_language_labels') != $form_values['show_language_labels']) {
      $bundles = \Drupal::entityManager()->getBundleInfo('node');
    
      foreach($bundles as $bundle_id => $bundle) {
        if ($bundle['translatable']) {
          $field_definitions = \Drupal::entityManager()->getFieldDefinitions('node', $bundle_id);
          $langcode = $field_definitions['langcode'];
          $display = entity_get_display('node', $bundle_id, 'default');
              
          if($form_values['show_language_labels']) {
            $component_values = array(
              'type' => 'language',
              'weight' => 0,
              'settings' => array(),
              'third_party_settings' => array(),
              'label' => 'above', // Can be above, inline, hidden, or visually_hidden (These are hard coded in core) 
            );
            $display->setComponent('langcode', $component_values);
          }
          else{
            $display->removeComponent('langcode');
          }
          $display->save();
        }
      }
    }
    $this->L->set('preference.show_language_labels', $form_values['show_language_labels']);
  }

}
