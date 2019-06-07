<?php

namespace Drupal\lingotek\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Element\Textfield;

/**
 * Provides a job id render element.
 *
 * Provides a form element to enter a job ID, which is validated to ensure
 * that does not contain disallowed characters.
 *
 * As the user types text into the source element, the JavaScript converts all
 * values to lower case, and replaces any remaining disallowed characters with a
 * replacement.
 *
 * Properties:
 * - #job_id: An associative array containing:
 *   - label: (optional) Text to display as label for the machine name value
 *     after the human-readable name form element. Defaults to t('Job ID').
 *   - replace_pattern: (optional) A regular expression (without delimiters)
 *     matching disallowed characters in the machine name. Defaults to '/'.
 *   - replace: (optional) A character to replace disallowed characters in the
 *     machine name via JavaScript. Defaults to '\' (underscore). When using a
 *     different character, 'replace_pattern' needs to be set accordingly.
 *   - error: (optional) A custom form error message string to show, if the
 *     job id contains disallowed characters.
 *
 * Usage example:
 * @code
 * $form['id'] = array(
 *   '#type' => 'job_id',
 *   '#job_id' => array(
 *      'pattern' => '[^\/]*',
 *      'replace_pattern' => '/',
 *      'replace' => '\\',
 *   ),
 * );
 * @endcode
 *
 * @see \Drupal\Core\Render\Element\Textfield
 *
 * @FormElement("lingotek_job_id")
 */
class LingotekJobId extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#default_value' => NULL,
      '#process' => [
        [$class, 'processJobId'],
        [$class, 'processAjaxForm'],
      ],
      '#element_validate' => [
        [$class, 'validateJobId'],
      ],
      '#pre_render' => [
        [$class, 'preRenderTextfield'],
      ],
      '#theme' => 'input__textfield',
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      // This should be a string, but allow other scalars since they might be
      // valid input in programmatic form submissions.
      return is_scalar($input) ? (string) $input : '';
    }
    return NULL;
  }

  /**
   * Processes a machine-readable name form element.
   *
   * @param array $element
   *   The form element to process. See main class documentation for properties.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processJobId(&$element, FormStateInterface $form_state, &$complete_form) {
    // Apply default form element properties.
    $element += [
      '#title' => t('Job ID'),
      '#description' => t('Assign a job id that you can filter on later on the TMS or in this page.'),
      '#job_id' => [],
      '#size' => 50,
    ];
    // A form element that doesn't need to set any #job_id property would leave
    // all properties undefined, if the defaults were defined by an element
    // plugin. Therefore, we apply the defaults here.
    $element['#job_id'] += [
      'element' => '#' . $element['#id'],
      'label' => t('Job ID'),
      'pattern' => '[^\/\\]+',
      // We need an extra \ because this is consumed by the JS.
      'replace_pattern' => '[\/\\\]+',
      'replace' => '-',
    ];

    // By default, machine names are restricted to Latin alphanumeric characters.
    // So, default to LTR directionality.
    if (!isset($element['#attributes'])) {
      $element['#attributes'] = [];
    }
    $element['#attributes'] += ['dir' => LanguageInterface::DIRECTION_LTR];

    $element['#attached']['library'][] = 'lingotek/lingotek.job';
    $options = [
      'replace_pattern',
      'replace',
      'element',
      'label',
    ];

    $element['#attached']['drupalSettings']['lingotekJobId']['#' . $element['#id']] = array_intersect_key($element['#job_id'], array_flip($options));

    return $element;
  }

  /**
   * Form element validation handler for job_id elements.
   *
   * This checks that the submitted value:
   * - Does not contain disallowed characters.
   */
  public static function validateJobId(&$element, FormStateInterface $form_state, &$complete_form) {
    // Verify that the job ID contains no disallowed characters.
    if (preg_match('@' . $element['#job_id']['replace_pattern'] . '@', $element['#value'])) {
      if (!isset($element['#job_id']['error'])) {
        $form_state->setError($element, t('The job ID name cannot contain invalid chars as "/" or "\".'));
      }
      else {
        $form_state->setError($element, $element['#job_id']['error']);
      }
    }
  }

}
