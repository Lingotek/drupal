<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk assignation of Job ID to content entities.
 *
 * @package Drupal\lingotek\Form
 */
class LingotekJobAssignToMultipleEntitiesForm extends FormBase {

  use RedirectDestinationTrait;

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
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface
   */
  protected $translationService;

  /**
   * The selection, in the $entity_type_id => entity_id => langcodes format.
   *
   * @var array
   */
  protected $selection = [];

  /**
   * Constructs a new LingotekJobAssignToMultipleEntitiesForm object.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The Lingotek content translation service.
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager, PrivateTempStoreFactory $temp_store_factory, MessengerInterface $messenger, LingotekContentTranslationServiceInterface $translation_service) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->tempStore = $temp_store_factory->get('lingotek_assign_job_entity_multiple_confirm');
    $this->messenger = $messenger;
    $this->translationService = $translation_service;
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
      $container->get('lingotek.content_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek.assign_job_entity_multiple_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $this->selection = $this->tempStore->get($this->currentUser->id());
    if (empty($this->selection)) {
      $form_state->setRedirectUrl(Url::fromUserInput('/' . $form_state->getValue('destination')));
    }

    $entities = $this->getSelectedEntities($this->selection);
    $items = array_map(function ($entity) {
      return $entity->label();
    }, $entities);

    $form['job_id'] = [
      '#type' => 'textfield',
      '#size' => 50,
      '#title' => $this->t('Job ID'),
      '#description' => $this->t('Assign a job id that you can filter on later on the TMS or in this page.'),
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

    $entities = $this->getSelectedEntities($this->selection);

    foreach ($entities as $entity) {
      $this->translationService->setJobId($entity, $job_id);
    }
    $form_state->setRedirectUrl(Url::fromUserInput('/' . $form_state->getValue('destination')));

    $this->messenger->addStatus('Job ID was assigned successfully.');

    // Clear selected data.
    $this->tempStore->delete($this->currentUser->id());
  }

  /**
   * Gets an array as in $entity_type_id:$id => $entity from the selection.
   *
   * @param string[][] $selection
   *   The selection.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   *   The entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getSelectedEntities($selection) {
    $entities = [];
    foreach ($selection as $entity_type_id => $ids) {
      $list = $this->entityTypeManager->getStorage($entity_type_id)
        ->loadMultiple(array_keys($ids));
      foreach ($list as $id => $entity) {
        $key = $entity_type_id . ':' . $id;
        $entities[$key] = $entity;
      }
    }
    return $entities;
  }

}
