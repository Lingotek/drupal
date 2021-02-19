<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\group\Entity\Group;
use Drupal\lingotek\Lingotek;

/**
 * Form for bulk management of content.
 */
class LingotekManagementForm extends LingotekManagementFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_management';
  }

  /**
   * {@inheritdoc}
   */
  protected function getHeaders() {
    $entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);
    $properties = $this->entityFieldManager->getBaseFieldDefinitions($this->entityTypeId);
    $has_bundles = $entity_type->get('bundle_entity_type') != 'bundle';
    if ($has_bundles) {
      $headers['bundle'] = $entity_type->getBundleLabel();
    }
    $headers += [
      'title' => $has_bundles && $entity_type->hasKey('label') ? $properties[$entity_type->getKey('label')]->getLabel() : $entity_type->getLabel(),
      'source' => $this->t('Source'),
      'translations' => $this->t('Translations'),
      'profile' => $this->t('Profile'),
      'job_id' => $this->t('Job ID'),
    ];
    return $headers;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilteredEntities() {
    $items_per_page = $this->getItemsPerPage();

    /** @var PrivateTempStore $temp_store */
    $temp_store = $this->tempStoreFactory->get($this->getTempStorageFilterKey());

    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    $entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query = $this->connection->select($entity_type->getBaseTable(), 'entity_table')->extend('\Drupal\Core\Database\Query\PagerSelectExtender');
    $query->fields('entity_table', [$entity_type->getKey('id')]);

    $has_bundles = $entity_type->get('bundle_entity_type') != 'bundle';

    $groupsExists = $this->moduleHandler->moduleExists('gnode') && $this->entityTypeId === 'node';

    // Filter results
    // Default options
    $labelFilter = $temp_store->get('label');
    $bundleFilter = $temp_store->get('bundle');
    $groupFilter = $groupsExists ? $temp_store->get('group') : NULL;
    $jobFilter = $temp_store->get('job');

    // Advanced options
    $documentIdFilter = $temp_store->get('document_id');
    $entityIdFilter = $temp_store->get('entity_id');
    $sourceLanguageFilter = $temp_store->get('source_language');
    $sourceStatusFilter = $temp_store->get('source_status');
    $targetStatusFilter = $temp_store->get('target_status');
    $contentStateFilter = $temp_store->get('content_state');
    $profileFilter = $temp_store->get('profile');

    if ($sourceStatusFilter) {
      if ($sourceStatusFilter === 'UPLOAD_NEEDED') {
        // We consider that "Upload Needed" includes those never uploaded or
        // disassociated, edited, or with error on last upload.
        $needingUploadStatuses = [
          Lingotek::STATUS_EDITED,
          Lingotek::STATUS_REQUEST,
          Lingotek::STATUS_CANCELLED,
          Lingotek::STATUS_ERROR,
        ];

        // Filter metadata by content_entity_type_id if exists
        $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
        $query->leftJoin($metadata_type->getBaseTable(), 'metadata_source',
        'entity_table.' . $entity_type->getKey('id') . '= metadata_source.content_entity_id');
        $orMetadata = $query->orConditionGroup()
          ->condition('metadata_source.content_entity_type_id', $entity_type->id())
          ->isNull('metadata_source.content_entity_id');
        $query->condition($orMetadata);

        // Filter translation status by translation_status_language and status if exists
        $query->leftJoin('lingotek_content_metadata__translation_status', 'translation_status',
          'entity_table.' . $entity_type->getKey('id') . ' = translation_status.entity_id AND translation_status.translation_status_language = entity_table.langcode');

        $orTranslationStatus = $query->orConditionGroup()
          ->condition('translation_status.translation_status_value', $needingUploadStatuses, 'IN')
          ->isNull('translation_status.entity_id');

        $query->condition($orTranslationStatus);
      }
      else {
        $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
        $query->innerJoin($metadata_type->getBaseTable(), 'metadata_source',
          'entity_table.' . $entity_type->getKey('id') . '= metadata_source.content_entity_id AND metadata_source.content_entity_type_id = \'' . $entity_type->id() . '\'');
        $query->innerJoin('lingotek_content_metadata__translation_status', 'translation_status',
          'metadata_source.id = translation_status.entity_id AND translation_status.translation_status_language = entity_table.' . $entity_type->getKey('langcode'));
        $query->condition('translation_status.translation_status_value', $sourceStatusFilter);
      }
    }
    // Default queries
    if ($has_bundles && $bundleFilter) {
      if (!in_array("", $bundleFilter, TRUE)) {
        $query->condition('entity_table.' . $entity_type->getKey('bundle'), $bundleFilter, 'IN');
      }
    }
    if ($labelFilter) {
      $labelKey = $entity_type->getKey('label');
      if ($labelKey) {
        $query->innerJoin($entity_type->getDataTable(), 'entity_data',
          'entity_table.' . $entity_type->getKey('id') . '= entity_data.' . $entity_type->getKey('id'));
        $query->condition('entity_data.' . $labelKey, '%' . $labelFilter . '%', 'LIKE');
      }
    }
    if ($groupFilter) {
      /** @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface $groupContentEnablers */
      $groupType = Group::load($groupFilter)->getGroupType();
      $groupContentEnablers = \Drupal::service('plugin.manager.group_content_enabler');
      $definitions = $groupContentEnablers->getDefinitions();
      $definitions = array_filter($definitions, function ($definition) {
        return ($definition['entity_type_id'] === 'node');
      });
      $valid_values = [];
      foreach ($definitions as $node_definition) {
        $valid_values[] = $groupType->id() . '-' . $node_definition['id'] . '-' . $node_definition['entity_bundle'];
      }
      $query->innerJoin('group_content_field_data', 'group_content',
        'entity_table.' . $entity_type->getKey('id') . '= group_content.entity_id');
      $query->condition('group_content.gid', $groupFilter);
      $query->condition('group_content.type', $valid_values, 'IN');
    }
    if ($jobFilter) {
      /** @var \Drupal\Core\Entity\EntityTypeInterface $metadata_type */
      $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
      $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
        'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
      $query->condition('metadata.job_id', '%' . $jobFilter . '%', 'LIKE');
    }
    // Advanced queries
    if ($documentIdFilter) {
      $documentIdArray = explode(',', $documentIdFilter);
      array_walk($documentIdArray, function (&$value) {
        $value = trim($value);
      });
      $documentIdOperator = (count($documentIdArray) > 1) ? 'IN' : 'LIKE';
      $documentIdValue = (count($documentIdArray) > 1) ? $documentIdArray : '%' . $documentIdFilter . '%';

      $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
      $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
        'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
      $query->condition('metadata.document_id', $documentIdValue, $documentIdOperator);
    }
    if ($entityIdFilter) {
      $entityIdArray = explode(',', $entityIdFilter);
      array_walk($entityIdArray, function (&$value) {
        $value = trim($value);
      });
      $entityIdOperator = (count($entityIdArray) > 1) ? 'IN' : '=';
      $entityIdValue = (count($entityIdArray) > 1) ? $entityIdArray : $entityIdFilter;

      $query->innerJoin($entity_type->getDataTable(), 'entity_data',
      'entity_table.' . $entity_type->getKey('id') . '= entity_data.' . $entity_type->getKey('id'));

      $query->condition('entity_table.' . $entity_type->getKey('id'), $entityIdValue, $entityIdOperator);
    }
    if ($profileFilter) {
      if (is_string($profileFilter)) {
        $profileFilter = [$profileFilter];
      }
      if (!in_array("", $profileFilter, TRUE)) {
        $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
        $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
          'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
        $query->condition('metadata.profile', $profileFilter, 'IN');
      }
    }
    if ($sourceLanguageFilter) {
      $query->innerJoin($entity_type->getDataTable(), 'entity_data',
        'entity_table.' . $entity_type->getKey('id') . '= entity_data.' . $entity_type->getKey('id'));
      $query->condition('entity_table.' . $entity_type->getKey('langcode'), $sourceLanguageFilter);
      $query->condition('entity_data.default_langcode', 1);
    }
    // We don't want items with language undefined.
    $query->condition('entity_table.' . $entity_type->getKey('langcode'), LanguageInterface::LANGCODE_NOT_SPECIFIED, '!=');

    if ($targetStatusFilter) {
      $subquery = $this->connection->select($entity_type->getBaseTable(), 'entity_table')->extend('\Drupal\Core\Database\Query\PagerSelectExtender');
      $subquery->fields('entity_table', [$entity_type->getKey('id')]);
      $metadata_type = $this->entityTypeManager->getDefinition('lingotek_content_metadata');
      $subquery->innerJoin($metadata_type->getBaseTable(), 'metadata_target',
        'entity_table.' . $entity_type->getKey('id') . '= metadata_target.content_entity_id AND metadata_target.content_entity_type_id = \'' . $entity_type->id() . '\'');
      $subquery->innerJoin('lingotek_content_metadata__translation_status', 'translation_target_status',
       'metadata_target.id = translation_target_status.entity_id AND translation_target_status.translation_status_language <> entity_table.' . $entity_type->getKey('langcode'));
      $subquery->condition('translation_target_status.translation_status_value', $targetStatusFilter);
      $query->condition('entity_table.' . $entity_type->getKey('id'), $subquery, 'IN');
    }

    if ($contentStateFilter != '') {
      $content_moderation_type = $this->entityTypeManager->getDefinition('content_moderation_state');
      $query->innerJoin($content_moderation_type->getDataTable(), 'content_moderation_data',
      'entity_table.' . $entity_type->getKey('id') . '= content_moderation_data.content_entity_id');
      $query->condition('content_moderation_data.moderation_state', $contentStateFilter);
    }

    $ids = $query->limit($items_per_page)->execute()->fetchCol(0);
    $entities = $this->entityTypeManager->getStorage($this->entityTypeId)->loadMultiple($ids);

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedEntities($values) {
    return $this->entityTypeManager->getStorage($this->entityTypeId)->loadMultiple($values);
  }

  /**
   * {@inheritdoc}
   */
  protected function getRows($entity_list) {
    $rows = [];
    foreach ($entity_list as $entity_id => $entity) {
      $rows[$entity_id] = $this->getRow($entity);
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilters() {
    $filters = [];

    $temp_store = $this->tempStoreFactory->get($this->getTempStorageFilterKey());

    $entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);
    $properties = $this->entityFieldManager->getBaseFieldDefinitions($this->entityTypeId);
    $has_bundles = $entity_type->get('bundle_entity_type') != 'bundle';

    $groupsExists = $this->moduleHandler->moduleExists('gnode') && $this->entityTypeId === 'node';

    $labelFilter = $temp_store->get('label');
    $bundleFilter = $temp_store->get('bundle');
    $groupFilter = $groupsExists ? $temp_store->get('group') : NULL;
    $jobFilter = $temp_store->get('job');

    if ($entity_type->getKey('label')) {
      $filters['label'] = [
        '#type' => 'textfield',
        '#title' => $has_bundles && $entity_type->hasKey('label') ? $properties[$entity_type->getKey('label')]->getLabel() : $entity_type->getLabel(),
        '#placeholder' => $this->t('Filter by @title', ['@title' => $entity_type->getBundleLabel()]),
        '#default_value' => $labelFilter,
        '#attributes' => ['class' => ['form-item']],
      ];
    }
    if ($has_bundles) {
      $filters['bundle'] = [
        '#type' => 'select',
        '#title' => $entity_type->getBundleLabel(),
        '#options' => ['' => $this->t('All')] + $this->getAllBundles(),
        '#default_value' => $bundleFilter,
        '#attributes' => ['class' => ['form-item']],
        '#multiple' => TRUE,
      ];
    }
    if ($groupsExists) {
      $filters['group'] = [
        '#type' => 'select',
        '#title' => $this->t('Group'),
        '#options' => ['' => $this->t('All')] + $this->getAllGroups(),
        '#default_value' => $groupFilter,
        '#attributes' => ['class' => ['form-item']],
      ];
    }
    $filters['job'] = [
      '#type' => 'lingotek_job_id',
      '#title' => $this->t('Job ID'),
      '#default_value' => $jobFilter,
      '#attributes' => ['class' => ['form-item']],
    ];
    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPager() {
    $items_per_page = $this->getItemsPerPage();

    return [
      '#type' => 'select',
      '#title' => $this->t('Results per page:'),
      '#options' => [10 => 10, 25 => 25, 50 => 50, 100 => 100, 250 => 250, 500 => 500],
      '#default_value' => $items_per_page,
      '#weight' => 60,
      '#ajax' => [
        'callback' => [$this, 'itemsPerPageCallback'],
        'event' => 'change',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function itemsPerPageCallback(array &$form, FormStateInterface $form_state) {
    $ajax_response = new AjaxResponse();
    $this->setItemsPerPage($form_state->getValue('items_per_page'));
    $ajax_response->addCommand(new InvokeCommand('#lingotek-management', 'submit'));
    return $ajax_response;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTempStorageFilterKey() {
    return 'lingotek.management.filter.' . $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilterKeys() {
    $groupsExists = $this->moduleHandler->moduleExists('gnode') && $this->entityTypeId === 'node';
    // We need specific identifiers for default and advanced filters since the advanced filters bundle is unique.
    $filtersKeys = [['wrapper', 'label'], ['wrapper', 'bundle'], ['wrapper', 'job'], ['advanced_options', 'document_id'], ['advanced_options', 'entity_id'], ['advanced_options', 'profile'], ['advanced_options', 'source_language'], ['advanced_options', 'source_status'], ['advanced_options', 'target_status'], ['advanced_options', 'content_state']];
    if ($groupsExists) {
      $filtersKeys[] = ['wrapper', 'group'];
    }
    return $filtersKeys;
  }

  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

}
