<?php

namespace Drupal\lingotek;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of Lingotek profile entities.
 *
 * @see \Drupal\lingotek\Entity\LingotekProfile
 */
class LingotekProfileListBuilder extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  protected $entitiesKey = 'profile';

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('language_manager'),
      $container->get('config.factory')
    );
  }

  /**
   * Constructs a new LingotekProfileListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage controller class.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, LanguageManagerInterface $language_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($entity_type, $storage);
    $this->languageManager = $language_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = $this->storage->loadMultiple();

    // Sort the entities using the entity class's sort() method.
    // See \Drupal\Core\Config\Entity\ConfigEntityBase::sort().
    uasort($entities, [$this->entityType->getClass(), 'sort']);
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_profile_admin_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
        'label' => t('Name'),
        'auto_upload' => t('Automatic Upload'),
        'auto_request' => t('Automatic Request'),
        'auto_download' => t('Automatic Download'),
      ] + parent::buildHeader();
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\lingotek\LingotekProfileInterface $entity */
    $row['label'] = $entity->label();
    $row['auto_upload'] = [
      '#type' => 'checkbox',
      '#title' => t('Set @title for automatic upload', ['@title' => $entity->label()]),
      '#title_display' => 'invisible',
      '#disabled' => $entity->isLocked(),
      '#default_value' => $entity->hasAutomaticUpload(),
    ];
    $row['auto_request'] = [
      '#type' => 'checkbox',
      '#title' => t('Set @title for automatic request', ['@title' => $entity->label()]),
      '#title_display' => 'invisible',
      '#disabled' => $entity->isLocked(),
      '#default_value' => $entity->hasAutomaticRequest(),
    ];
    $row['auto_download'] = [
      '#type' => 'checkbox',
      '#title' => t('Set @title for automatic download', ['@title' => $entity->label()]),
      '#title_display' => 'invisible',
      '#disabled' => $entity->isLocked(),
      '#default_value' => $entity->hasAutomaticDownload(),
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form[$this->entitiesKey]['#profiles'] = $this->entities;
    $form['actions']['submit']['#value'] = t('Save configuration');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Parent submit handler takes care of the weights, but not the checkboxes.
    parent::submitForm($form, $form_state);
    /** @var \Drupal\lingotek\LingotekProfileInterface $entity */
    foreach ($this->entities as $entity_id => $entity) {
      if (!$entity->isLocked() && (
          $entity->hasAutomaticRequest() != $form_state->getValue(['profile', $entity_id, 'auto_request']) ||
          $entity->hasAutomaticUpload() != $form_state->getValue(['profile', $entity_id, 'auto_upload']) ||
          $entity->hasAutomaticDownload() != $form_state->getValue(['profile', $entity_id, 'auto_download']))) {
        $entity->setAutomaticUpload($form_state->getValue(['profile', $entity_id, 'auto_upload']));
        $entity->setAutomaticRequest($form_state->getValue(['profile', $entity_id, 'auto_request']));
        $entity->setAutomaticDownload($form_state->getValue(['profile', $entity_id, 'auto_download']));
        $entity->save();
      }
    }
    $this->messenger()->addStatus(t('Configuration saved.'));
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    // We don't call parent, as we don't want config_translation operations.
    $operations = [];
    if (!$entity->isLocked() && $entity->hasLinkTemplate('edit-form')) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'weight' => 10,
        'url' => $entity->toUrl('edit-form'),
      ];
    }
    if (!$entity->isLocked() && $entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 100,
        'url' => $entity->toUrl('delete-form'),
      ];
    }
    return $operations;
  }

}
