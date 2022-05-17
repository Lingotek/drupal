<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form for bulk management of job filtered content.
 */
class LingotekJobManagementContentEntitiesForm extends LingotekManagementFormBase {

  /**
   * The job ID
   *
   * @var string
   */
  protected $jobId;

  public function buildForm(array $form, FormStateInterface $form_state, $job_id = NULL) {
    $this->jobId = $job_id;
    $form = parent::buildForm($form, $form_state);
    $form['filters']['wrapper']['job']['#access'] = FALSE;
    $form['options']['options']['job_id']['#value'] = $this->jobId;
    $form['options']['options']['job_id']['#access'] = FALSE;
    return $form;
  }

  protected function getFilteredEntities() {
    $metadataStorage = $this->entityTypeManager->getStorage('lingotek_content_metadata');
    $entity_query = $metadataStorage->getQuery();
    $entity_query->condition('job_id', $this->jobId);
    $ids = $entity_query->execute();

    $metadatas = $metadataStorage->loadMultiple($ids);
    $entities = [];

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata $metadata */
    if (!empty($metadatas)) {
      foreach ($metadatas as $metadata) {
        $content_entity_type_id = $metadata->getContentEntityTypeId();
        $content_entity_id = $metadata->getContentEntityId();
        $entity = $this->entityTypeManager->getStorage($content_entity_type_id)
          ->load($content_entity_id);
        $entities[$content_entity_type_id][] = $entity;
      }
    }
    return $entities;
  }

  protected function getSelectedEntities($values) {
    $entityTypes = [];
    $entities = [];
    foreach ($values as $type_entity_id) {
      [$type, $entity_id] = explode(":", $type_entity_id);
      $entityTypes[$type][] = $entity_id;
    }

    foreach ($entityTypes as $type => $values) {
      $entities = array_merge($entities, $this->entityTypeManager->getStorage($type)->loadMultiple($values));
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_job_content_entities_management';
  }

  protected function getRows($entity_list) {
    $counter = 1;
    $rows = [];
    foreach ($entity_list as $entity_type_id => $entities) {
      foreach ($entities as $entity_id => $entity) {
        $rowId = (string) $entity->getEntityTypeId() . ':' . (String) $entity->id();
        $rows[$rowId] = $this->getRow($entity);
        $counter += 1;
      }
    }
    return $rows;
  }

  protected function getRow($entity) {
    $row = parent::getRow($entity);
    $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());

    if ($entity->hasLinkTemplate('canonical')) {
      $row['label'] = $entity->toLink();
    }
    else {
      $row['label'] = $entity->label();
    }
    $row['entity_type_id'] = $entity->getEntityType()->getLabel();
    $row['bundle'] = $bundleInfo[$entity->bundle()]['label'];
    return $row;
  }

  /**
   * Gets the key used for persisting filtering options in the temp storage.
   *
   * @return string
   *   Temp storage identifier where filters are persisted.
   */
  protected function getTempStorageFilterKey() {
    return NULL;
  }

  /**
   * Gets the filters for rendering.
   *
   * @return array
   *   A form array.
   */
  protected function getFilters() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPager() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function canHaveDeleteTranslationBulkOptions() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function canHaveDeleteBulkOptions() {
    return FALSE;
  }

}
