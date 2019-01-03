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
  public function execute($entity = NULL) {
    /** @var \Drupal\node\NodeInterface $entity */
    foreach ($entity->getTranslationLanguages() as $language) {
      if ($language->getId() !== $entity->getUntranslated()->language()->getId()) {
        $entity->removeTranslation($language->getId());
      }
    }
    $entity->save();
    return TRUE;
  }

}
