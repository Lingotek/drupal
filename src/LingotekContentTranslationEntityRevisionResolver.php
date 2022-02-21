<?php

namespace Drupal\lingotek;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolver of the entity revision from which we need to extract the data from.
 */
class LingotekContentTranslationEntityRevisionResolver implements ContainerInjectionInterface, LingotekContentTranslationEntityRevisionResolverInterface {

  /**
   * Resolve to the same revision passed.
   *
   * @var string
   */
  const RESOLVE_SAME = 'same';

  /**
   * Resolve to the last translation affected revision.
   *
   * @var string
   */
  const RESOLVE_LATEST_TRANSLATION_AFFECTED = 'latest_translation_affected';

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LingotekContentTranslationEntityRevisionResolver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   An entity manager object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(ContentEntityInterface $entity, string $mode) {
    $source_entity = NULL;
    if ($entity instanceof RevisionableInterface) {
      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      $langcode = $entity->getUntranslated()->language()->getId();
      if ($mode === self::RESOLVE_LATEST_TRANSLATION_AFFECTED &&
        $revision_id = $storage->getLatestTranslationAffectedRevisionId($entity->id(), $langcode)) {
        $source_entity = $storage->loadRevision($revision_id);
      }
      else {
        $source_entity = $entity->getUntranslated();
      }
    }
    return $source_entity;
  }

}
