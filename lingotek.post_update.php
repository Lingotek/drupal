<?php

/**
 * @file
 * Post update functions for Lingotek.
 */

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
