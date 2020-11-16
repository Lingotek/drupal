<?php

namespace Drupal\lingotek_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Lingotek Test Rich Text' block.
 *
 * @Block(
 *   id = "lingotek_test_rich_text_block",
 *   admin_label = @Translation("Lingotek Test Rich text"),
 *   category = @Translation("Content Elements"),
 * )
 */
class TestRichTextBlock extends BlockBase implements BlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $form['rich_text'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Rich text'),
      '#format' => isset($this->configuration['rich_text']['format']) ? $this->configuration['rich_text']['format'] : NULL,
      '#default_value' => isset($this->configuration['rich_text']['value']) ? $this->configuration['rich_text']['value'] : '',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $this->configuration['rich_text'] = $form_state->getValue('rich_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $value = isset($this->configuration['rich_text']) && isset($this->configuration['rich_text']['value']) ? $this->configuration['rich_text']['value'] : '';
    $format = isset($this->configuration['rich_text']) && isset($this->configuration['rich_text']['format']) ? $this->configuration['rich_text']['format'] : 'plain_text';

    return [
      '#type' => 'processed_text',
      '#text' => $value,
      '#format' => $format,
    ];
  }

}
