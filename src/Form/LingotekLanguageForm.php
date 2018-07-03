<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\language\ConfigurableLanguageInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\LanguageLocaleMapperInterface;
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
   * Constructs a new LingotekConfigTranslationService object.
   *
   * @param \Drupal\lingotek\LingotekInterface $lingotek
   *   A lingotek object.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   */
  public function __construct(LingotekInterface $lingotek, LanguageLocaleMapperInterface $language_locale_mapper) {
    $this->lingotek = $lingotek;
    $this->languageLocaleMapper = $language_locale_mapper;
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

    $form['custom_language']['lingotek_locale'] = [
      '#type' => 'textfield',
      '#title' => t('Locale'),
      // If we have a langcode, check if there is a locale or default to the one we can guess.
      '#default_value' => $langcode !== NULL ? str_replace("_", "-", $this->languageLocaleMapper->getLocaleForLangcode($langcode)) : '',
      '#weight' => 0,
      '#description' => $this->t('The Lingotek locale this language maps to.') . ' ' .
        $this->t('Use language codes as <a href=":w3ctags">defined by the W3C</a> for interoperability. <em>Examples: "en", "en-gb" and "zh-hant".</em>', [':w3ctags' => 'http://www.w3.org/International/articles/language-tags/']),
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
    $form_key = ['lingotek_locale'];
    if ($value = $form_state->getValue($form_key)) {
      $language->setThirdPartySetting('lingotek', 'locale', str_replace("-", "_", $value));
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
          drupal_set_message("The Lingotek locale has not been validated.", 'warning');
        }
      }
    }
  }

  /**
   * Checks if a locale is valid.
   *
   * @param string $locale
   *   The locale to validate.
   * @return bool
   *   TRUE if it's a valid locale in Lingotek. FALSE if not.
   */
  public static function isValidLocale($locale) {
    $locales = \Drupal::service('lingotek')->getLocales();
    return in_array($locale, $locales);
  }

}
