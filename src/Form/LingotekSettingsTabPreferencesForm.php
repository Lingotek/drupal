<?php

namespace Drupal\lingotek\Form;

use Drupal\block\BlockRepositoryInterface;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

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
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');

    $this->retrieveLanguageSwitcher();
    $this->retrieveAdminMenu();

    $form['prefs'] = [
      '#type' => 'details',
      '#title' => t('Preferences'),
    ];

    $form['prefs']['lang_switcher'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the default language switcher',
      '#default_value' => $this->lang_switcher_value,
    ];

    $form['prefs']['lang_switcher_select'] = [
      '#type' => 'select',
      '#description' => $this->t('The region where the switcher will be displayed') . '<br>' . $this->t('Note: The default language switcher block is only shown if at least two languages are enabled and language negotiation is set to <em>URL</em> or <em>Session</em>. Go to <a href=":url">%language_detection</a> to change this.',
          ['%language_detection' => $this->t('Language detection and selection'), ':url' => Url::fromRoute('language.negotiation')->toString()]),
      '#options' => $this->lang_regions,
      '#default_value' => $this->lang_region_selected == 'none' ? $this->default_region : $this->lang_region_selected,
      '#states' => [
        'visible' => [
          ':input[name="lang_switcher"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['prefs']['advanced_taxonomy_terms'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable advanced handling of taxonomy terms'),
      '#description' => t('This option is used to handle translation of custom fields assigned to taxonomy terms.'),
      '#default_value' => $lingotek_config->getPreference('advanced_taxonomy_terms'),
    ];

    $form['prefs']['hide_top_level'] = [
      '#type' => 'checkbox',
      '#title' => t('Hide top-level menu item'),
      '#description' => t('When hidden, the module can still be accessed under <i>Configuration > Regional and language</i>. <p> Note: It will take a few seconds to save if this setting is changed.'),
      '#default_value' => $this->top_level_value,
    ];

    $form['prefs']['show_language_labels'] = [
      '#type' => 'checkbox',
      '#title' => t('Show language label on node pages'),
      '#description' => t("If checked, language labels will be displayed for nodes that have the 'language selection' field set to be visible."),
      '#default_value' => $lingotek_config->getPreference('show_language_labels'),
    ];

    $form['prefs']['always_show_translate_tabs'] = [
      '#type' => 'checkbox',
      '#title' => t('Always show non-Lingotek translate tabs'),
      '#description' => t('If checked, edit-form tabs for both Content Translation and Entity Translation will not be hidden, even if the entity is managed by Lingotek.'),
      '#default_value' => $lingotek_config->getPreference('always_show_translate_tabs'),
    ];

    $form['prefs']['allow_local_editing'] = [
      '#prefix' => '<div style="margin-left: 20px;">',
      '#suffix' => '</div>',
      '#type' => 'checkbox',
      '#title' => t('Allow local editing of Lingotek translations'),
      '#description' => t('If checked, local editing of translations managed by Lingotek will be allowed. (Note: any changes made may be overwritten if the translation is downloaded from Lingotek again.)'),
      '#default_value' => $lingotek_config->getPreference('allow_local_editing'),
      '#states' => [
        'visible' => [
          ':input[name="always_show_translate_tabs"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['prefs']['language_specific_profiles'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable language-specific profiles'),
      '#description' => t('If checked, languages enabled for Lingotek translation will not automatically be queued for all content. Instead, languages enabled for Lingotek will be added to the available languages for profiles but will be disabled by default on profiles that have existing content. (Note: this cannot be unchecked if language-specific settings are in use.)'),
      '#default_value' => $lingotek_config->getPreference('language_specific_profiles'),
    ];

    $form['prefs']['advanced_parsing'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable advanced features'),
      '#description' => t('Some features may not be available without an <a href=":url">Enterprise License</a> for the Lingotek TMS. Call <a href=":phone_link">%phone</a> for details.',
        [':url' => 'http://www.lingotek.com', ':phone_link' => 'tel:1-801-331-7777', '%phone' => '+1 (801) 331-7777']),
      '#default_value' => $lingotek_config->getPreference('advanced_parsing'),
    ];

    $states = [
      'published' => t('Published'),
      'unpublished' => t('Unpublished'),
      'same-as-source' => t('Same as source content'),
    ];

    $form['prefs']['target_download_status'] = [
      '#type' => 'select',
      '#title' => t('Translations download publication status'),
      '#description' => t('Translations download publication status: specify which published status the translations downloads must be saved with.'),
      '#options' => $states,
      '#default_value' => $lingotek_config->getPreference('target_download_status') ?: 'same-as-source',
    ];

    $form['prefs']['enable_content_cloud'] = [
      '#type' => 'checkbox',
      '#title' => t('Enable importing from Lingotek Content Cloud (beta)'),
      '#description' => t("Allows the importing of documents that are in your TMS. An 'Import' tab will appear next to the 'Settings' tab. <br> Note: The settings could take longer to save if this setting is changed."),
      '#default_value' => $lingotek_config->getPreference('enable_content_cloud', FALSE),
    ];

    $form['prefs']['enable_download_source'] = [
      '#type' => 'checkbox',
      '#title' => t('Download source if content is missing'),
      '#description' => t('If some content is not shown, the original words will be.'),
      '#default_value' => $lingotek_config->getPreference('enable_download_source') ?: FALSE,
    ];

    $form['prefs']['enable_download_interim'] = [
      '#type' => 'checkbox',
      '#title' => t('Download interim translations if available'),
      '#description' => t('Translations that have still phases pending in the TMS will be downloaded.'),
      '#default_value' => $lingotek_config->getPreference('enable_download_interim') ?: FALSE,
    ];

    $form['prefs']['append_type_to_title'] = [
      '#type' => 'checkbox',
      '#title' => t('Append Entity Type to TMS Document Name'),
      '#description' => t('Enable to have content/entity type appended to the document title in TMS.'),
      '#default_value' => $lingotek_config->getPreference('append_type_to_title'),
    ];

    $form['prefs']['split_download_all'] = [
      '#type' => 'checkbox',
      '#title' => t('Use a different batch per locale when downloading all translations'),
      '#description' => t('Uses a different batch process per locale. This option can be used to prevent timeouts during download all translations.'),
      '#default_value' => $lingotek_config->getPreference('split_download_all') ?: FALSE,
    ];

    $form['prefs']['actions']['#type'] = 'actions';
    $form['prefs']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');

    $form_values = $form_state->getValues();

    $this->saveAdminMenu($form_values);
    $this->saveLanguageSwitcherSettings($form_values);
    $this->saveShowLanguageFields($form_values);
    $this->saveAlwaysShowTranslateTabs($form_values);
    $lingotek_config->setPreference('language_specific_profiles', $form_values['language_specific_profiles'] ? TRUE : FALSE);
    $lingotek_config->setPreference('advanced_taxonomy_terms', $form_values['advanced_taxonomy_terms'] ? TRUE : FALSE);
    $lingotek_config->setPreference('advanced_parsing', $form_values['advanced_parsing'] ? TRUE : FALSE);
    $lingotek_config->setPreference('append_type_to_title', $form_values['append_type_to_title'] ? TRUE : FALSE);
    $lingotek_config->setPreference('target_download_status', $form_values['target_download_status']);
    $lingotek_config->setPreference('enable_download_source', $form_values['enable_download_source'] ? TRUE : FALSE);
    $lingotek_config->setPreference('enable_download_interim', $form_values['enable_download_interim'] ? TRUE : FALSE);
    $lingotek_config->setPreference('split_download_all', $form_values['split_download_all'] ? TRUE : FALSE);
    parent::submitForm($form, $form_state);
  }

  protected function retrieveLanguageSwitcher() {
    if (\Drupal::moduleHandler()->moduleExists('block')) {
      $theme_default = $this->config('system.theme')->get('default');
      $this->lang_regions = system_region_list($theme_default, BlockRepositoryInterface::REGIONS_VISIBLE);
      $ids = \Drupal::entityQuery('block')
        ->condition('plugin', 'language_block:language_interface')
        ->condition('theme', $theme_default)
        ->execute();
      if ($ids) {
        // We just take the first language switcher.
        $this->lang_switcher = \Drupal::entityTypeManager()->getStorage('block')->load(reset($ids));
        $this->lang_switcher_value = $this->lang_switcher->status();
        $this->lang_region_selected = $this->lang_switcher->getRegion();
      }
      else {
        $this->lang_switcher_value = 0;
        $this->lang_region_selected = $this->default_region;
      }
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
        $this->lang_switcher = \Drupal::entityTypeManager()->getStorage('block')->create(['plugin' => 'language_block:language_interface', 'theme' => $theme_default]);
        $this->lang_switcher->setRegion($form_values['lang_switcher_select']);
        $this->lang_switcher->enable();
        $this->lang_switcher->set('id', 'languageswitcher');
        $this->lang_switcher->save();
      }
    }
  }

  protected function retrieveAdminMenu() {
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
    $admin_menu = $menu_link_manager->getDefinition('lingotek.dashboard');

    // Will be opposite from enabled value since we're hiding the menu item
    if ($admin_menu['enabled']) {
      $this->top_level_value = 0;
    }
    else {
      $this->top_level_value = 1;
    }
  }

  protected function saveAdminMenu($form_values) {
    $updated_values = [];
    $should_reset_cache = FALSE;

    /** @var \Drupal\Core\Menu\MenuLinkManager $menu_link_manager */
    $menu_link_manager = \Drupal::service('plugin.manager.menu.link');

    // Only run if there's been a change to avoid clearing the cache if we don't have to
    if ($this->top_level_value != $form_values['hide_top_level']) {
      if ($form_values['hide_top_level']) {
        $updated_values = [
          'enabled' => 0,
        ];
      }
      else {
        $updated_values = [
          'enabled' => 1,
        ];
      }

      $menu_link_manager->updateDefinition('lingotek.dashboard', $updated_values);
      $ids = $menu_link_manager->getChildIds('lingotek.dashboard');
      foreach ($ids as $child_link) {
        $menu_link_manager->updateDefinition($child_link, $updated_values);
      }
      if ($updated_values['enabled']) {
        $menu_link_manager->resetLink('lingotek.dashboard');
      }
      $should_reset_cache = TRUE;
    }
    if ($should_reset_cache) {
      drupal_flush_all_caches();
    }
  }

  protected function saveAlwaysShowTranslateTabs($form_values) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    $config->set('preference.always_show_translate_tabs', $form_values['always_show_translate_tabs'] ? TRUE : FALSE);
    if ($form_values['always_show_translate_tabs']) {
      $config->set('preference.allow_local_editing', $form_values['allow_local_editing'] ? TRUE : FALSE);
    }
    else {
      $config->set('preference.allow_local_editing', FALSE);
    }
    $config->save();
  }

  protected function saveShowLanguageFields($form_values) {
    $config = \Drupal::configFactory()->getEditable('lingotek.settings');
    // Only save if there's a change to the show_language_labels choice
    if ($config->get('preference.show_language_labels') != $form_values['show_language_labels']) {
      $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');

      foreach ($bundles as $bundle_id => $bundle) {
        if ($bundle['translatable']) {
          $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle_id);
          $langcode = $field_definitions['langcode'];
          $display = $this->entityTypeManager->getStorage('entity_view_display')
            ->load('node.' . $bundle_id . '.default');

          if ($form_values['show_language_labels']) {
            $component_values = [
              'type' => 'language',
              'weight' => 0,
              'settings' => [],
              'third_party_settings' => [],
            // Can be above, inline, hidden, or visually_hidden (These are hard coded in core)
              'label' => 'above',
            ];
            $display->setComponent('langcode', $component_values);
          }
          else {
            $display->removeComponent('langcode');
          }
          $display->save();
        }
      }
    }
    $config->set('preference.show_language_labels', $form_values['show_language_labels'] ? TRUE : FALSE);
    $config->save();
  }

}
