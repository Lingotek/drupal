<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Entity\LingotekProfile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LingotekBatchController extends LingotekControllerBase {

  public function dispatch($action, $entity_type, $entity_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $configuration_service */
    $configuration_service = \Drupal::service('lingotek.configuration');
    /** @var \Drupal\lingotek\Lingotek $lingotek */
    $lingotek = \Drupal::service('lingotek');

    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

    // This forces the hash to be set.
    if ($translation_service->hasEntityChanged($entity)) {
      $entity->save();
    }
    $profile = $configuration_service->getEntityProfile($entity, TRUE);

    switch ($action) {
      case 'uploadSingle':
        return $this->uploadSingle($entity_type, $entity_id, $profile);

      case 'downloadSingle':
        return $this->downloadSingle($entity_type, $entity_id, $profile);

      default:
        return $this->noAction();
    }
  }

  public function uploadSingle($entity_type, $entity_id, LingotekProfile $profile = NULL) {
    $batch = [
      'title' => $this->t('Uploading content to Lingotek'),
      'operations' => $this->getUploadOperations($entity_type, [$entity_id]),
      'error_message' => t('An error happened.'),
      'finished' => 'lingotek_operation_content_upload_finished',
      'file' => drupal_get_path('module', 'lingotek') . '/lingotek.batch.inc',
    ];
    $redirect_url = \Drupal::urlGenerator()->generate("entity.$entity_type.content_translation_overview",
      [$entity_type => $entity_id], UrlGeneratorInterface::ABSOLUTE_URL);
    batch_set($batch);
    return batch_process($redirect_url);
  }

  public function downloadSingle($entity_type, $entity_id, $locales) {
    $batch = [
      'title' => $this->t('Downloading translations from Lingotek'),
      'operations' => $this->getDownloadOperations($entity_type, [$entity_id], $locales),
      'finished' => 'lingotek_operation_content_download_finished',
      'file' => drupal_get_path('module', 'lingotek') . '/lingotek.batch.inc',
    ];
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);
    $redirect_url = \Drupal::urlGenerator()->generate("entity.$entity_type.content_translation_overview",
      [$entity_type => $entity_id], UrlGeneratorInterface::ABSOLUTE_URL);
    batch_set($batch);
    return batch_process($redirect_url);
  }

  public function checkUploadStatus($entity_type, $entity_id) {
    // TODO
  }

  public function checkTargetStatus($entity_type, $entity_id) {
    // TODO
  }

  public function addLanguageSingle($entity_type, $entity_id) {
    // TODO
  }

  protected function getUploadOperations($entity_type, $entity_ids) {
    $operations = [];
    if (is_array($entity_ids)) {
      foreach ($entity_ids as $id) {
        $operations[] = ['lingotek_operation_content_upload', [$entity_type, $id]];
      }
    }
    return $operations;
  }

  protected function getDownloadOperations($entity_type, $entity_ids, $locales) {
    $operations = [];
    if (is_array($entity_ids)) {
      foreach ($entity_ids as $id) {
        foreach ($locales as $locale) {
          $operations[] = ['lingotek_operation_translation_download', [$entity_type, $id, $locale]];
        }
      }
    }
    return $operations;
  }

  public function noAction() {
    $markup = $this->t('Lingotek batch operation error: You must supply a valid action.');
    return [
      '#type' => 'markup',
      '#markup' => $markup,
    ];
  }

}
