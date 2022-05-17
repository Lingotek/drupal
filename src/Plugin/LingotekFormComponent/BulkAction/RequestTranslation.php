<?php

namespace Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekDocumentArchivedException;
use Drupal\lingotek\Exception\LingotekDocumentLockedException;
use Drupal\lingotek\Exception\LingotekDocumentNotFoundException;
use Drupal\lingotek\Exception\LingotekPaymentRequiredException;
use Drupal\lingotek\Exception\LingotekProcessedWordsLimitException;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionBase;
use Drupal\lingotek\FormComponent\LingotekFormComponentBulkActionExecutor;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\LingotekConfigurationServiceInterface;
use Drupal\lingotek\LingotekContentTranslationServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a Lingotek bulk action plugin for the request operation for a single
 * language.
 *
 * @LingotekFormComponentBulkAction(
 *   id = "request_translation",
 *   title = @Translation("Request %language (%langcode) translation"),
 *   group = @Translation("Request translations"),
 *   weight = 40,
 *   form_ids = {
 *     "lingotek_management",
 *     "lingotek_entity_management",
 *     "lingotek_job_content_entities_management"
 *   },
 *   batch = {
 *     "title" = @Translation("Requesting translations to Lingotek service.")
 *   },
 *   deriver = "Drupal\lingotek\Plugin\LingotekFormComponent\BulkAction\Derivative\LanguageLingotekBulkActionDeriver",
 * )
 */
class RequestTranslation extends LingotekFormComponentBulkActionBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The language-locale mapper.
   *
   * @var \Drupal\lingotek\LanguageLocaleMapperInterface
   */
  protected $languageLocaleMapper;

  /**
   * DebugExport constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity_type.manager service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language_manager service.
   * @param \Drupal\lingotek\LanguageLocaleMapperInterface $language_locale_mapper
   *   The language-locale mapper.
   * @param \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration
   *   The lingotek.configuration service.
   * @param \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service
   *   The lingotek.content_translation service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity_type.bundle.info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, LanguageManagerInterface $language_manager, LanguageLocaleMapperInterface $language_locale_mapper, LingotekConfigurationServiceInterface $lingotek_configuration, LingotekContentTranslationServiceInterface $translation_service, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $language_manager, $lingotek_configuration, $translation_service);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->languageLocaleMapper = $language_locale_mapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('lingotek.language_locale_mapper'),
      $container->get('lingotek.configuration'),
      $container->get('lingotek.content_translation'),
      $container->get('entity_type.bundle.info')
    );
  }

  public function executeSingle(ContentEntityInterface $entity, $options, LingotekFormComponentBulkActionExecutor $executor, array &$context) {
    $langcode = $this->pluginDefinition['langcode'];
    $context['message'] = $this->t('Requesting translation for @type %label to language @language.', ['@type' => $entity->getEntityType()->getLabel(), '%label' => $entity->label(), '@language' => $langcode]);
    $bundleInfos = $this->entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      $this->messenger()->addWarning($this->t('Cannot request @type %label translation for @language. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel(), '@language' => $langcode]));
      return FALSE;
    }
    if (!$this->lingotekConfiguration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning($this->t('Cannot request @type %label translation for @language. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel(), '@language' => $langcode]
      ));
      return FALSE;
    }
    $locale = $this->languageLocaleMapper->getLocaleForLangcode($langcode);
    if ($profile = $this->lingotekConfiguration->getEntityProfile($entity, FALSE)) {
      try {
        $this->translationService->addTarget($entity, $locale);
      }
      catch (LingotekDocumentNotFoundException $exc) {
        $this->messenger()->addError($this->t('Document @entity_type %title was not found. Please upload again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
        return FALSE;
      }
      catch (LingotekPaymentRequiredException $exception) {
        $this->messenger()->addError($this->t('Community has been disabled. Please contact support@lingotek.com to re-enable your community.'));
        return FALSE;
      }
      catch (LingotekDocumentArchivedException $exception) {
        $this->messenger()->addWarning($this->t('Document @entity_type %title has been archived. Uploading again.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '%title' => $entity->label(),
        ]));
        // We need to re-upload. The executor does that for us.
        throw $exception;
      }
      catch (LingotekDocumentNotFoundException $exc) {
        $this->messenger()->addError($this->t('Document @entity_type %title was not found. Please upload again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
        return FALSE;
      }
      catch (LingotekDocumentLockedException $exception) {
        $this->messenger()->addError($this->t('Document @entity_type %title has a new version. The document id has been updated for all future interactions. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
        return FALSE;
      }
      catch (LingotekProcessedWordsLimitException $exception) {
        $this->messenger()->addError($this->t('Processed word limit exceeded. Please contact your local administrator or Lingotek Client Success (<a href=":link">@mail</a>) for assistance.', [':link' => 'mailto:sales@lingotek.com', '@mail' => 'sales@lingotek.com']));
        return FALSE;
      }
      catch (LingotekApiException $exception) {
        $this->messenger()->addError($this->t('The request for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
        return FALSE;
      }
    }
    else {
      $this->messenger()->addWarning($this->t('The @type %label has no profile assigned so it was not processed.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label()]));
      return FALSE;
    }
    return TRUE;
  }

}
