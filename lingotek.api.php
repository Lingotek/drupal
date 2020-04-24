<?php

/**
 * @file
 * Hooks provided by the Lingotek module.
 */

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Serialization\Yaml;

/**
 * @defgroup lingotek_api Lingotek API
 * @{
 * During Lingotek operations there are several sets of hooks that get
 * invoked to allow modules to modify the operation.
 * @}
 */


/**
 * @addtogroup hooks
 * @{
 */

/**
 * Act on a translation of an content entity before it is saved or updated after
 * being downloaded from Lingotek.
 *
 * @param \Drupal\Core\Entity\ContentEntityInterface &$translation
 *   The content entity that is going to be saved.
 * @param string $langcode
 *   Drupal language code that has been downloaded.
 * @param array $data
 *   Data returned from the Lingotek service when asking for the translation.
 *
 * @ingroup lingotek_api
 */
function hook_lingotek_content_entity_translation_presave(ContentEntityInterface &$translation, $langcode, $data) {
  // In this example, we avoid press releases to be published when downloaded.
  if ($translation->getEntityTypeId() === 'node' && $translation->bundle() === 'press_release') {
    if ($translation->isNewTranslation()) {
      /** @var \Drupal\node\NodeInterface $translation */
      $translation->setUnpublished();
    }
  }
}

/**
 * Act on the data extracted from an an content entity before it is uploaded to
 * Lingotek.
 *
 * @param array &$source_data
 *   The data that will be uploaded, as an associative array.
 * @param \Drupal\Core\Entity\ContentEntityInterface &$entity
 *   The content entity where the data is extracted from and will be associated
 *   to the Lingotek document.
 * @param string &$url
 *   The url which will be associated to this document, e.g. for context review.
 *
 * @ingroup lingotek_api
 */
function hook_lingotek_content_entity_document_upload(array &$source_data, ContentEntityInterface &$entity, &$url) {
  // In this example, press releases pages are always displayed in a view, so we
  // want to send a different url for in context review.
  if ($entity->getEntityTypeId() === 'node' && $entity->bundle() === 'press_release') {
    $url = \Drupal::request()->getBasePath() . '/press-release/view-path/' . $entity->id();
  }
}

/**
 * Determines the default Lingotek profile for the given entity.
 *
 * @param \Drupal\Core\Entity\ContentEntityInterface $entity
 *   The entity.
 * @param \Drupal\lingotek\LingotekProfileInterface &$profile
 *   The already calculated profile.
 * @param bool $provide_default
 *   If TRUE, and the entity does not have a profile, will retrieve the default
 *   for this entity type and bundle. Defaults to TRUE.
 *
 * @returns \Drupal\lingotek\LingotekProfileInterface
 *   The default profile.
 */
function hook_lingotek_content_entity_get_profile(ContentEntityInterface $entity, LingotekProfileInterface &$profile = NULL, $provide_default = TRUE) {
  /*
   * If the document being uploaded is a comment, use the profile from the
   * commented entity.
   */
  if ($entity->getEntityTypeId() === 'comment') {
    /** @var \Drupal\comment\CommentInterface $entity */
    $commented = $entity->getCommentedEntity();
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $lingotek_config */
    $lingotek_config = \Drupal::service('lingotek.configuration');
    $profile = $lingotek_config->getEntityProfile($commented, FALSE);
  }
}

/**
 * Act on a translation of a config entity before it is saved or updated after
 * being downloaded from Lingotek.
 *
 * @param \Drupal\Core\Config\Entity\ConfigEntityInterface &$translation
 *   The config entity that is going to be saved.
 * @param string $langcode
 *   Drupal language code that has been downloaded.
 * @param array &$data
 *   Data returned from the Lingotek service when asking for the translation.
 *
 * @ingroup lingotek_api
 */
function hook_lingotek_config_entity_translation_presave(ConfigEntityInterface &$translation, $langcode, &$data) {
  // In this example, all tokens are being decoded for block config entities.
  switch ($translation->getEntityTypeId()) {
    case 'block';
      // Decode all [tokens].
      $yaml = Yaml::encode($data);
      $yaml = preg_replace_callback(
        '/\[\*\*\*([^]]+)\*\*\*\]/', function ($matches) {
          return '[' . base64_decode($matches[1]) . ']';
        },
        $yaml
      );
      $data = Yaml::decode($yaml);
      break;
  }
}

/**
 * Act on the data extracted from a config entity before it is uploaded to
 * Lingotek.
 *
 * @param array &$source_data
 *   The data that will be uploaded, as an associative array.
 * @param \Drupal\Core\Config\Entity\ConfigEntityInterface &$entity
 *   The config entity where the data is extracted from and will be associated
 *   to the Lingotek document.
 * @param string &$url
 *   The url which will be associated to this document, e.g. for context review.
 *
 * @ingroup lingotek_api
 */
function hook_lingotek_config_entity_document_upload(array &$source_data, ConfigEntityInterface &$entity, &$url) {
  // In this example, all tokens are being encoded for block config entities.
  switch ($entity->getEntityTypeId()) {
    case 'block';
      // Encode all [tokens].
      $yaml = Yaml::encode($source_data);
      $yaml = preg_replace_callback(
        '/\[([a-z][^]]+)\]/', function ($matches) {
          return '[***' . base64_encode($matches[1]) . '***]';
        },
        $yaml
      );
      $source_data = Yaml::decode($yaml);
      break;
  }
}

/**
 * Act on a translation of a config entity before it is saved or updated after
 * being downloaded from Lingotek.
 *
 * @param array &$data
 *   Data returned from the Lingotek service when asking for the translation.
 * @param string $config_name
 *   The simple configuration name.
 *
 * @ingroup lingotek_api
 */
function hook_lingotek_config_object_translation_presave(array &$data, $config_name) {
  // Decode all [tokens].
  $yaml = Yaml::encode($data);
  $yaml = preg_replace_callback(
    '/\[\*\*\*([^]]+)\*\*\*\]/', function ($matches) {
      return '[' . base64_decode($matches[1]) . ']';
    },
    $yaml
  );
  $data = Yaml::decode($yaml);
}

/**
 * Act on the data extracted from a config object before it is uploaded to
 * Lingotek.
 *
 * @param array &$data
 *   Data returned from the Lingotek service when asking for the translation.
 * @param string $config_name
 *   The simple configuration name.
 *
 * @ingroup lingotek_api
 */
function hook_lingotek_config_object_document_upload(array &$data, $config_name) {
  // Encode all [tokens].
  $yaml = Yaml::encode($data);
  $yaml = preg_replace_callback(
    '/\[([a-z][^]]+)\]/', function ($matches) {
      return '[***' . base64_encode($matches[1]) . '***]';
    },
    $yaml
  );
  $data = Yaml::decode($yaml);
}
