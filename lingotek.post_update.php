<?php

/**
 * @file
 * Post update functions for Lingotek.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Site\Settings;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\LingotekProfileInterface;
use Drupal\user\Entity\Role;

/**
 * Add the new 'manage lingotek translations' permission to roles already with
 * 'administer lingotek' permission.
 */
function lingotek_post_update_lingotek_manage_lingotek_translations_permission(&$sandbox) {
  $roles = Role::loadMultiple();
  foreach ($roles as $role) {
    if ($role->hasPermission('administer lingotek')) {
      $role->grantPermission('manage lingotek translations');
      $role->save();
    }
  }
}

/**
 * Fix lingotek metadata entities with dependencies on config entities which
 * names were wrongly calculated.
 */
function lingotek_post_update_lingotek_metadata_dependencies() {
  $metadatas = LingotekConfigMetadata::loadMultiple();
  array_walk($metadatas, function (LingotekConfigMetadata $metadata) {
    $old_dependencies = $metadata->getDependencies();
    $new_dependencies = $metadata->calculateDependencies()->getDependencies();
    if ($old_dependencies !== $new_dependencies) {
      $metadata->save();
    }
  });
}

/**
 * Update job ids for content metadata, replacing invalid chars.
 */
function lingotek_post_update_lingotek_content_metadata_job_id(&$sandbox = NULL) {
  // Initialize sandbox info.
  $pendingEntities = &$sandbox['entities'];
  if (!isset($pendingEntities)) {
    $storage = \Drupal::entityTypeManager()->getStorage('lingotek_content_metadata');
    $query = $storage->getQuery();
    $pendingEntities = $query->execute();

    $sandbox['limit'] = Settings::get('entity_update_batch_size', 50);
    $sandbox['total'] = count($pendingEntities);
    $sandbox['entities'] = $pendingEntities;
  }

  /** @var \Drupal\lingotek\LingotekInterface $lingotek */
  $lingotek = \Drupal::service('lingotek');

  $ids = [];
  for ($var = 0; $var < $sandbox['limit'] && !empty($pendingEntities); $var++) {
    $ids[] = array_pop($pendingEntities);
  }
  $metadatas = LingotekContentMetadata::loadMultiple($ids);
  array_walk($metadatas, function (LingotekContentMetadata $metadata) use ($lingotek) {
    $job_id = $metadata->getJobId();
    if (preg_match('@[\/\\\]+@', $job_id)) {
      $new_job_id = str_replace('/', '-', $job_id);
      $new_job_id = str_replace('\\', '-', $new_job_id);
      if ($new_job_id !== $job_id) {
        $metadata->setJobId($new_job_id);
        $metadata->save();
        if ($document_id = $metadata->getDocumentId()) {
          try {
            $lingotek->updateDocument($document_id, NULL, NULL, NULL, NULL, $new_job_id);
          }
          catch (LingotekApiException $exception) {
            \Drupal::logger('lingotek')
              ->error("Update of job_id %job failed for document %document",
                ['%job' => $new_job_id, '%document' => $document_id]);
          }
        }
      }
    }
  });

  $sandbox['entities'] = $pendingEntities;

  $sandbox['#finished'] = empty($sandbox['entities']) ? 1 : ($sandbox['total'] - count($sandbox['entities'])) / $sandbox['total'];
}

/**
 * Update job ids for config metadata, replacing invalid chars.
 */
function lingotek_post_update_lingotek_config_metadata_job_id(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'lingotek_config_metadata', function (LingotekConfigMetadata $metadata) {
      $lingotek = \Drupal::service('lingotek');
      $job_id = $metadata->getJobId();
      if (preg_match('@[\/\\\]+@', $job_id)) {
        $new_job_id = str_replace('/', '-', $job_id);
        $new_job_id = str_replace('\\', '-', $new_job_id);
        $metadata->setJobId($new_job_id);
        if ($new_job_id !== $job_id && $document_id = $metadata->getDocumentId()) {
          try {
            $lingotek->updateDocument($document_id, NULL, NULL, NULL, NULL, $new_job_id);
          }
          catch (LingotekApiException $exception) {
            \Drupal::logger('lingotek')
              ->error("Update of job_id %job failed for document %document",
                ['%job' => $new_job_id, '%document' => $document_id]);
          }
        }
        return TRUE;
      }
      return FALSE;
    });
}

/**
 * Update auto_request new setting with the value of auto_download for all profiles.
 */
function lingotek_post_update_lingotek_profile_auto_request(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'lingotek_profile', function (LingotekProfileInterface $profile) {
      $profile->setAutomaticRequest($profile->hasAutomaticDownload());
      $languages = \Drupal::languageManager()->getLanguages();
      foreach ($languages as $language) {
        $langcode = $language->getId();
        if ($profile->hasCustomSettingsForTarget($language->getId())) {
          $profile->setAutomaticRequestForTarget($langcode, $profile->hasAutomaticDownloadForTarget($langcode));
        }
      }
      return TRUE;
    });
}
