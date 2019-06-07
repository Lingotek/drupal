<?php

namespace Drupal\lingotek\Plugin\Menu;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;

class JobConfigLocalTask extends LocalTaskDefault {

  use StringTranslationTrait;

  public function getRouteName() {
    return 'lingotek.translation_job_info.config';
  }

  public function getTitle(Request $request = NULL) {
    $job_id = $request->get('job_id');
    return $this->t('Job @job_id Config', ['@job_id' => $job_id]);
  }

}
