<?php

namespace Drupal\lingotek\Plugin\LingotekFieldProcessor;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\lingotek\FieldProcessor\LingotekFieldProcessorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @LingotekFieldProcessor(
 *   id = "path",
 *   weight = 5,
 * )
 */
class LingotekPathProcessor extends PluginBase implements LingotekFieldProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new UploadToLingotekAction action.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger;
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
      $container->get('module_handler'),
      $container->get('logger.factory')->get('lingotek')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function appliesToField(FieldDefinitionInterface $field_definition, ContentEntityInterface &$entity) {
    return 'path' === $field_definition->getType();
  }

  /**
   * {@inheritdoc}
   */
  public function extract(ContentEntityInterface &$entity, string $field_name, FieldDefinitionInterface $field_definition, array &$data, array &$visited = []) {
    if ($entity->id()) {
      $source = '/' . $entity->toUrl()->getInternalPath();
      /** @var \Drupal\Core\Entity\EntityStorageInterface $aliasStorage */
      $alias_storage = $this->entityTypeManager->getStorage('path_alias');
      /** @var \Drupal\path_alias\PathAliasInterface[] $paths */
      $paths = $alias_storage->loadByProperties([
        'path' => $source,
        'langcode' => $entity->language()->getId(),
      ]);
      if (count($paths) > 0) {
        $path = reset($paths);
        $alias = $path->getAlias();
        if ($alias !== NULL) {
          $data[$field_name][0]['alias'] = $alias;
        }
        unset($data[$field_name][0]['langcode']);
        unset($data[$field_name][0]['pid']);
      }
      else {
        unset($data[$field_name]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function store(ContentEntityInterface &$translation, string $langcode, ContentEntityInterface &$revision, string $field_name, FieldDefinitionInterface $field_definition, array &$field_data) {
    $stored = FALSE;
    $source = '/' . $revision->toUrl()->getInternalPath();
    /** @var \Drupal\Core\Entity\EntityStorageInterface $aliasStorage */
    $alias_storage = $this->entityTypeManager->getStorage('path_alias');
    /** @var \Drupal\path_alias\PathAliasInterface[] $original_paths */
    $original_paths = $alias_storage->loadByProperties(['path' => $source, 'langcode' => $revision->getUntranslated()->language()->getId()]);
    $original_path = NULL;
    $alias = $field_data[0]['alias'];
    // Validate the alias before saving.
    if (!UrlHelper::isValid($alias)) {
      $this->logger->warning('Alias for %type %label in language %langcode not saved, invalid uri "%uri"',
        ['%type' => $revision->getEntityTypeId(), '%label' => $revision->label(), '%langcode' => $langcode, '%uri' => $alias]);
      // Default to the original path.
      if (count($original_paths) > 0) {
        $original_path = reset($original_paths);
        $alias = $original_path->getAlias();
      }
      else {
        $alias = $source;
      }
      if ($this->moduleHandler->moduleExists('pathauto')) {
        $alias = '';
        $translation->get($field_name)->offsetGet(0)->set('alias', $alias);
        $translation->get($field_name)->offsetGet(0)->set('pathauto', TRUE);
        $stored = TRUE;
      }
    }
    if ($alias !== NULL && !$stored) {
      $translation->get($field_name)->offsetGet(0)->set('alias', $alias);
      if ($this->moduleHandler->moduleExists('pathauto') && !empty($alias) && $alias !== $original_path) {
        $translation->get($field_name)->offsetGet(0)->set('pathauto', FALSE);
      }
    }
  }

}
