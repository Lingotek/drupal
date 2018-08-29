<?php

namespace Drupal\lingotek\Plugin\Action\Derivative;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Action\Plugin\Action\Derivative\EntityActionDeriverBase;
use Drupal\Core\Entity\EntityTypeInterface;

class ContentEntityLingotekActionDeriver extends EntityActionDeriverBase {

  /**
   * {@inheritdoc}
   */
  protected function isApplicable(EntityTypeInterface $entity_type) {
    return TRUE;
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $drupalConfiguration */
    $drupalConfiguration = \Drupal::service('lingotek.configuration');

    return $drupalConfiguration->isEnabled($entity_type->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $definitions = parent::getDerivativeDefinitions($base_plugin_definition);
    if (!empty($definitions)) {
      $entity_types = $this->entityTypeManager->getDefinitions();
      foreach ($this->derivatives as $entity_type_id => &$definition) {
        $definition['label'] = new FormattableMarkup($base_plugin_definition['action_label'], ['@entity_label' => $entity_types[$entity_type_id]->getSingularLabel()]);
      }
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
