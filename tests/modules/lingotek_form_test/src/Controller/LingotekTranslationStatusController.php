<?php

namespace Drupal\lingotek_form_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for rendering the translation status of an entity for tests.
 *
 * @package Drupal\lingotek_form_test\Controller
 */
class LingotekTranslationStatusController extends ControllerBase {

  /**
   * Renders the Lingotek source status of the given entity.
   *
   * @param string $entity_type
   *   The entity type id from the entity which status we want to render.
   * @param string $entity_id
   *   The entity id from the entity which status we want to render.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return array
   *   A render array including a lingotek_source_status element.
   */
  public function renderSource($entity_type, $entity_id, Request $request) {
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $status = $translation_service->getSourceStatus($entity);

    return [
      '#type' => 'lingotek_source_status',
      '#entity' => $entity,
      '#language' => $entity->language(),
      '#status' => $status,
    ];
  }

  /**
   * Renders the Lingotek target status of the given entity.
   *
   * @param string $entity_type
   *   The entity type id from the entity which status we want to render.
   * @param string $entity_id
   *   The entity id from the entity which status we want to render.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return array
   *   A render array including a lingotek_target_status element.
   */
  public function renderTargetStatus($entity_type, $entity_id, Request $request) {
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    $elements = [
      '#cache' => ['max-age' => 0],
    ];
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $statuses = $translation_service->getTargetStatuses($entity);
    foreach ($statuses as $langcode => $status) {
      $elements[$langcode] = [
        '#type' => 'lingotek_target_status',
        '#entity' => $entity,
        '#language' => $langcode,
        '#status' => $status,
      ];
    }
    return $elements;
  }

  /**
   * Renders the Lingotek target statuses of the given entity.
   *
   * @param string $entity_type
   *   The entity type id from the entity which status we want to render.
   * @param string $entity_id
   *   The entity id from the entity which status we want to render.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return array
   *   A render array including a lingotek_target_statuses element.
   */
  public function renderTargetStatuses($entity_type, $entity_id, Request $request) {
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $statuses = $translation_service->getTargetStatuses($entity);

    return [
      '#type' => 'lingotek_target_statuses',
      '#entity' => $entity,
      '#source_langcode' => $entity->language()->getId(),
      '#statuses' => $statuses,
    ];
  }

}
