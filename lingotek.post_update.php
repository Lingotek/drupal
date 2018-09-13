<?php

/**
 * @file
 * Post update functions for Lingotek.
 */

use Drupal\lingotek\Entity\LingotekConfigMetadata;
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
