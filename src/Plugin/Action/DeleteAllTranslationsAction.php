<?php

namespace Drupal\lingotek\Plugin\Action;

/**
 * Assigns ownership of a node to a user.
 *
 * @Action(
 *   id = "entity:lingotek_delete_translations_action",
 *   action_label = @Translation("Delete all @entity_label translations"),
 *   category = "Lingotek",
 *   deriver = "Drupal\lingotek\Plugin\Action\Derivative\ContentEntityLingotekActionDeriver",
 * )
 */
class DeleteAllTranslationsAction extends LingotekContentEntityActionBase {

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $entityInfo = [];
    $languages = \Drupal::languageManager()->getLanguages();

    foreach ($entities as $entity) {
      $source_language = $entity->getUntranslated()->language();
      foreach ($languages as $langcode => $language) {
        if ($source_language->getId() !== $langcode && $entity->hasTranslation($langcode)) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $entityInfo[$entity->id()][$langcode] = $langcode;
        }
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
