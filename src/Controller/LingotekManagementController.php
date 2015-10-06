<?php

namespace Drupal\lingotek\Controller;

class LingotekManagementController extends LingotekControllerBase {

  public function content() {
    if ($redirect = $this->checkSetup()) {
      return $redirect;
    }
    $entity_types = \Drupal::service('lingotek.configuration')->getEnabledEntityTypes();
    $entity_type_id = NULL;
    if (!empty($entity_types)) {
      // Prioritize node as main content type.
      if (array_key_exists('node', $entity_types)) {
        $entity_type_id = 'node';
      }
      else {
        $entity_type_keys = array_keys($entity_types);
        $entity_type_id = reset($entity_type_keys);
      }
    }
    if ($entity_type_id) {
      return $this->redirect("lingotek.manage.$entity_type_id");
    }

    $build['enable_content_translation']['#markup'] =
      $this->t('You need to enable content translation first. You can enable translation for the desired content entities on the <a href=":translation-entity">Content language</a> page.',
        [':translation-entity' => \Drupal::url('language.content_settings_page')]) . '<br/>';
    $build['enable_lingotek']['#markup'] =
      $this->t('Then you need to configure how you want to translate your content with Lingotek. Enable translation for the desired content entities on the <a href=":lingotek-translation-entity">Lingotek settings</a> page.',
        [':lingotek-translation-entity' => \Drupal::url('lingotek.settings')]);

    return $build;
  }

}
