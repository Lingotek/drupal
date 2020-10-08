<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the option to use Lingotek to translate content entities
 *
 * @package Drupal\lingotek\Form
 */
class LingotekElementInfoAlterForm implements ContainerInjectionInterface {

  use StringTranslationTrait;

  use MessengerTrait;

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
   *
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
      $entity_type_id = $context['entity_type'];
      $bundle_id = $context['bundle'];

      if ($form['form_id']['#value'] !== 'language_content_settings_form') {
        $element['content_translation_for_lingotek'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable translation for Lingotek'),
          '#description' => $this->t('This will enable Lingotek translation for this bundle, and will enable default fields for translation. You can <a href=":settings">review those settings</a> later.', [':settings' => Url::fromRoute('lingotek.settings')->toString()]),
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
    $entity_type_id = $context['entity_type'];
    $bundle_id = $context['bundle'];
    $shouldClearCaches = FALSE;
    if ($enabled) {
      if ($enabled_for_lingotek) {
        if (!$this->lingotekConfiguration->isEnabled($entity_type_id, $bundle_id)) {
          $this->lingotekConfiguration->setEnabled($entity_type_id, $bundle_id);
          $entity_type = \Drupal::entityTypeManager()
            ->getDefinition($entity_type_id);

          $content_translation_manager = \Drupal::service('content_translation.manager');
          $storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);

          if ($content_translation_manager->isSupported($context['entity_type'])) {
            $fields = \Drupal::service('entity_field.manager')
              ->getFieldDefinitions($entity_type_id, $bundle_id);
            // Find which fields the user previously selected
            foreach ($fields as $field_id => $field_definition) {
              // We allow non-translatable entity_reference_revisions fields through.
              // See https://www.drupal.org/node/2788285
              if (!empty($storage_definitions[$field_id]) &&
                $storage_definitions[$field_id]->getProvider() != 'content_translation' &&
                !in_array($storage_definitions[$field_id]->getName(), [
                  $entity_type->getKey('langcode'),
                  $entity_type->getKey('default_langcode'),
                  'revision_translation_affected',
                ]) &&
                ($field_definition->isTranslatable() || ($field_definition->getType() == 'cohesion_entity_reference_revisions' || $field_definition->getType() == 'entity_reference_revisions' || $field_definition->getType() == 'path')) && !$field_definition->isComputed() && !$field_definition->isReadOnly()) {
                if ($value = $this->lingotekConfiguration->isFieldLingotekEnabled($entity_type_id, $bundle_id, $field_id)) {
                  break;
                }
                if ($this->lingotekConfiguration->shouldFieldLingotekEnabled($entity_type_id, $bundle_id, $field_id)) {
                  $this->lingotekConfiguration->setFieldLingotekEnabled($entity_type_id, $bundle_id, $field_id);
                }
              }
              // We have an exception here, if the entity alias is a computed field we
              // may still want to translate it.
              elseif ($field_definition->getType() == 'path' && $field_definition->isComputed()) {
                if ($value = $this->lingotekConfiguration->isFieldLingotekEnabled($entity_type_id, $bundle_id, $field_id)) {
                  break;
                }
                elseif ($this->lingotekConfiguration->shouldFieldLingotekEnabled($entity_type_id, $bundle_id, $field_id)) {
                  $this->lingotekConfiguration->setFieldLingotekEnabled($entity_type_id, $bundle_id, $field_id);
                }
              }
            }
          }
        }

        $shouldClearCaches = TRUE;
      }
      else {
        if ($this->lingotekConfiguration->isEnabled($context['entity_type'], $context['bundle'])) {
          $this->lingotekConfiguration->setEnabled($context['entity_type'], $context['bundle'], FALSE);
          $shouldClearCaches = TRUE;
        }
      }
    }
    else {
      $this->messenger()->addError(t("You must Enable Translation to Enable Translation for Lingotek"));
    }
    if ($shouldClearCaches) {
      $this->entityTypeManager->clearCachedDefinitions();
      $this->routeBuilder->setRebuildNeeded();
    }
  }

}
