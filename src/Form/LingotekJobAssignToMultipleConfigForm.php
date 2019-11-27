<?php

namespace Drupal\lingotek\Form;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\ConfigFieldMapper;
use Drupal\config_translation\ConfigMapperInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\field\Entity\FieldConfig;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk assignation of Job ID to config entities.
 *
 * @package Drupal\lingotek\Form
 */
class LingotekJobAssignToMultipleConfigForm extends FormBase {

  use RedirectDestinationTrait;

  /**
   * A array of configuration mapper instances.
   *
   * @var \Drupal\config_translation\ConfigMapperInterface[]
   */
  protected $mappers;

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
   * The tempstore.
   *
   * @var \Drupal\Core\TempStore\SharedTempStore
   */
  protected $tempStore;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface
   */
  protected $translationService;

  /**
   * The selection, in the $entity_type_id => entity_id => langcodes format.
   *
   * @var array
   */
  protected $selection = [];

  /**
   * Constructs a new LingotekJobAssignToMultipleConfigForm object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service
   *   The Lingotek config translation service.
   * @param \Drupal\config_translation\ConfigMapperInterface[] $mappers
   *   The configuration mappers.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $temp_store_factory, MessengerInterface $messenger, LingotekConfigTranslationServiceInterface $translation_service, array $mappers) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->tempStore = $temp_store_factory->get('lingotek_assign_job_config_multiple_confirm');
    $this->messenger = $messenger;
    $this->translationService = $translation_service;
    $this->mappers = $mappers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('messenger'),
      $container->get('lingotek.config_translation'),
      $container->get('plugin.manager.config_translation.mapper')->getMappers()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek.assign_job_config_multiple_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->selection = $this->tempStore->get($this->currentUser->id());
    if (empty($this->selection)) {
      $form_state->setRedirectUrl(Url::fromUserInput('/' . $form_state->getValue('destination')));
    }

    $mappers = $this->getSelectedMappers($this->selection);
    $items = array_map(function ($mapper) {
      return $this->getMapperLabel($mapper);
    }, $mappers);

    $form['job_id'] = [
      '#type' => 'lingotek_job_id',
      '#title' => $this->t('Job ID'),
      '#description' => $this->t('Assign a job id that you can filter on later on the TMS or in this page.'),
    ];
    $form['update_tms'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify the Lingotek TMS'),
      '#description' => $this->t('Notify the Lingotek TMS (when applicable)'),
    ];

    $form['entities'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Affected content'),
      '#items' => $items,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Assign Job ID'),
      '#submit' => ['::submitForm'],
      '#button_type' => 'primary',
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancelForm'],
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function cancelForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl(Url::fromUserInput('/' . $form_state->getValue('destination')));

    // Clear selected data.
    $this->tempStore->delete($this->currentUser->id());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $job_id = $form_state->getValue('job_id');
    $updateTMS = $form_state->getValue('update_tms');
    $errors = FALSE;

    $mappers = $this->getSelectedMappers($this->selection);

    foreach ($mappers as $mapper) {
      if ($mapper instanceof ConfigEntityMapper) {
        try {
          $entity = $mapper->getEntity();
          $this->translationService->setJobId($entity, $job_id, $updateTMS);
        }
        catch (LingotekPaymentRequiredException $exception) {
          $errors = TRUE;
          $this->messenger->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
        }
        catch (LingotekDocumentArchivedException $exception) {
          $errors = TRUE;
          $this->messenger->addError(t('Document @entity_type %title has been archived. Please upload again.', [
            '@entity_type' => $entity->getEntityTypeId(),
            '%title' => $entity->label(),
          ]));
        }
        catch (LingotekDocumentLockedException $exception) {
          $errors = TRUE;
          $this->messenger->addError(t('Document @entity_type %title has a new version. The document id has been updated for all future interactions. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
        }
        catch (LingotekApiException $exception) {
          $errors = TRUE;
          $this->messenger()
            ->addError(t('The Job ID change submission for @entity_type %title failed. Please try again.', [
              '@entity_type' => $entity->getEntityTypeId(),
              '%title' => $entity->label(),
            ]));
        }
      }
      else {
        try {
          $this->translationService->setConfigJobId($mapper, $job_id, $updateTMS);
        }
        catch (LingotekPaymentRequiredException $exception) {
          $errors = TRUE;
          $this->messenger->addError(t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
        }
        catch (LingotekDocumentArchivedException $exception) {
          $errors = TRUE;
          $this->messenger->addError(t('Document %label has been archived. Please upload again.',
            ['%label' => $mapper->getTitle()]));
        }
        catch (LingotekDocumentLockedException $exception) {
          $errors = TRUE;
          $this->messenger->addError(t('Document %label has a new version. The document id has been updated for all future interactions. Please try again.',
            ['%label' => $mapper->getTitle()]));
        }
        catch (LingotekApiException $exception) {
          $errors = TRUE;
          $this->messenger->addError(t('The Job ID change submission for %label failed. Please try again.',
              ['%label' => $mapper->getTitle()]));
        }
      }
    }
    $form_state->setRedirectUrl(Url::fromUserInput('/' . $form_state->getValue('destination')));

    if (!$errors) {
      $this->postStatusMessage();
    }
    else {
      $this->messenger->addWarning($this->t('Job ID for some config failed to sync to the TMS.'));
    }

    // Clear selected data.
    $this->tempStore->delete($this->currentUser->id());
  }

  /**
   * Post a status message when succeeded.
   */
  protected function postStatusMessage() {
    $this->messenger->addStatus('Job ID was assigned successfully.');
  }

