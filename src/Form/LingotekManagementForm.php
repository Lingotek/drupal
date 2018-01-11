<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\group\Entity\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for bulk management of content.
 */
class LingotekManagementForm extends LingotekManagementFormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity.manager'),
      $container->get('language_manager'),
      $container->get('entity.query'),
      $container->get('lingotek'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('content_translation.manager'),
      $container->get('lingotek.content_translation'),
      $container->get('user.private_tempstore'),
      $container->get('state'),
      $container->get('module_handler'),
      \Drupal::routeMatch()->getParameter('entity_type_id')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_management';
  }

  /**
   * {@inheritdoc}
   */
  protected function getFilteredEntities() {
    $items_per_page = $this->getItemsPerPage();

    /** @var PrivateTempStore $temp_store */
    $temp_store = $this->tempStoreFactory->get($this->getTempStorageFilterKey());

    $entity_type = $this->entityManager->getDefinition($this->entityTypeId);

    $query = $this->connection->select($entity_type->getBaseTable(), 'entity_table')->extend('\Drupal\Core\Database\Query\PagerSelectExtender');
    $query->fields('entity_table', [$entity_type->getKey('id')]);

    $has_bundles = $entity_type->get('bundle_entity_type') != 'bundle';

    $groupsExists = $this->moduleHandler->moduleExists('group') && $this->entityTypeId === 'node';

    // Filter results.
    $labelFilter = $temp_store->get('label');
    $bundleFilter = $temp_store->get('bundle');
    $profileFilter = $temp_store->get('profile');
    $sourceLanguageFilter = $temp_store->get('source_language');
    $groupFilter = $groupsExists ? $temp_store->get('group') : NULL;
    $jobFilter = $temp_store->get('job');

    if ($has_bundles && $bundleFilter) {
      $query->condition('entity_table.' . $entity_type->getKey('bundle'), $bundleFilter);
    }
    if ($labelFilter) {
      $labelKey = $entity_type->getKey('label');
      if ($labelKey) {
        $query->innerJoin($entity_type->getDataTable(), 'entity_data',
          'entity_table.' . $entity_type->getKey('id') . '= entity_data.' . $entity_type->getKey('id'));
        $query->condition('entity_data.' . $labelKey, '%' . $labelFilter . '%', 'LIKE');
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

    if ($profileFilter) {
      $metadata_type = $this->entityManager->getDefinition('lingotek_content_metadata');
      $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
        'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
      $query->condition('metadata.profile', $profileFilter);
    }
    if ($jobFilter) {
      $metadata_type = $this->entityManager->getDefinition('lingotek_content_metadata');
      $query->innerJoin($metadata_type->getBaseTable(), 'metadata',
        'entity_table.' . $entity_type->getKey('id') . '= metadata.content_entity_id AND metadata.content_entity_type_id = \'' . $entity_type->id() . '\'');
      $query->condition('metadata.job_id', '%' . $jobFilter . '%', 'LIKE');
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

    $ids = $query->limit($items_per_page)->execute()->fetchCol(0);
    $entities = $this->entityManager->getStorage($this->entityTypeId)->loadMultiple($ids);

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  protected function getSelectedEntities($values) {
    return $this->entityManager->getStorage($this->entityTypeId)->loadMultiple($values);
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

    $entity_type = $this->entityManager->getDefinition($this->entityTypeId);
    $properties = $this->entityManager->getBaseFieldDefinitions($this->entityTypeId);
    $has_bundles = $entity_type->get('bundle_entity_type') != 'bundle';

    $groupsExists = $this->moduleHandler->moduleExists('group') && $this->entityTypeId === 'node';

    $labelFilter = $temp_store->get('label');
    $bundleFilter = $temp_store->get('bundle');
    $profileFilter = $temp_store->get('profile');
    $sourceLanguageFilter = $temp_store->get('source_language');
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
    $filters['source_language'] = [
      '#type' => 'select',
      '#title' => $this->t('Source language'),
      '#options' => ['' => $this->t('All languages')] + $this->getAllLanguages(),
      '#default_value' => $sourceLanguageFilter,
      '#attributes' => ['class' => ['form-item']],
    ];
    $filters['profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Profile'),
      '#options' => ['' => $this->t('All')] + $this->lingotekConfiguration->getProfileOptions(),
      '#default_value' => $profileFilter,
      '#attributes' => ['class' => ['form-item']],
    ];
    $filters['job'] = [
      '#type' => 'textfield',
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
      ]
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
    $groupsExists = $this->moduleHandler->moduleExists('group') && $this->entityTypeId === 'node';
    $filtersKeys = ['label', 'profile', 'source_language', 'bundle', 'job'];
    if ($groupsExists) {
      $filtersKeys[] = 'group';
    }
    return $filtersKeys;
  }

}
