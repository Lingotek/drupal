<?php

use Drupal\lingotek\Entity\LingotekConfigMetadata;

/**
 * @addtogroup updates-8.x-1.10
 * @{
 */

/**
 * Fix lingotek metadata entities with dependencies on config entities which
 * names were wrongly calculated.
 */
function lingotek_post_update_lingotek_metadata_dependencies() {
  $metadatas = LingotekConfigMetadata::loadMultiple();
  array_walk($metadatas, function(LingotekConfigMetadata $metadata) {
    $old_dependencies = $metadata->getDependencies();
    $new_dependencies = $metadata->calculateDependencies()->getDependencies();
    if ($old_dependencies !== $new_dependencies) {
      $metadata->save();
    }
  });
}