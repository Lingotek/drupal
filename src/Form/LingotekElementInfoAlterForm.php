<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the option to use Lingotek to translate content entities
 *
 * @package Drupal\lingotek\Form
 */
class LingotekElementInfoAlterForm implements ContainerInjectionInterface {

  use StringTranslationTrait;

  use DependencySerializationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route builder service.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * Constructs a new LingotekElementInfoAlterForm object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   An entity type manager object.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder service.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, RouteBuilderInterface $route_builder, LingotekConfigurationServiceInterface $lingotek_configuration) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->routeBuilder = $route_builder;
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('router.builder'),
      $container->get('lingotek.configuration')
    );
  }

  /**
   * Add a process when element info is altered
   */
  public function type(&$type) {
    if (isset($type['language_configuration'])) {
      $type['language_configuration']['#process'][] = [
        $this,
        'process',
      ];
    }
  }

  /**
   * Process callback: Expands the language_configuration form element.
   *
   * @param array $element
   *   Form API element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The form.
   * @return array
   *   Processed language configuration element.
   */
  public function process(array $element, FormStateInterface $form_state, array &$form) {
    if (empty($element['#content_translation_for_lingotek_skip_alter']) &&
        $this->currentUser->hasPermission('administer content translation') &&
        $this->currentUser->hasPermission('administer lingotek')) {
      $key = $element['#name'];
      $form_state->set(['content_translation_for_lingotek', 'key'], $key);
      $context = $form_state->get(['language', $key]);

      if ($form['form_id']['#value'] !== 'language_content_settings_form') {
        $element['content_translation_for_lingotek'] = [
          '#type' => 'checkbox',
          '#title' => ('Enable translation for Lingotek'),
          '#default_value' => $context['bundle'] !== NULL && $this->lingotekConfiguration->isEnabled($context['entity_type'], $context['bundle']),
          '#states' => [
            'visible' => [
              ':input[name="language_configuration[content_translation]"]' => ['checked' => TRUE],
            ],
          ],
        ];

        $submit_name = isset($form['actions']['save_continue']) ? 'save_continue' : 'submit';
        // Only add the submit handler on the submit button if the #submit property
        // is already available, otherwise this breaks the form submit function.
        if (isset($form['actions'][$submit_name]['#submit'])) {
          $form['actions'][$submit_name]['#submit'][] = [
            $this,
            'submit',
          ];
        }
        else {
          $form['#submit'][] = [$this, 'submit'];
        }
      }
    }
    return $element;
  }

  /**
   * Form submission handler for element added with lingotek_language_configuration_element_process().
   *
   * Stores the content translation settings.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @see lingotek_language_configuration_element_validate()
   */
  public function submit(array $form, FormStateInterface $form_state) {
    $key = $form_state->get(['content_translation_for_lingotek', 'key']);
    $context = $form_state->get(['language', $key]);
    $enabled_for_lingotek = $form_state->getValue([
      $key,
      'content_translation_for_lingotek',
    ]);
    $enabled = $form_state->getValue([$key, 'content_translation']);

    if ($enabled) {
      if ($enabled_for_lingotek) {
        if (!$this->lingotekConfiguration->isEnabled($context['entity_type'], $context['bundle'])) {
          $this->lingotekConfiguration->setEnabled($context['entity_type'], $context['bundle']);
          $this->entityTypeManager->clearCachedDefinitions();
          $this->routeBuilder->setRebuildNeeded();
        }
      }
      else {
        if ($this->lingotekConfiguration->isEnabled($context['entity_type'], $context['bundle'])) {
          $this->lingotekConfiguration->setEnabled($context['entity_type'], $context['bundle'], FALSE);
          $this->entityTypeManager->clearCachedDefinitions();
          $this->routeBuilder->setRebuildNeeded();
        }
      }
    }
    else {
      drupal_set_message(t("You must Enable Translation to Enable Translation for Lingotek"), 'error');
    }
  }

}
