<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\lingotek\Entity\LingotekConfigMetadata;
use Drupal\config_translation\ConfigEntityMapper;
use Drupal\lingotek\Entity\LingotekContentMetadata;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\LingotekConfigTranslationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Show a warning before disassociate all content.
 */
class LingotekDisassociateAllConfirmForm extends ConfirmFormBase {

  /**
   * The Lingotek content translation service.
   *
   * @var \Drupal\lingotek\LingotekContentTranslationServiceInterface
   */
  protected $contentTranslationService;

  /**
   * The Lingotek configuration translation service.
   *
   * @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface
   */
  protected $configTranslationService;

  /**
   * Constructs a new LingotekDisassociateAllConfirmForm object.
   *
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service
   *   The Lingotek content translation service.
   * @param \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service
   *   The Lingotek config translation service.
   */
  public function __construct(LingotekContentTranslationServiceInterface $content_translation_service, LingotekConfigTranslationServiceInterface $config_translation_service) {
    $this->contentTranslationService = $content_translation_service;
    $this->configTranslationService = $config_translation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('lingotek.content_translation'),
      $container->get('lingotek.config_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'lingotek_disassociate_all_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormName() {
    return 'lingotek_disassociate_all_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disassociate everything from Lingotek?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $string = '<p>' . $this->t("This is useful for switching between different environments. It will disassociate the content from TMS content so your next changes won't alter what is getting worked on from the TMS.") . '</p>';
    $string .= '<p>' . $this->t("This option should only be used if you still want the translations to be completed (and eventually billed for) in Lingotek's TMS.") . '</p>';
    $string .= '<p>' . $this->t("Check if you may want to use the Cancel option instead.") . '</p>';
    $string .= '<p>' . parent::getDescription() . '</p>';
    return $string;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Disassociate');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('lingotek.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->disassociateAllTranslations();
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Disassociate all content and config translations.
   */
  public function disassociateAllTranslations() {
    $error = FALSE;

    $error &= $this->disassociateAllContentTranslations();
    $error &= $this->disassociateAllConfigTranslations();

    if ($error) {
      $this->messenger()->addWarning($this->t('Some translations may have been disassociated, but some failed.'));
    }
    else {
      $this->messenger()->addStatus($this->t('All translations have been disassociated.'));
    }
  }

  /**
   * Disassociate all config translations.
   */
  protected function disassociateAllConfigTranslations() {
    $error = FALSE;

    /** @var \Drupal\lingotek\Entity\LingotekConfigMetadata[] $all_config_metadata */
    $all_config_metadata = LingotekConfigMetadata::loadMultiple();
    foreach ($all_config_metadata as $config_metadata) {
      try {
        $mapper = $config_metadata->getConfigMapper();
        if ($mapper instanceof ConfigEntityMapper) {
          $entity = $mapper->getEntity();
          $this->configTranslationService->deleteMetadata($entity);
        }
        else {
          $this->configTranslationService->deleteConfigMetadata($mapper->getPluginId());
        }
      }
      catch (LingotekApiException $exception) {
        $error = TRUE;
        if ($mapper instanceof ConfigEntityMapper) {
          $this->messenger()->addError(t('The deletion of %title failed. Please try again.', ['%title' => $mapper->getEntity()->label()]));
        }
        else {
          $this->messenger()->addError(t('The deletion of %title failed. Please try again.', ['%title' => $mapper->getPluginId()]));
        }
      }
    }
    return $error;
  }

  /**
   * Disassociate all content translations.
   */
  protected function disassociateAllContentTranslations() {
    $error = FALSE;

    /** @var \Drupal\lingotek\Entity\LingotekContentMetadata[] $all_content_metadata */
    $all_content_metadata = LingotekContentMetadata::loadMultiple();
    foreach ($all_content_metadata as $content_metadata) {
      try {
        $content_metadata->delete();
      }
      catch (LingotekApiException $exception) {
        $error = TRUE;
        $this->messenger()->addError(t('The disassociation of @entity_type %title failed. Please try again.', ['@entity_type' => $content_metadata->getContentEntityTypeId(), '%title' => $content_metadata->id()]));
      }
    }
    if ($error) {
      $this->messenger()->addWarning($this->t('Some translations may have been disassociated, but some failed.'));
    }
    else {
      $this->messenger()->addStatus($this->t('All translations have been disassociated.'));
    }
    return $error;
  }

}
