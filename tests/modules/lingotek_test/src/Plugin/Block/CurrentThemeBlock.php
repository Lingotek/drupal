<?php

namespace Drupal\lingotek_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Block that shows the current theme.
 *
 * @Block(
 *   id = "current_theme_block",
 *   admin_label = "Current theme block",
 * )
 */
class CurrentThemeBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $theme = \Drupal::theme()->getActiveTheme()->getName();
    return ['#markup' => $this->t('Current theme: @theme', ['@theme' => $theme])];
  }

}
