<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lingotek\LingotekInterfaceTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to disconnect from Lingotek.
 */
class LingotekInterfaceTranslationClearMetadataForm extends ConfirmFormBase {

  /**
   * The Lingotek interface translation service.
   *
   * @var \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface
   */
  protected $lingotekInterfaceTranslation;

  /**
   * Constructs a LingotekInterfaceTranslationClearMetadataForm object.
   *
   * @param \Drupal\lingotek\LingotekInterfaceTranslationServiceInterface $lingotek_interface_translation
   *   The Lingotek interface translation service.
   */
  public function __construct(LingotekInterfaceTranslationServiceInterface $lingotek_interface_translation) {
    $this->lingotekInterfaceTranslation = $lingotek_interface_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek.interface_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek_interface_translation_clear_metadata_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to clear the Lingotek metadata?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t("This will remove the metadata stored about your Lingotek interface translations, so you will need to re-upload those in case you want to translate them. This operation won't remove any interface translations from your Drupal site.") . ' ' . $this->t('This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Clear metadata');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('lingotek.manage_interface_translation');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->lingotekInterfaceTranslation->deleteAllMetadata();

    $this->logger('lingotek')->notice('Cleared interface translation Lingotek metadata.');
    $this->messenger()->addStatus($this->t('You have cleared the Lingotek metadata for interface translations.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
