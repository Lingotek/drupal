<?php

/**
 * @file
 * Checks if the Drupal version is higher than 8.6.x
 */

include "core/lib/Drupal.php";
if (((float) \Drupal::VERSION) >= 8.6) {
  exit(0);
}
else {
  exit(1);
}
