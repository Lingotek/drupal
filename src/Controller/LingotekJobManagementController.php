<?php

namespace Drupal\lingotek\Controller;

use Drupal\Core\Url;

/**
 * Form for bulk management of content.
 */
class LingotekJobManagementController extends LingotekControllerBase {

  /**
   * List of all the Translation Jobs.
   */
  public function listJobs() {
    $jobs = [];
    $jobs += $this->getAllContentJobs($jobs);
    $jobs += $this->getAllConfigJobs($jobs);
    $rows = array_map(function ($item) {
      return [
        'id' => $item['id'],
        'tagged' =>
          $this->t('@content content items, @config config items', [
            '@content' => $item['content'],
            '@config' => $item['config'],
          ]),
        'link' => [
          'data' => [
            '#type' => 'link',
            '#title' => 'View translation job',
            '#url' => Url::fromRoute('lingotek.translation_job_info', ['job_id' => $item['id']]),
            '#attributes' => [
              'title' => t('View translation job'),
            ],
          ],
        ],
      ];
    }, $jobs);
    $table = [
      '#type' => 'table',
      '#header' => [
        'id' => $this->t('Job ID'),
        'tagged' => $this->t('Elements'),
        'link' => '',
      ],
      '#rows' => $rows,
      '#empty' => $this->t('There are no translation jobs. Use the Content or Config tabs to assign them.'),
    ];
    return $table;
  }

  public function title($job_id) {
    return $this->t('Job @job', ['@job' => $job_id]);
  }

  public function titleContent($job_id) {
    return $this->t('Job @job Content', ['@job' => $job_id]);
  }

  public function titleConfig($job_id) {
    return $this->t('Job @job Configuration', ['@job' => $job_id]);
  }

  protected function getAllContentJobs(array &$jobs) {
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

  protected function getAllConfigJobs(array &$jobs) {
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

  public function indexJob($job_id) {
    return $this->redirect('lingotek.translation_job_info.content', ['job_id' => $job_id]);
  }

}
