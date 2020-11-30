<?php

namespace Drupal\lingotek_test;

use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\LingotekInterface;
use Drupal\lingotek\LingotekProfileInterface;

class LingotekFakeBC extends LingotekFake implements LingotekInterface {

  public function updateDocument($doc_id, $content, $url = NULL, $title = NULL, LingotekProfileInterface $profile = NULL, $job_id = NULL, $locale = NULL) {
    if (\Drupal::state()->get('lingotek.must_error_in_upload', FALSE)) {
      throw new LingotekApiException('Error was forced.');
    }
    if (is_array($content)) {
      $content = json_encode($content);
    }

    \Drupal::state()->set('lingotek.uploaded_content', $content);
    \Drupal::state()->set('lingotek.uploaded_content_url', $url);
    \Drupal::state()->set('lingotek.uploaded_title', $title);
    \Drupal::state()->set('lingotek.uploaded_job_id', $job_id);

    // Save the timestamp of the upload.
    $timestamps = \Drupal::state()->get('lingotek.upload_timestamps', []);
    $timestamps[$doc_id] = \Drupal::time()->getRequestTime();
    \Drupal::state()->set('lingotek.upload_timestamps', $timestamps);

    // Our document is always imported correctly.
    return TRUE;
  }

}
