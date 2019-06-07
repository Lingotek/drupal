<?php

namespace Drupal\lingotek;

class LingotekJobManagementService {

  public function getAllContentJobs(array &$jobs) {
    $entity_query = \Drupal::entityQuery('lingotek_content_metadata');
    $entity_query->exists('job_id');
    $ids = $entity_query->execute();

    $metadatas = $this->entityTypeManager()->getStorage('lingotek_content_metadata')
      ->loadMultiple($ids);
    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata $metadata */
    foreach ($metadatas as $metadata) {
      $job_id = $metadata->getJobId();
      if (!empty($job_id)) {
        if (!isset($jobs[$job_id])) {
          $jobs[$job_id] = [
            'id' => $job_id,
            'content' => 0,
            'config' => 0,
          ];
        }
        ++$jobs[$job_id]['content'];
      }
    }
    return $jobs;
  }

  public function getAllConfigJobs(array &$jobs) {
    $entity_query = \Drupal::entityQuery('lingotek_config_metadata');
    $entity_query->exists('job_id');
    $ids = $entity_query->execute();

    $metadatas = $this->entityTypeManager()->getStorage('lingotek_config_metadata')
      ->loadMultiple($ids);
    /** @var \Drupal\lingotek\Entity\LingotekConfigMetadata $metadata */
    foreach ($metadatas as $metadata) {
      $job_id = $metadata->getJobId();
      if (!empty($job_id)) {
        if (!isset($jobs[$job_id])) {
          $jobs[$job_id] = [
            'id' => $job_id,
            'content' => 0,
            'config' => 0,
          ];
        }
        ++$jobs[$job_id]['config'];
      }
    }
    return $jobs;
  }

}
