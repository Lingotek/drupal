<?php

namespace Drupal\lingotek\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'lingotek_translation_status' formatter.
 *
 * @FieldFormatter(
 *   id = "lingotek_translation_status",
 *   label = @Translation("Lingotek translation status"),
 *   field_types = {
 *     "lingotek_language_key_value",
 *   }
 * )
 */
class LingotekTranslationStatusFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $elements['#attached']['library'][] = 'lingotek/lingotek';

    foreach ($items as $delta => $item) {
      $elements[$delta] = $this->formatItem($item);
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'lingotek_language_key_value';
  }

  protected function formatItem($item) {
    $value = $item->getValue();
    $langcode = $value['language'];
    $status = $value['value'];
    $languages = [];
    if (\Drupal::languageManager()->getLanguage($langcode)) {
      $languages[] = [
        'language' => strtoupper($langcode),
        'status' => strtolower($status),
        'status_text' => $status,
        'url' => NULL,
        'new_window' => FALSE
      ];
    }
    return [
      '#type' => 'inline_template',
      '#template' => '{% for language in languages %}{% if language.url %}<a href="{{ language.url }}" {%if language.new_window%}target="_blank"{%endif%}{%else%}<span {%endif%} class="language-icon target-{{language.status}}" title="{{language.status_text}}">{{language.language}}{%if language.url%}</a>{%else%}</span>{%endif%}{% endfor %}',
      '#context' => [
        'languages' => $languages,
      ],
    ];
  }

}