  /**
   * Gets the select mappers from their IDs.
   *
   * @param $values
   *   Array of ids.
   *
   * @return \Drupal\config_translation\ConfigNamesMapper[]
   *   The mappers.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getSelectedMappers($values) {
    $mappers = [];
    foreach ($values as $type => $data) {
      if ($type === 'config') {
        foreach ($data as $key => $languages) {
          $mappers[$key] = $this->mappers[$key];
        }
      }
      elseif (substr($type, -7) == '_fields') {
        $mapper = $this->mappers[$type];
        $ids = \Drupal::entityQuery('field_config')
          ->condition('id', array_keys($data))
          ->execute();
        $fields = FieldConfig::loadMultiple($ids);
        $mappers = [];
        foreach ($fields as $id => $field) {
          $new_mapper = clone $mapper;
          $new_mapper->setEntity($field);
          $mappers[$field->id()] = $new_mapper;
        }
      }
      else {
        $entities = $this->entityTypeManager->getStorage($type)
          ->loadMultiple(array_keys($data));
        foreach ($entities as $entity) {
          $mapper = clone $this->mappers[$type];
          $mapper->setEntity($entity);
          $mappers[$entity->id()] = $mapper;
        }
      }
    }
    return $mappers;
  }

  /**
   * Gets a user-friendly label for a mapper.
   *
   * @param \Drupal\config_translation\ConfigMapperInterface $mapper
   *   The mapper.
   *
   * @return string
   *   A user-friendly label.
   */
  protected function getMapperLabel(ConfigMapperInterface $mapper) {
    $label = '';
    if ($mapper instanceof ConfigFieldMapper) {
      $label = $mapper->getTitle() . '(' . $this->getFieldBundleLabel($mapper) . ')';
    }
    elseif ($mapper instanceof ConfigEntityMapper) {
      $label = $mapper->getTitle();
    }
    else {
      $label = $mapper->getTitle();
    }
    return $label;
  }

  /**
   * Gets a user-friendly label for a mapper bundle.
   *
   * @param \Drupal\config_translation\ConfigFieldMapper $mapper
   *   The mapper.
   *
   * @return string
   *   A user-friendly label.
   */
  protected function getFieldBundleLabel(ConfigFieldMapper $mapper) {
    $label = '';
    $entity_type_id = $mapper->getEntity()->get('entity_type');
    $bundle = $mapper->getEntity()->get('bundle');
    $bundle_info = \Drupal::service('entity_type.bundle.info')
      ->getBundleInfo($entity_type_id);
    if (isset($bundle_info[$bundle])) {
      $label = $bundle_info[$bundle]['label'];
    }
    else {
      $label = $bundle;
    }
    return $label;
  }

}
