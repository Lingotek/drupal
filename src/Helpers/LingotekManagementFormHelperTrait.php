<?php

namespace Drupal\lingotek\Helpers;

/**
 * Useful methods for management forms.
 */
trait LingotekManagementFormHelperTrait {

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @return int|mixed
   */
  protected function getItemsPerPage() {
    $items_per_page_temp_store = $this->tempStoreFactory->get('lingotek.management.items_per_page');
    $items_per_page = $items_per_page_temp_store->get('limit');
    if (!$items_per_page) {
      $items_per_page = 10;
      return $items_per_page;
    }
    return $items_per_page;
  }

  protected function setItemsPerPage($count) {
    $items_per_page_temp_store = $this->tempStoreFactory->get('lingotek.management.items_per_page');
    $items_per_page_temp_store->set('limit', $count);
  }

}
