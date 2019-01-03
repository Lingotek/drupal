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
  public function execute($entity = NULL) {
    $configuration = $this->getConfiguration();
    $langcode = $configuration['language'];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity->removeTranslation($langcode);
    $result = $entity->save();
    return $result;
  }

}
