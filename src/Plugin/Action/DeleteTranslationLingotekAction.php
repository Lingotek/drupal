<?php

namespace Drupal\lingotek\Plugin\Action;

/**
 * Delete Lingotek translation of a content entity for one language.
 *
 * @Action(
 *   id = "entity:lingotek_delete_translation_action",
 *   action_label = @Translation("Delete @entity_label translation for @language"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class DeleteTranslationLingotekAction extends LingotekContentEntityConfigurableActionBase {

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $entityInfo = [];
    $configuration = $this->getConfiguration();
    $langcode = $configuration['language'];

    foreach ($entities as $entity) {
      $source_language = $entity->getUntranslated()->language();
      if ($source_language->getId() !== $langcode && $entity->hasTranslation($langcode)) {
        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entityInfo[$entity->id()][$langcode] = $langcode;
      }
    }
    \Drupal::getContainer()->get('tempstore.private')
      ->get('entity_delete_multiple_confirm')
      ->set(\Drupal::currentUser()->id() . ':node', $entityInfo);
    if (empty($entityInfo)) {
      $this->messenger()->addWarning("No valid translations for deletion.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $this->executeMultiple([$entity]);
  }

}
