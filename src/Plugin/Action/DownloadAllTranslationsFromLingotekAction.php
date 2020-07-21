<?php

namespace Drupal\lingotek\Plugin\Action;

use Drupal\lingotek\Exception\LingotekApiException;
use Drupal\lingotek\Exception\LingotekContentEntityStorageException;

/**
 * Assigns ownership of a node to a user.
 *
 * @Action(
 *   id = "entity:lingotek_download_translations_action",
 *   action_label = @Translation("Download all @entity_label translations from Lingotek"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class DownloadAllTranslationsFromLingotekAction extends LingotekContentEntityActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $result = FALSE;
    $entityTypeBundleInfo = \Drupal::service('entity_type.bundle.info');
    $bundleInfos = $entityTypeBundleInfo->getBundleInfo($entity->getEntityTypeId());
    if (!$entity->getEntityType()->isTranslatable() || !$bundleInfos[$entity->bundle()]['translatable']) {
      $this->messenger()->addWarning(t('Cannot download @type %label. That @bundle_label is not enabled for translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return FALSE;
    }
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_configuration */
    $lingotek_configuration = \Drupal::service('lingotek.configuration');
    if (!$lingotek_configuration->isEnabled($entity->getEntityTypeId(), $entity->bundle())) {
      $this->messenger()->addWarning(t('Cannot download @type %label. That @bundle_label is not enabled for Lingotek translation.',
        ['@type' => $bundleInfos[$entity->bundle()]['label'], '%label' => $entity->label(), '@bundle_label' => $entity->getEntityType()->getBundleLabel()]));
      return FALSE;
    }
    try {
      /** @var \Drupal\node\NodeInterface $entity */
      $result = $this->translationService->downloadDocuments($entity);
    }
    catch (LingotekApiException $exception) {
      $this->messenger()->addError(t('The download for @entity_type %title translation failed. Please try again.', ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label()]));
    }
    catch (LingotekContentEntityStorageException $storage_exception) {
      \Drupal::logger('lingotek')->error('The download for @entity_type %title failed because of the length of one field translation value: %table.',
        ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '%table' => $storage_exception->getTable()]);
      $this->messenger()->addError(t('The download for @entity_type %title failed because of the length of one field translation value: %table.',
        ['@entity_type' => $entity->getEntityTypeId(), '%title' => $entity->label(), '%table' => $storage_exception->getTable()]));
    }
    return $result;
  }

}
