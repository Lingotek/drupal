<?php

namespace Drupal\Tests\lingotek\Functional;

/**
 * Trait with Lingotek interface translation test assertion helpers.
 */
trait LingotekInterfaceTranslationTestTrait {

  protected function assertLingotekInterfaceTranslationUploadLink($component) {
    $basepath = \Drupal::request()->getBasePath();
    $href = $basepath . '/admin/lingotek/interface-translation/upload?component=' . $component;
    if ($destination = $this->getDestination()) {
      $href .= $destination;
    }
    $this->assertSession()->linkByHrefExists($href, 0);
  }

  protected function assertLingotekInterfaceTranslationCheckSourceStatusLink($component) {
    $basepath = \Drupal::request()->getBasePath();
    $href = $basepath . '/admin/lingotek/interface-translation/check-upload?component=' . $component;
    if ($destination = $this->getDestination()) {
      $href .= $destination;
    }
    $this->assertSession()->linkByHrefExists($href, 0);
  }

  protected function assertLingotekInterfaceTranslationRequestTranslationLink($component, $locale) {
    $basepath = \Drupal::request()->getBasePath();
    $href = $basepath . '/admin/lingotek/interface-translation/request-translation?component=' . $component . '&locale=' . $locale;
    if ($destination = $this->getDestination()) {
      $href .= $destination;
    }
    $this->assertSession()->linkByHrefExists($href, 0);
  }

  protected function assertNoLingotekInterfaceTranslationRequestTranslationLink($component, $locale) {
    $basepath = \Drupal::request()->getBasePath();
    $href = $basepath . '/admin/lingotek/interface-translation/request-translation?component=' . $component . '&locale=' . $locale;
    if ($destination = $this->getDestination()) {
      $href .= $destination;
    }
    $this->assertSession()->linkByHrefNotExists($href, 0);
  }

  protected function assertLingotekInterfaceTranslationCheckTargetStatusLink($component, $locale) {
    $basepath = \Drupal::request()->getBasePath();
    $href = $basepath . '/admin/lingotek/interface-translation/check-translation?component=' . $component . '&locale=' . $locale;
    if ($destination = $this->getDestination()) {
      $href .= $destination;
    }
    $this->assertSession()->linkByHrefExists($href, 0);
  }

  protected function assertLingotekInterfaceTranslationDownloadLink($component, $locale) {
    $basepath = \Drupal::request()->getBasePath();
    $href = $basepath . '/admin/lingotek/interface-translation/download-translation?component=' . $component . '&locale=' . $locale;
    if ($destination = $this->getDestination()) {
      $href .= $destination;
    }
    $this->assertSession()->linkByHrefExists($href, 0);
  }

  protected function getDestination($entity_type_id = 'node', $prefix = NULL) {
    $basepath = \Drupal::request()->getBasePath();
    return '&destination=' . $basepath . $this->getInterfaceTranslationFormUrl($prefix);
  }

  protected function getInterfaceTranslationFormUrl($prefix = NULL) {
    return ($prefix === NULL ? '' : '/' . $prefix) . '/admin/lingotek/manage/interface-translation';
  }

}
