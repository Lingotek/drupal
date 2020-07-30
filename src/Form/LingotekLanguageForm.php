<?php

namespace Drupal\lingotek\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekInterface;

/**
 * Alters the Drupal language module language forms.
 *
 * @package Drupal\lingotek\Form
 */
class LingotekLanguageForm {

  use StringTranslationTrait;

  /**
   * A lingotek connector object
   *
   * @var \Drupal\lingotek\LingotekInterface
   */
  protected $lingotek;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * The Lingotek configuration service.
   *
   * @var \Drupal\lingotek\LingotekConfigurationServiceInterface
   */
  protected $lingotekConfiguration;

  /**
   * Constructs a new LingotekLanguageForm object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   A lingotek object.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The Lingotek configuration service.
   */
  public function __construct(LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration = NULL) {
    $this->lingotek = $lingotek;
    $this->languageLocaleMapper = $language_locale_mapper;
    if (!$lingotek_configuration) {
      @trigger_error('The lingotek.configuration service must be passed to LingotekLanguageForm::__construct, it is required before Lingotek 4.0.0.', E_USER_DEPRECATED);
      $lingotek_configuration = \Drupal::service('lingotek.configuration');
    }
    $this->lingotekConfiguration = $lingotek_configuration;
  }

  /**
   * Alters the configurable language entity edit and add form.
   *
   * @param array $form
   *   The form definition array for the configurable language entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function form(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\language\ConfigurableLanguageInterface $language */
    $language = $form_state->getFormObject()->getEntity();
    $langcode = $language->getId();

    $form['custom_language']['lingotek'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Lingotek translation'),
      '#weight' => 0,
    ];

    $form['custom_language']['lingotek']['lingotek_disabled'] = [
      '#type' => 'checkbox',
      '#title' => t('Disabled for Lingotek translation'),
      // If we have a langcode, check if there is a locale or default to the one we can guess.
      '#default_value' => $langcode !== NULL ? (!$this->lingotekConfiguration->isLanguageEnabled($language)) : FALSE,
      '#description' => $this->t('Check this if you want Lingotek to ignore this language or locale.'),
    ];

    $form['custom_language']['lingotek']['lingotek_locale'] = [
      '#type' => 'textfield',
      '#title' => t('Locale'),
      '#autocomplete_route_name' => 'lingotek.supported_locales_autocomplete',
      // If we have a langcode, check if there is a locale or default to the one we can guess.
      '#default_value' => $langcode !== NULL ? str_replace("_", "-", $this->languageLocaleMapper->getLocaleForLangcode($langcode)) : '',
      '#description' => $this->t('The Lingotek locale this language maps to.') . ' ' .
        $this->t('Use locale codes as <a href=":w3ctags">defined by the W3C</a> for interoperability. <em>Examples: "en", "en-gb" and "zh-hant".</em>', [':w3ctags' => 'http://www.w3.org/International/articles/language-tags/']),
    ];
    $form['custom_language']['lingotek']['lingotek_locale_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Lingotek supported locales list'),
      '#url' => Url::fromRoute('lingotek.supported_locales'),
      '#ajax' => [
        'class' => ['use-ajax'],
      ],
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'dialog',
        'data-dialog-options' => Json::encode([
          'width' => 861,
          'height' => 700,
          'draggable' => TRUE,
          'autoResize' => FALSE,
        ]),
      ],
    ];

    // Buttons are different if adding or editing a language. We need validation
    // on both cases.
    if ($langcode) {
      $form['actions']['submit']['#validate'][] = LingotekLanguageForm::class . '::validateLocale';
    }
    else {
      $form['custom_language']['submit']['#validate'][] = LingotekLanguageForm::class . '::validateLocale';
    }
    $form['#entity_builders'][] = LingotekLanguageForm::class . '::languageEntityBuilder';
  }

  /**
   * Entity builder for the configurable language type form with lingotek options.
   *
   * @param string $entity_type
   *   The entity type.
   * @param \Drupal\language\ConfigurableLanguageInterface $language
   *   The language object.
   * @param array $form
   *   The form definition array for the configurable language entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see lingotek_form_language_admin_add_form_alter()
   * @see lingotek_form_language_admin_edit_form_alter()
   */
  public static function languageEntityBuilder($entity_type, ConfigurableLanguageInterface $language, array &$form, FormStateInterface $form_state) {
    // We need to check if the value exists, as we are enabling by default those
    // predefined languages in Drupal.
    if ($form_state->hasValue(['lingotek_locale'])) {
      $lingotek_locale = $form_state->getValue(['lingotek_locale']);
      $lingotekDisabled = $form_state->getValue(['lingotek_disabled']);

      $language->setThirdPartySetting('lingotek', 'locale', str_replace("-", "_", $lingotek_locale));
      $language->setThirdPartySetting('lingotek', 'disabled', $lingotekDisabled);
    }
  }

  /**
   * Validate the configurable language type form with lingotek options.
   *
   * @param array $form
   *   The form definition array for the configurable language entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see lingotek_form_language_admin_add_form_alter()
   * @see lingotek_form_language_admin_edit_form_alter()
   */
  public static function validateLocale(&$form, FormStateInterface $form_state) {
    $form_key = ['lingotek_locale'];
    if (!$form_state->isValueEmpty($form_key)) {
      $value = $form_state->getValue($form_key);
      try {
        if (!self::isValidLocale($value)) {
          $form_state->setErrorByName('lingotek_locale', t('The Lingotek locale %locale does not exist.', ['%locale' => $value]));
        }
      }
      catch (LingotekApiException $lingotekApiException) {
        if ($lingotekApiException->getCode() === 401) {
          \Drupal::messenger()->addWarning("The Lingotek locale has not been validated.", 'warning');
        }
      }
    }
  }

  /**
   * Checks if a locale is valid.
   *
   * @param string $locale
   *   The locale to validate.
   *
   * @return bool
   *   TRUE if it's a valid locale in Lingotek. FALSE if not.
   */
  public static function isValidLocale($locale) {
    $locales = \Drupal::service('lingotek')->getLocales();
    return in_array(str_replace("_", "-", $locale), $locales);
  }

}
