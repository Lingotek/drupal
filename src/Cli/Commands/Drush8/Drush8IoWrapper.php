<?php

namespace Drupal\lingotek\Cli\Commands\Drush8;

use Drush\Log\LogLevel;

/**
 * Class Drush8IoWrapper.
 * Originally from ConfigSplitDrush8Io.
 *
 * This is a stand in for \Symfony\Component\Console\Style\StyleInterface with
 * drush 8 so that we don't need to depend on symfony components.
 */
class Drush8IoWrapper {

  public function writeln($text) {
    $this->success($text);
  }

  public function confirm($text) {
    return drush_confirm($text);
  }

  public function success($text) {
    drush_log($text, LogLevel::SUCCESS);
  }

  public function error($message, array $context = []) {
    drush_log($message, LogLevel::ERROR);
  }

  public function text($text) {
    drush_log($text, LogLevel::NOTICE);
  }

}
