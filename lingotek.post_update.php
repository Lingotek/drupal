<?php

/**
 * @file
 * Post update functions for Lingotek.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\lingotek\LingotekProfileInterface;

/**
 * Update target custom profile settings with default value.
 */
function lingotek_post_update_lingotek_profile_target_save_to_vault(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'lingotek_profile', function (LingotekProfileInterface $profile) {
      // Default target save-to vaults to default
      $languages = \Drupal::languageManager()->getLanguages();
      foreach ($languages as $language) {
        $langcode = $language->getId();
        if ($profile->hasCustomSettingsForTarget($langcode)) {
          $profile->setVaultForTarget($langcode, 'default');
        }
      }
      return TRUE;
    });
}

/**
 * Remove obsolete Lingotek account configuration.
 */
function lingotek_post_update_delete_sandbox_host_settings() {
  \Drupal::configFactory()->getEditable('lingotek.settings')
    ->clear('account.use_production')
    ->clear('account.sandbox_host')
    ->save();
}

/**
 * Implements hook_removed_post_updates().
 */
function lingotek_removed_post_updates() {
  return [
    'lingotek_post_update_lingotek_manage_lingotek_translations_permission' => '3.0.0',
    'lingotek_post_update_lingotek_metadata_dependencies' => '3.0.0',
    'lingotek_post_update_lingotek_content_metadata_job_id' => '3.0.0',
    'lingotek_post_update_lingotek_config_metadata_job_id' => '3.0.0',
    'lingotek_post_update_lingotek_profile_auto_request' => '3.0.0',
  ];
}
