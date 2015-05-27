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

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Component\Utility\SafeMarkup;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\language\LanguageNegotiatorInterface;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationSelected;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Lingotek
 */
class LingotekSettingsTabTestPulldownForm extends LingotekConfigFormBase {

public function __construct(ConfigFactoryInterface $config_factory, ConfigurableLanguageManagerInterface $language_manager, LanguageNegotiatorInterface $negotiator, BlockManagerInterface $block_manager, ThemeHandlerInterface $theme_handler, EntityStorageInterface $block_storage = NULL) {
    parent::__construct($config_factory);
    $this->languageTypes = $this->config('language.types');
    $this->languageManager = $language_manager;
    $this->negotiator = $negotiator;
    $this->blockManager = $block_manager;
    $this->themeHandler = $theme_handler;
    $this->blockStorage = $block_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $entity_manager = $container->get('entity.manager');
    $block_storage = $entity_manager->hasHandler('block', 'storage') ? $entity_manager->getStorage('block') : NULL;
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('language_negotiator'),
      $container->get('plugin.manager.block'),
      $container->get('theme_handler'),
      $block_storage
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek.settings_tab_pulldown_form';
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $profiles = $this->L->get('profile');
    
    //$configurable = $this->languageTypes->get('configurable');

    $form = array(
      '#theme' => 'language_negotiation_configure_form',
      '#language_types_info' => $this->languageManager->getDefinedLanguageTypesInfo(),
      '#language_negotiation_info' => $this->negotiator->getNegotiationMethods(),
    );
    $form['#language_types'] = array();

    foreach ($form['#language_types_info'] as $type => $info) {
      // Show locked language types only if they are configurable.
      if (empty($info['locked']) || in_array($type, $configurable)) {
        $form['#language_types'][] = $type;
      }
    }

    foreach ($form['#language_types'] as $type) {
      $this->configureFormTable($form, $type);
    }

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Save settings'),
    );

     return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    dpm('Prefs!');
  }

  protected function configureFormTable(array &$form, $type)  {
    $info = $form['#language_types_info'][$type];

    $table_form = array(
      '#title' => $this->t('@type language detection', array('@type' => $info['name'])),
      '#tree' => TRUE,
      '#description' => $info['description'],
      '#language_negotiation_info' => array(),
      '#show_operations' => FALSE,
      'weight' => array('#tree' => TRUE),
    );
    // Only show configurability checkbox for the unlocked language types.
    if (empty($info['locked'])) {
      $configurable = $this->languageTypes->get('configurable');
      // $table_form['configurable'] = array(
      //   '#type' => 'checkbox',
      //   '#title' => $this->t('Customize %language_name language detection to differ from User interface text language detection settings', array('%language_name' => $info['name'])),
      //   '#default_value' => in_array($type, $configurable),
      //   '#attributes' => array('class' => array('language-customization-checkbox')),
      //   '#attached' => array(
      //     'library' => array(
      //       'language/drupal.language.admin'
      //     ),
      //   ),
      // );
    }

    $negotiation_info = $form['#language_negotiation_info'];
    $enabled_methods = $this->languageTypes->get('negotiation.' . $type . '.enabled') ?: array();
    $methods_weight = $this->languageTypes->get('negotiation.' . $type . '.method_weights') ?: array();

    // Add missing data to the methods lists.
    foreach ($negotiation_info as $method_id => $method) {
      if (!isset($methods_weight[$method_id])) {
        $methods_weight[$method_id] = isset($method['weight']) ? $method['weight'] : 0;
      }
    }

    // Order methods list by weight.
    asort($methods_weight);

    foreach ($methods_weight as $method_id => $weight) {
      // A language method might be no more available if the defining module has
      // been disabled after the last configuration saving.
      if (!isset($negotiation_info[$method_id])) {
        continue;
      }

      $enabled = isset($enabled_methods[$method_id]);
      $method = $negotiation_info[$method_id];

      // List the method only if the current type is defined in its 'types' key.
      // If it is not defined default to all the configurable language types.
      $types = array_flip(isset($method['types']) ? $method['types'] : $form['#language_types']);

      if (isset($types[$type])) {
        $table_form['#language_negotiation_info'][$method_id] = $method;
        $method_name = SafeMarkup::checkPlain($method['name']);

        $table_form['weight'][$method_id] = array(
          '#type' => 'weight',
          '#title' => $this->t('Weight for !title language detection method', array('!title' => Unicode::strtolower($method_name))),
          '#title_display' => 'invisible',
          '#default_value' => $weight,
          '#attributes' => array('class' => array("language-method-weight-$type")),
          '#delta' => 20,
        );

        $table_form['title'][$method_id] = array('#markup' => $method_name);

        $table_form['enabled'][$method_id] = array(
          '#type' => 'checkbox',
          '#title' => $this->t('Enable !title language detection method', array('!title' => Unicode::strtolower($method_name))),
          '#title_display' => 'invisible',
          '#default_value' => $enabled,
        );
        if ($method_id === LanguageNegotiationSelected::METHOD_ID) {
          $table_form['enabled'][$method_id]['#default_value'] = TRUE;
          $table_form['enabled'][$method_id]['#attributes'] = array('disabled' => 'disabled');
        }

        $table_form['description'][$method_id] = array(
          '#type' => 'select',
          '#options' => array('Good', 'Bad'),
        );

        $config_op = array();
        if (isset($method['config_route_name'])) {
          $config_op['configure'] = array(
            'title' => $this->t('Configure'),
            'url' => Url::fromRoute($method['config_route_name']),
          );
          // If there is at least one operation enabled show the operation
          // column.
          $table_form['#show_operations'] = TRUE;
        }
        $table_form['operation'][$method_id] = array(
         '#type' => 'operations',
         '#links' => $config_op,
        );
      }
    }
    $form[$type] = $table_form;
  }

}
