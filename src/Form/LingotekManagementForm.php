<?php

namespace Drupal\lingotek\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormStateInterface;

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
  protected function getFilteredEntities() {
    // The query will be initialized in FormComponentFilterBase.
    /** @var \Drupal\Core\Database\Query\PagerSelectExtender $query */
    $query = NULL;
    $temp_store = $this->tempStoreFactory->get($this->getTempStorageFilterKey());
    $submitted = $temp_store->get('filters') ?? [];

    foreach ($this->formFilters as $filter) {
      if ($filter_value = $filter->getSubmittedValue($submitted)) {
        $filter->filter($this->entityTypeId, [], $filter_value, $query);
      }
    }
    // This should never happen, but just in case.
    if (!$query) {
      return [];
    }

    $items_per_page = $this->getItemsPerPage();
    $ids = $query->limit($items_per_page)->execute()->fetchCol(0);
    return $this->entityTypeManager->getStorage($this->entityTypeId)->loadMultiple($ids);
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

    if ($this->formFilters) {
      $submitted = $this->tempStoreFactory->get($this->getTempStorageFilterKey())->get('filters') ?? [];

      foreach ($this->formFilters as $filter_id => $filter) {
        if ($group = $filter->getGroupMachineName()) {
          if (!isset($filters[$group])) {
            $filters[$group] = $filter->buildGroupElement();
          }
        }

        $value = $filter->getSubmittedValue($submitted);
        $parents = $filter->getFilterKey();
        NestedArray::setValue($filters, $parents, $filter->buildElement($value ?? NULL));
      }
    }

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

  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

}
